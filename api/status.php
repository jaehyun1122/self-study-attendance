<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

$installed = $app->checkInstalled();

$status = [
    'app_name' => $app->string('app_name'),
    'version' => $app->string('app_version'),
    'repository_url' => $app->string('repository_url'),
    'powered_by' => $app->string('powered_by'),
    'installed' => $installed,
    'timezone' => $app->string('timezone'),
    'server_time' => $app->now(),
    'server_time_sync_interval_seconds' => $app->int('server_time_sync_interval_seconds', 5),
    'location' => $installed ? $app->locationSettings() : [
        'enabled' => false,
        'latitude' => null,
        'longitude' => null,
        'radius_meters' => null,
        'timeout_seconds' => null,
        'configured' => false,
    ],
];

$app->success('성공적으로 처리되었습니다.', $status);
