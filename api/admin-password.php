<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $input = $app->jsonInput();
    $app->requireFields($input, ['old_password', 'new_password']);

    $newPassword = trim((string) $input['new_password']);
    $passwordRange = $app->lengthRange('password_length', 4, 64);
    $passwordLength = $app->textLength($newPassword);

    if ($passwordLength < $passwordRange['min'] || $passwordLength > $passwordRange['max']) {
        $app->error($app->lengthRequirementText('새 비밀번호는', 'password_length', 4, 64), 400);
    }

    $admin = $app->pdo()->query('SELECT id, password_hash FROM admin ORDER BY id ASC LIMIT 1')->fetch();

    if (!$admin || !password_verify((string) $input['old_password'], (string) $admin['password_hash'])) {
        $app->error('기존 비밀번호가 올바르지 않습니다.', 400);
    }

    $statement = $app->pdo()->prepare('UPDATE admin SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
    $statement->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':updated_at' => $app->now(),
        ':id' => (int) $admin['id'],
    ]);

    $app->pdo()->exec('DELETE FROM admin_tokens');
    $app->clearAdminCookie();
    $app->success('비밀번호가 변경되었습니다. 다시 로그인해주세요.');
} catch (Throwable $exception) {
    $app->error('비밀번호 변경 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
