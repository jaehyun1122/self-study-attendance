<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$app->requireAdminPage();

$app->renderAdmin('admin/location.php', [
    'title' => '위치 설정',
    'active' => 'location',
]);
