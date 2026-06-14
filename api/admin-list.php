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
    $date = trim((string) ($input['date'] ?? $app->today()));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $app->error('날짜 형식은 YYYY-MM-DD이어야 합니다.', 400);
    }

    $statement = $app->pdo()->prepare(
        'SELECT id, student_no, name, attend_date, created_at, created_at AS attend_datetime FROM attendance WHERE attend_date = :attend_date ORDER BY created_at ASC, id ASC'
    );
    $statement->execute([':attend_date' => $date]);

    $rows = array_map(static function (array $row) use ($app): array {
        $row['attend_datetime'] = $app->formatDateTime($row['created_at'] ?? $row['attend_datetime'] ?? null);
        return $row;
    }, $statement->fetchAll());

    $app->success('성공적으로 처리되었습니다.', $rows);
} catch (Throwable $exception) {
    $app->error('출석 목록 조회 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
