<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireInstalled();

    $input = $app->jsonInput();
    $app->requireFields($input, ['student_no', 'name']);

    $studentNo = trim(preg_replace('/\s+/', '', (string) $input['student_no']) ?? '');
    $name = trim(preg_replace('/\s+/', ' ', (string) $input['name']) ?? '');

    if ($studentNo === '' || $name === '') {
        $app->error('학번과 이름을 입력해주세요.', 400);
    }

    $studentNoLength = $app->int('student_no_length', 5);
    $studentNameMaxLength = $app->int('student_name_max_length', 5);

    if ($app->textLength($studentNo) !== $studentNoLength) {
        $app->error("학번은 {$studentNoLength}자여야 합니다.", 400);
    }

    if ($app->textLength($name) > $studentNameMaxLength) {
        $app->error("이름은 {$studentNameMaxLength}자까지 입력할 수 있습니다.", 400);
    }

    $attendDate = $app->today();
    $attendDateTime = $app->now();

    $statement = $app->pdo()->prepare(
        'INSERT INTO attendance (student_no, name, attend_date, created_at) VALUES (:student_no, :name, :attend_date, :created_at)'
    );

    try {
        $statement->execute([
            ':student_no' => $studentNo,
            ':name' => $name,
            ':attend_date' => $attendDate,
            ':created_at' => $attendDateTime,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        $duplicate = $app->pdo()->prepare('SELECT created_at FROM attendance WHERE student_no = :student_no AND attend_date = :attend_date');
        $duplicate->execute([
            ':student_no' => $studentNo,
            ':attend_date' => $attendDate,
        ]);
        $createdAt = $app->formatDateTime($duplicate->fetchColumn() ?: $attendDateTime);

        $app->error('이미 출석 처리되었습니다.', 200, [
            'student_no' => $studentNo,
            'name' => $name,
            'attend_date' => $attendDate,
            'attend_datetime' => $createdAt,
        ]);
    }

    $app->success('성공적으로 처리되었습니다.', [
        'student_no' => $studentNo,
        'name' => $name,
        'attend_date' => $attendDate,
        'attend_datetime' => $app->formatDateTime($attendDateTime),
        'attend_time' => $app->formatDateTime($attendDateTime),
    ]);
} catch (Throwable $exception) {
    $app->error('출석 처리 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
