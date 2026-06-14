<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $todayStatement = $app->pdo()->prepare('SELECT COUNT(*) FROM attendance WHERE attend_date = :attend_date');
    $todayStatement->execute([':attend_date' => $app->today()]);

    $app->success('성공적으로 처리되었습니다.', [
        'today' => (int) $todayStatement->fetchColumn(),
        'total' => (int) $app->pdo()->query('SELECT COUNT(*) FROM attendance')->fetchColumn(),
        'server_time' => $app->now(),
    ]);
} catch (Throwable $exception) {
    $app->error('대시보드 정보를 불러오는 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
