<?php

declare(strict_types=1);

use App\Controller;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

if (!$app->checkInstalled()) {
    fwrite(STDERR, "설치된 관리자 계정을 찾을 수 없습니다.\n");
    exit(1);
}

$range = $app->lengthRange('password_length', 4, 32);
$password = promptPassword('새 관리자 비밀번호: ');
$confirmation = promptPassword('새 관리자 비밀번호 확인: ');
$length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);

if ($password === '') {
    fwrite(STDERR, "비밀번호를 입력해주세요.\n");
    exit(1);
}

if ($length < $range['min'] || $length > $range['max']) {
    fwrite(STDERR, $app->lengthRequirementText('새 비밀번호는', 'password_length', 4, 32) . "\n");
    exit(1);
}

if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "비밀번호 확인이 일치하지 않습니다.\n");
    exit(1);
}

$pdo = $app->pdo();

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'UPDATE admin
         SET password_hash = :password_hash, updated_at = :updated_at
         WHERE id = (SELECT id FROM admin ORDER BY id ASC LIMIT 1)'
    );
    $statement->execute([
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':updated_at' => $app->now(),
    ]);
    $pdo->exec('DELETE FROM admin_tokens');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "비밀번호 초기화 중 오류가 발생했습니다: {$exception->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "관리자 비밀번호를 변경하고 기존 로그인 세션을 모두 종료했습니다.\n");

function promptPassword(string $prompt): string
{
    if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
        fwrite(STDOUT, $prompt);
        shell_exec('stty -echo');

        try {
            $value = fgets(STDIN);
        } finally {
            shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }

        return rtrim(is_string($value) ? $value : '', "\r\n");
    }

    if (function_exists('readline')) {
        $value = readline($prompt);
    } else {
        fwrite(STDOUT, $prompt);
        $value = fgets(STDIN);
    }

    return rtrim(is_string($value) ? $value : '', "\r\n");
}
