<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');

    $input = $app->jsonInput();

    if ($app->checkInstalled()) {
        $app->error('이미 설치되어 있습니다.', 409);
    }

    $configuredInstallPassword = trim($app->string('initial_admin_password'));
    $installPassword = trim((string) ($input['install_password'] ?? ''));
    $password = trim((string) ($input['password'] ?? ''));
    $passwordRange = $app->lengthRange('password_length', 4, 32);
    $passwordLength = $app->textLength($password);

    if ($configuredInstallPassword === '') {
        $app->error('data/config.php의 initial_admin_password를 설정해주세요.', 500);
    }

    $app->requireFields($input, ['install_password', 'password']);
    $app->enforceAuthRateLimit('install-auth');

    if (!hash_equals($configuredInstallPassword, $installPassword)) {
        $app->recordAuthFailure('install-auth');
        $app->error('설치 승인 비밀번호가 올바르지 않습니다.', 403);
    }

    $app->clearAuthFailures('install-auth');
    if ($passwordLength < $passwordRange['min'] || $passwordLength > $passwordRange['max']) {
        $app->error($app->lengthRequirementText('관리자 비밀번호는', 'password_length', 4, 32), 400);
    }

    $schemaPath = $app->string('schema_path');
    $schema = is_readable($schemaPath) ? file_get_contents($schemaPath) : false;

    if (!is_string($schema) || trim($schema) === '') {
        $app->error('설치 스키마를 읽을 수 없습니다.', 500);
    }

    $pdo = $app->pdo();
    $pdo->exec('BEGIN IMMEDIATE');

    try {
        $pdo->exec($schema);

        if ((int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0) {
            $pdo->rollBack();
            $app->error('이미 설치되어 있습니다.', 409);
        }

        $statement = $pdo->prepare('INSERT INTO admin (password_hash, created_at, updated_at) VALUES (:password_hash, :created_at, :updated_at)');
        $statement->execute([
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':created_at' => $app->now(),
            ':updated_at' => $app->now(),
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    $app->success('설치가 완료되었습니다.');
} catch (Throwable $exception) {
    $app->failWithException('설치 중 오류가 발생했습니다.', $exception);
}
