<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');
    $app->requireInstalled();

    $input = $app->jsonInput();
    $app->requireFields($input, ['password']);
    $app->verifyAdminPassword($input['password'], 403);
    $app->success('관리자 비밀번호가 확인되었습니다.');
} catch (Throwable $exception) {
    $app->failWithException('관리자 비밀번호 확인 중 오류가 발생했습니다.', $exception);
}
