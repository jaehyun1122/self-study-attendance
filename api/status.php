<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

$status = [
    'app_name' => $app->string('app_name'),
    'version' => $app->string('app_version'),
    'repository_url' => $app->string('repository_url'),
    'powered_by' => $app->string('powered_by'),
    'api_status' => $app->isRuntimeReady() && extension_loaded('pdo_sqlite') ? 'ok' : 'error',
    'installed' => $app->checkInstalled(),
    'timezone' => $app->string('timezone'),
    'server_time' => $app->now(),
    'server_time_sync_interval_seconds' => $app->int('server_time_sync_interval_seconds', 5),
    'php' => [
        'required' => $app->string('min_php_version', 'v8.5.0'),
        'current' => "v" . PHP_VERSION,
        'ok' => $app->isRuntimeReady(),
    ],
    'extensions' => [
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    ],
];

if (!$app->isRuntimeReady()) {
    $app->error('PHP 8.5 이상이 필요합니다.', 500, $status);
}

$app->success('성공적으로 처리되었습니다.', $status);
