<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$adminToken = $app->requireAdminPage();

$app->renderAdmin('admin/password.php', [
    'title' => '비밀번호 변경',
    'active' => 'password',
    'adminToken' => $adminToken,
]);
