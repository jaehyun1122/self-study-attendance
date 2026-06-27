<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');

    $token = $app->requireAdminApi();
    $app->deleteAdminToken($token);
    $app->clearAdminCookie();
    $app->success('로그아웃되었습니다.');
} catch (Throwable $exception) {
    $app->failWithException('로그아웃 중 오류가 발생했습니다.', $exception);
}
