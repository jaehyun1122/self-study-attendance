<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireInstalled();

    $input = $app->jsonInput();
    $app->requireFields($input, ['password']);

    $admin = $app->pdo()->query('SELECT password_hash FROM admin ORDER BY id ASC LIMIT 1')->fetch();

    if (!$admin || !password_verify((string) $input['password'], (string) $admin['password_hash'])) {
        $app->error('관리자 비밀번호가 올바르지 않습니다.', 401);
    }

    $app->success('관리자 비밀번호가 확인되었습니다.');
} catch (Throwable $exception) {
    $app->error('관리자 비밀번호 확인 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
