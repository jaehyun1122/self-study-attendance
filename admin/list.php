<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$adminToken = $app->requireAdminPage();

$app->renderAdmin('admin/list.php', [
    'title' => '출석 목록',
    'active' => 'list',
    'adminToken' => $adminToken,
]);
