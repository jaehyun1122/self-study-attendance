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

    $admin = $app->pdo()->query('SELECT id, password_hash FROM admin ORDER BY id ASC LIMIT 1')->fetch();

    if (!$admin || !password_verify((string) $input['password'], (string) $admin['password_hash'])) {
        $app->error('관리자 비밀번호가 올바르지 않습니다.', 401);
    }

    $token = bin2hex(random_bytes(32));
    $now = new DateTimeImmutable('now');
    $expiredAt = $now->add(new DateInterval('PT' . $app->int('token_expire_hours', 12) . 'H'));
    $format = 'Y-m-d H:i:s';

    $statement = $app->pdo()->prepare(
        'INSERT INTO admin_tokens (
            token, created_at, expired_at, last_seen_at, ip_address, user_agent
        ) VALUES (
            :token, :created_at, :expired_at, :last_seen_at, :ip_address, :user_agent
        )'
    );
    $statement->execute([
        ':token' => $app->hashToken($token),
        ':created_at' => $now->format($format),
        ':expired_at' => $expiredAt->format($format),
        ':last_seen_at' => $now->format($format),
        ':ip_address' => $app->clientIpAddress(),
        ':user_agent' => $app->clientUserAgent(),
    ]);

    $app->setAdminCookie($token, $expiredAt->getTimestamp());
    $app->success('로그인되었습니다.', ['token' => $token]);
} catch (Throwable $exception) {
    $app->error('로그인 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
