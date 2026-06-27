<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $currentToken = $app->requireAdminApi();
    $currentTokenHash = $app->hashToken($currentToken);
    $input = $app->jsonInput();
    $type = (string) ($input['type'] ?? 'list');
    $now = $app->now();

    $app->pdo()->prepare('DELETE FROM admin_tokens WHERE expired_at < :now')->execute([':now' => $now]);

    if ($type === 'list') {
        $statement = $app->pdo()->prepare(
            'SELECT id, token, created_at, expired_at,
                    COALESCE(last_seen_at, created_at) AS last_seen_at,
                    COALESCE(ip_address, :unknown_ip) AS ip_address,
                    COALESCE(user_agent, :unknown_agent) AS user_agent
             FROM admin_tokens
             WHERE expired_at >= :now
             ORDER BY COALESCE(last_seen_at, created_at) DESC, id DESC'
        );
        $statement->execute([
            ':unknown_ip' => '알 수 없음',
            ':unknown_agent' => '알 수 없음',
            ':now' => $now,
        ]);

        $sessions = array_map(static function (array $session) use ($currentTokenHash): array {
            $isCurrent = hash_equals($currentTokenHash, (string) $session['token']);
            unset($session['token']);
            $session['id'] = (int) $session['id'];
            $session['is_current'] = $isCurrent;

            return $session;
        }, $statement->fetchAll());

        $app->success('로그인 세션을 불러왔습니다.', [
            'sessions' => $sessions,
            'count' => count($sessions),
        ]);
    }

    if ($type === 'revoke') {
        $sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($sessionId === false) {
            $app->error('종료할 세션을 선택해주세요.', 400);
        }

        $find = $app->pdo()->prepare('SELECT token FROM admin_tokens WHERE id = :id');
        $find->execute([':id' => $sessionId]);
        $targetTokenHash = $find->fetchColumn();

        if (!is_string($targetTokenHash)) {
            $app->error('이미 종료되었거나 존재하지 않는 세션입니다.', 404);
        }

        $delete = $app->pdo()->prepare('DELETE FROM admin_tokens WHERE id = :id');
        $delete->execute([':id' => $sessionId]);
        $isCurrent = hash_equals($currentTokenHash, $targetTokenHash);

        if ($isCurrent) {
            $app->clearAdminCookie();
        }

        $app->success('선택한 세션을 강제 로그아웃했습니다.', [
            'revoked_id' => (int) $sessionId,
            'revoked_current' => $isCurrent,
        ]);
    }

    if ($type === 'revoke_others') {
        $delete = $app->pdo()->prepare('DELETE FROM admin_tokens WHERE token <> :current_token');
        $delete->execute([':current_token' => $currentTokenHash]);

        $app->success('현재 기기를 제외한 모든 세션을 강제 로그아웃했습니다.', [
            'revoked_count' => $delete->rowCount(),
        ]);
    }

    $app->error('지원하지 않는 세션 요청입니다.', 400);
} catch (Throwable $exception) {
    $app->error('로그인 세션 처리 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
