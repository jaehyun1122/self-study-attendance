<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');

    $input = $app->jsonInput();

    if ($app->checkInstalled()) {
        $app->error('이미 설치되어 있습니다.', 409);
    }

    $configuredInstallPassword = trim($app->string('initial_admin_password'));
    $installPassword = trim((string) ($input['install_password'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    $passwordRange = $app->lengthRange('password_length', 4, 64);
    $passwordLength = $app->textLength($password);

    if ($configuredInstallPassword === '') {
        $app->error('data/config.php의 initial_admin_password를 설정해주세요.', 500);
    }

    $app->requireFields($input, ['install_password', 'password']);

    if (!hash_equals($configuredInstallPassword, $installPassword)) {
        $app->error('설치 승인 비밀번호가 올바르지 않습니다.', 403);
    }

    if ($passwordLength < $passwordRange['min'] || $passwordLength > $passwordRange['max']) {
        $app->error($app->lengthRequirementText('관리자 비밀번호는', 'password_length', 4, 64), 400);
    }

    $schemaPath = $app->string('schema_path');
    $schema = is_readable($schemaPath) ? file_get_contents($schemaPath) : false;

    if (!is_string($schema) || trim($schema) === '') {
        $app->error('스키마 파일을 읽을 수 없습니다.', 500, ['schema_path' => $schemaPath]);
    }

    $pdo = $app->pdo();
    $pdo->exec($schema);

    $statement = $pdo->prepare('INSERT INTO admin (password_hash, created_at, updated_at) VALUES (:password_hash, :created_at, :updated_at)');
    $statement->execute([
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':created_at' => $app->now(),
        ':updated_at' => $app->now(),
    ]);

    $app->success('설치가 완료되었습니다.');
} catch (Throwable $exception) {
    $app->error('설치 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
