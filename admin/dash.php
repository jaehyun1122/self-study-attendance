<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$app->requireAdminPage();

$app->renderAdmin('admin/dash.php', [
    'title' => '관리자 대시보드',
    'active' => 'dash',
]);
