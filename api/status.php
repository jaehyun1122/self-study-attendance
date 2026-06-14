<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

$status = [
    'app_name' => $app->string('app_name'),
    'version' => $app->string('app_version'),
    'api_status' => $app->isRuntimeReady() && extension_loaded('pdo_sqlite') ? 'ok' : 'error',
    'installed' => $app->checkInstalled(),
    'timezone' => $app->string('timezone'),
    'server_time' => $app->now(),
    'php' => [
        'required' => $app->string('min_php_version', '8.5.0'),
        'current' => PHP_VERSION,
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
