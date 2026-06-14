<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$token = $_COOKIE['admin_token'] ?? null;

if (is_string($token) && $app->checkAdminToken($token)) {
    $app->redirect('/admin/dash.php');
}

echo $app->renderTemplate('admin/login.php');
