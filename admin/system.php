<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$app->requireAdminPage();

$app->renderAdmin('admin/system.php', [
    'title' => '시스템 관리',
    'active' => 'system',
]);
