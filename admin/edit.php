<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$app->requireAdminPage();

$app->renderAdmin('admin/edit.php', [
    'title' => '출석 기록 수정',
    'active' => 'list',
]);
