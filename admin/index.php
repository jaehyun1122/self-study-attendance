<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();
$token = $_COOKIE['admin_token'] ?? null;
$reason = trim((string) ($_GET['reason'] ?? ''));

if (is_string($token) && $token !== '') {
    if ($app->checkAdminToken($token)) {
        $app->redirect('/admin/dash.php');
    }

    $app->clearAdminCookie();

    if ($reason === '') {
        $app->redirect('/admin/?reason=' . rawurlencode($app->adminTokenFailureReason()));
    }
}

echo $app->renderTemplate('admin/login.php');
