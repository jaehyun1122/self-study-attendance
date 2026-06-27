<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$app->requireMethod('GET');

$installed = $app->checkInstalled();
$location = $installed ? $app->publicLocationStatus() : [
    'enabled' => false,
    'configured' => false,
    'timeout_seconds' => null,
];

$status = [
    'installed' => $installed,
    'server_time' => $app->now(),
    'server_time_sync_interval_seconds' => $app->int('server_time_sync_interval_seconds', 5),
    'location' => $location,
];

$app->success('성공적으로 처리되었습니다.', $status);
