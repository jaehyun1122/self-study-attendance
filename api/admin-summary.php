<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $countedStatuses = "('verified', 'approved', 'unchecked')";
    $todayStatement = $app->pdo()->prepare(
        "SELECT COUNT(*) FROM attendance WHERE attend_date = :attend_date AND location_status IN {$countedStatuses}"
    );
    $todayStatement->execute([':attend_date' => $app->today()]);
    $totalStatement = $app->pdo()->query("SELECT COUNT(*) FROM attendance WHERE location_status IN {$countedStatuses}");
    $pendingStatement = $app->pdo()->query("SELECT COUNT(*) FROM attendance WHERE location_status = 'pending'");

    $app->success('성공적으로 처리되었습니다.', [
        'today' => (int) $todayStatement->fetchColumn(),
        'total' => (int) $totalStatement->fetchColumn(),
        'pending' => (int) $pendingStatement->fetchColumn(),
        'location' => $app->locationSettings(),
        'server_time' => $app->now(),
        'server_time_sync_interval_seconds' => $app->int('server_time_sync_interval_seconds', 5),
    ]);
} catch (Throwable $exception) {
    $app->error('대시보드 정보를 불러오는 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
