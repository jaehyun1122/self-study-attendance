<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $input = $app->jsonInput();
    $app->requireFields($input, ['type', 'id']);

    $type = (string) $input['type'];
    $id = (int) $input['id'];

    if ($id < 1) {
        $app->error('올바른 출석 기록을 선택해주세요.', 400);
    }

    if ($type === 'get') {
        $statement = $app->pdo()->prepare(
            'SELECT id, student_no, name, attend_date, created_at, created_at AS attend_datetime FROM attendance WHERE id = :id'
        );
        $statement->execute([':id' => $id]);
        $record = $statement->fetch();

        if (!$record) {
            $app->error('출석 기록을 찾을 수 없습니다.', 404);
        }

        $record['attend_datetime'] = $app->formatDateTime($record['created_at'] ?? $record['attend_datetime'] ?? null);

        $app->success('성공적으로 처리되었습니다.', $record);
    }

    if ($type === 'delete') {
        $statement = $app->pdo()->prepare('DELETE FROM attendance WHERE id = :id');
        $statement->execute([':id' => $id]);
        $app->success('삭제되었습니다.');
    }

    if ($type !== 'update') {
        $app->error('지원하지 않는 수정 유형입니다.', 400);
    }

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

    $statement = $app->pdo()->prepare('UPDATE attendance SET student_no = :student_no, name = :name WHERE id = :id');

    try {
        $statement->execute([
            ':student_no' => $studentNo,
            ':name' => $name,
            ':id' => $id,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            $app->error('같은 날짜에 동일한 학번 출석 기록이 이미 있습니다.', 409);
        }

        throw $exception;
    }

    $app->success('저장되었습니다.');
} catch (Throwable $exception) {
    $app->error('출석 기록 저장 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
