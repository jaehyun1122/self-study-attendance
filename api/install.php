<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');

    $input = $app->jsonInput();
    $app->requireFields($input, ['password']);

    $password = trim((string) $input['password']);
    $passwordRange = $app->lengthRange('password_length', 4, 64);
    $passwordLength = $app->textLength($password);

    if ($passwordLength < $passwordRange['min'] || $passwordLength > $passwordRange['max']) {
        $app->error($app->lengthRequirementText('관리자 비밀번호는', 'password_length', 4, 64), 400);
    }

    if ($app->checkInstalled()) {
        $app->error('이미 설치되어 있습니다.', 409);
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
