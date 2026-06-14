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

    $studentNoRange = $app->lengthRange('student_no_length', 5, 5);
    $studentNameRange = $app->lengthRange('student_name_length', 1, 5);
    $studentNoLength = $app->textLength($studentNo);
    $studentNameLength = $app->textLength($name);

    if ($studentNoLength < $studentNoRange['min'] || $studentNoLength > $studentNoRange['max']) {
        $app->error($app->lengthRequirementText('학번은', 'student_no_length', 5, 5), 400);
    }

    if ($studentNameLength < $studentNameRange['min'] || $studentNameLength > $studentNameRange['max']) {
        $app->error($app->lengthRequirementText('이름은', 'student_name_length', 1, 5), 400);
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
