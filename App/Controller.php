<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

require_once __DIR__ . '/Database.php';

final class Controller
{
    public const STATUS_SUCCESS = 1;
    public const STATUS_ERROR = 2;
    private const SCHEMA_VERSION = 10903;

    /** @var array<string, mixed> */
    private array $config;

    private Database $database;

    private bool $schemaReady = false;

    private string $adminTokenFailureReason = 'login-required';

    public function __construct()
    {
        $config = require __DIR__ . '/../data/config.php';
        $this->config = is_array($config) ? $config : [];
        date_default_timezone_set($this->string('timezone', 'Asia/Seoul'));
        $this->database = new Database($this->config);
        $this->sendSecurityHeaders();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array{min: int, max: int}
     */
    public function lengthRange(string $key, int $defaultMin, ?int $defaultMax = null): array
    {
        $defaultMax ??= $defaultMin;
        $value = $this->get($key);

        if (is_array($value)) {
            $minValue = $value['min'] ?? $value[0] ?? $defaultMin;
            $maxValue = $value['max'] ?? $value[1] ?? $defaultMax;
        } elseif (is_numeric($value)) {
            $minValue = $value;
            $maxValue = $value;
        } else {
            $minValue = $defaultMin;
            $maxValue = $defaultMax;
        }

        $min = max(0, (int) $minValue);
        $max = max(0, (int) $maxValue);

        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return ['min' => $min, 'max' => $max];
    }

    public function lengthRequirementText(string $subject, string $key, int $defaultMin, ?int $defaultMax = null): string
    {
        $range = $this->lengthRange($key, $defaultMin, $defaultMax);

        if ($range['min'] === $range['max']) {
            return "{$subject} {$range['min']}자로 입력해주세요.";
        }

        if ($range['min'] < 1) {
            return "{$subject} {$range['max']}자까지 입력할 수 있습니다.";
        }

        return "{$subject} {$range['min']}자 이상 {$range['max']}자까지 입력할 수 있습니다.";
    }

    /**
     * @return array<int|string, mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        try {
            $this->migrateInstalledDatabase();
            $statement = $this->pdo()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key');
            $statement->execute([':setting_key' => $key]);
            $value = $statement->fetchColumn();

            if (!is_string($value)) {
                return $default;
            }

            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public function saveSetting(string $key, mixed $value): void
    {
        $this->migrateInstalledDatabase();
        $now = $this->now();
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new \RuntimeException('설정값을 저장할 수 없습니다.');
        }

        $statement = $this->pdo()->prepare(
            'INSERT OR REPLACE INTO app_settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, :updated_at)'
        );
        $statement->execute([
            ':setting_key' => $key,
            ':setting_value' => $encoded,
            ':updated_at' => $now,
        ]);
    }

    public function deleteSetting(string $key): void
    {
        $this->migrateInstalledDatabase();
        $statement = $this->pdo()->prepare('DELETE FROM app_settings WHERE setting_key = :setting_key');
        $statement->execute([':setting_key' => $key]);
    }

    /**
     * @return array{enabled: bool, latitude: ?float, longitude: ?float, radius_meters: ?int, timeout_seconds: ?int, configured: bool}
     */
    public function locationSettings(): array
    {
        $defaults = $this->array('attendance_location', []);
        $saved = $this->setting('attendance_location', []);
        $settings = array_merge($defaults, is_array($saved) ? $saved : []);
        $latitude = is_numeric($settings['latitude'] ?? null) ? (float) $settings['latitude'] : null;
        $longitude = is_numeric($settings['longitude'] ?? null) ? (float) $settings['longitude'] : null;
        $radiusMeters = is_numeric($settings['radius_meters'] ?? null)
            ? min(5000, max(10, (int) $settings['radius_meters']))
            : null;
        $timeoutSeconds = is_numeric($settings['timeout_seconds'] ?? null)
            ? min(60, max(3, (int) $settings['timeout_seconds']))
            : null;
        $enabled = filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOL);

        return [
            'enabled' => $enabled,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'timeout_seconds' => $timeoutSeconds,
            'configured' => $enabled && $latitude !== null && $longitude !== null && $radiusMeters !== null,
        ];
    }

    /**
     * @return array{enabled: bool, configured: bool, timeout_seconds: ?int}
     */
    public function publicLocationStatus(): array
    {
        $settings = $this->locationSettings();

        return [
            'enabled' => $settings['enabled'],
            'configured' => $settings['configured'],
            'timeout_seconds' => $settings['timeout_seconds'],
        ];
    }

    /**
     * @return array{enabled: bool, latitude: ?float, longitude: ?float, radius_meters: ?int, timeout_seconds: ?int}
     */
    public function normalizedLocationSettingPayload(array $settings): array
    {
        $latitude = is_numeric($settings['latitude'] ?? null) ? (float) $settings['latitude'] : null;
        $longitude = is_numeric($settings['longitude'] ?? null) ? (float) $settings['longitude'] : null;
        $radiusMeters = is_numeric($settings['radius_meters'] ?? null)
            ? min(5000, max(10, (int) $settings['radius_meters']))
            : null;
        $timeoutSeconds = is_numeric($settings['timeout_seconds'] ?? null)
            ? min(60, max(3, (int) $settings['timeout_seconds']))
            : null;

        return [
            'enabled' => filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'timeout_seconds' => $timeoutSeconds,
        ];
    }

    public function isDefaultLocationSettingPayload(array $settings): bool
    {
        return $this->normalizedLocationSettingPayload($settings)
            === $this->normalizedLocationSettingPayload($this->array('attendance_location', []));
    }

    public function distanceMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusMeters = 6371000;
        $fromLatRad = deg2rad($fromLatitude);
        $toLatRad = deg2rad($toLatitude);
        $deltaLat = deg2rad($toLatitude - $fromLatitude);
        $deltaLng = deg2rad($toLongitude - $fromLongitude);
        $a = sin($deltaLat / 2) ** 2
            + cos($fromLatRad) * cos($toLatRad) * sin($deltaLng / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function pdo(): PDO
    {
        return $this->database->pdo();
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function formatDateTime(mixed $value): string
    {
        $dateTime = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTime)) {
            return $dateTime;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $dateTime)) {
            return $dateTime . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTime)) {
            return $dateTime . ' 00:00:00';
        }

        $timestamp = strtotime($dateTime);

        return $timestamp === false ? $this->now() : date('Y-m-d H:i:s', $timestamp);
    }

    public function textLength(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        if (function_exists('grapheme_strlen')) {
            $length = grapheme_strlen($value);

            if ($length !== false) {
                return $length;
            }
        }

        $graphemeCount = preg_match_all('/\X/u', $value, $matches);

        if ($graphemeCount !== false) {
            return $graphemeCount;
        }

        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{student_no: string, name: string}
     */
    public function validatedStudentInput(array $input): array
    {
        $this->requireFields($input, ['student_no', 'name']);

        $studentNo = trim((string) ($input['student_no'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $studentNoRange = $this->lengthRange('student_no_length', 5, 5);
        $studentNoLength = strlen($studentNo);

        if (!preg_match('/^\d+$/', $studentNo)) {
            $this->error('학번은 숫자만 입력해주세요.', 400);
        }

        if ($studentNoLength < $studentNoRange['min'] || $studentNoLength > $studentNoRange['max']) {
            $this->error($this->lengthRequirementText('학번은', 'student_no_length', 5, 5), 400);
        }

        if ($name === '') {
            $this->error('학번과 이름을 입력해주세요.', 400);
        }

        $studentNameRange = $this->lengthRange('student_name_length', 1, 10);
        $studentNameLength = $this->textLength($name);

        if ($studentNameLength < $studentNameRange['min'] || $studentNameLength > $studentNameRange['max']) {
            $this->error($this->lengthRequirementText('이름은', 'student_name_length', 1, 10), 400);
        }

        return [
            'student_no' => $studentNo,
            'name' => $name,
        ];
    }

    public function today(): string
    {
        return date('Y-m-d');
    }

    public function success(string $message = '성공적으로 처리되었습니다.', mixed $result = null, int $httpCode = 200): never
    {
        $this->response(self::STATUS_SUCCESS, $message, $result, $httpCode);
    }

    public function error(string $message = '오류가 발생하였습니다.', int $httpCode = 400, mixed $result = null): never
    {
        $this->response(self::STATUS_ERROR, $message, $result, $httpCode);
    }

    public function response(int $status, string $message, mixed $result = null, int $httpCode = 200): never
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $payload = [
            'status' => $status,
            'msg' => $message,
        ];

        if ($result !== null) {
            $payload['result'] = $result;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonInput(): array
    {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;

        if (is_numeric($contentLength) && (int) $contentLength > 65536) {
            $this->error('요청 본문이 너무 큽니다.', 413);
        }

        $raw = (string) file_get_contents('php://input');

        if (strlen($raw) > 65536) {
            $this->error('요청 본문이 너무 큽니다.', 413);
        }

        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));

        if ($contentType !== 'application/json') {
            $this->error('JSON Content-Type 요청만 허용됩니다.', 415);
        }

        $input = json_decode($raw, true);

        if (!is_array($input)) {
            $this->error('JSON 요청 형식이 올바르지 않습니다.', 400);
        }

        return $input;
    }

    public function requireMethod(string $method): void
    {
        $expectedMethod = strtoupper($method);
        $actualMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($actualMethod !== $expectedMethod) {
            $this->error('허용되지 않은 요청 방식입니다.', 405);
        }

        if ($expectedMethod !== 'GET' && strtolower($this->serverHeaderValue('Sec-Fetch-Site')) === 'cross-site') {
            $this->error('교차 사이트 요청은 허용하지 않습니다.', 403);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $fields
     */
    public function requireFields(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $input[$field] ?? null;

            if ((!is_string($value) && !is_numeric($value)) || trim((string) $value) === '') {
                $this->error('필수 입력값이 누락되었습니다: ' . $field, 400);
            }
        }
    }

    public function checkInstalled(): bool
    {
        try {
            if (!is_file($this->string('database_path'))) {
                return false;
            }

            $tables = ['attendance', 'admin', 'admin_tokens'];
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $statement = $this->pdo()->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ($placeholders)");
            $statement->execute(array_values($tables));

            if ((int) $statement->fetchColumn() !== count($tables)) {
                return false;
            }

            $this->migrateInstalledDatabase();

            return (int) $this->pdo()->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function requireInstalled(): void
    {
        if (!$this->checkInstalled()) {
            $this->error('설치가 필요합니다.', 503, ['installed' => false]);
        }
    }

    public function checkAdminToken(string $token, bool $refresh = true): bool
    {
        if ($token === '') {
            return false;
        }

        $this->migrateInstalledDatabase();
        $nowDateTime = new \DateTimeImmutable('now');
        $now = $nowDateTime->format('Y-m-d H:i:s');
        $statement = $this->pdo()->prepare(
            'SELECT id, expired_at FROM admin_tokens WHERE token = :token'
        );
        $statement->execute([
            ':token' => $this->hashToken($token),
        ]);
        $session = $statement->fetch();

        if (!is_array($session) || !is_numeric($session['id'] ?? null)) {
            $this->adminTokenFailureReason = 'session-revoked';
            return false;
        }

        $expiresAt = $this->dateTimeOrNull($session['expired_at'] ?? null);

        if ($expiresAt === null || $expiresAt <= $nowDateTime) {
            try {
                $delete = $this->pdo()->prepare('DELETE FROM admin_tokens WHERE id = :id');
                $delete->execute([':id' => (int) $session['id']]);
            } catch (Throwable) {
                // 만료 판정은 이미 끝났으므로 잠금 중 정리 실패가 응답을 막지 않게 합니다.
            }

            $this->adminTokenFailureReason = 'session-expired';
            return false;
        }

        if ($refresh) {
            $sessionHours = max(1, $this->int('token_expire_hours', 12));
            $newExpiresAt = $nowDateTime->modify("+{$sessionHours} hours");

            try {
                $touch = $this->pdo()->prepare(
                    'UPDATE admin_tokens
                     SET last_seen_at = :last_seen_at, expired_at = :expired_at
                     WHERE id = :id'
                );
                $touch->execute([
                    ':last_seen_at' => $now,
                    ':expired_at' => $newExpiresAt->format('Y-m-d H:i:s'),
                    ':id' => (int) $session['id'],
                ]);
                $this->setAdminCookie($token, $newExpiresAt->getTimestamp());
            } catch (Throwable $exception) {
                error_log('Admin session refresh skipped: ' . $exception->getMessage());
            }
        }

        $this->adminTokenFailureReason = '';

        return true;
    }

    public function requireAdminApi(): string
    {
        $this->requireInstalled();
        $cookieToken = $_COOKIE['admin_token'] ?? null;

        if (is_string($cookieToken) && $this->checkAdminToken($cookieToken, false)) {
            return $cookieToken;
        }

        $this->error('로그인이 필요합니다.', 401, [
            'reason' => $this->adminTokenFailureReason,
        ]);
    }

    public function requireAdminPage(): string
    {
        header('Cache-Control: no-store, private');
        $cookieName = 'admin_token';
        $token = $_COOKIE[$cookieName] ?? null;

        if (!is_string($token) || $token === '') {
            $this->redirect('/admin/?reason=login-required');
        }

        if (!$this->checkAdminToken($token)) {
            $this->redirect('/admin/?reason=' . rawurlencode($this->adminTokenFailureReason ?: 'session-expired'));
        }

        return $token;
    }

    public function adminTokenFailureReason(): string
    {
        return $this->adminTokenFailureReason ?: 'session-expired';
    }

    public function setAdminCookie(string $token, int $expires): void
    {
        setcookie('admin_token', $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function clearAdminCookie(): void
    {
        setcookie('admin_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function deleteAdminToken(string $token): void
    {
        $statement = $this->pdo()->prepare('DELETE FROM admin_tokens WHERE token = :token');
        $statement->execute([':token' => $this->hashToken($token)]);
    }

    public function verifyAdminPassword(mixed $password, int $httpCode = 403): void
    {
        $this->enforceAuthRateLimit('admin-sensitive');
        $admin = $this->pdo()->query('SELECT password_hash FROM admin ORDER BY id ASC LIMIT 1')->fetch();

        if (!$admin || !password_verify((string) $password, (string) $admin['password_hash'])) {
            $this->recordAuthFailure('admin-sensitive');
            $this->error('관리자 비밀번호가 올바르지 않습니다.', $httpCode);
        }

        $this->clearAuthFailures('admin-sensitive');
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function enforceAuthRateLimit(string $scope): void
    {
        $this->migrateInstalledDatabase();
        $identifier = hash('sha256', $this->clientIpAddress());
        $now = new \DateTimeImmutable('now');
        $cleanup = $this->pdo()->prepare('DELETE FROM auth_rate_limits WHERE updated_at < :cutoff');
        $cleanup->execute([':cutoff' => $now->modify('-7 days')->format('Y-m-d H:i:s')]);
        $statement = $this->pdo()->prepare(
            'SELECT attempts, window_started_at, blocked_until
             FROM auth_rate_limits
             WHERE scope = :scope AND identifier = :identifier'
        );
        $statement->execute([
            ':scope' => $scope,
            ':identifier' => $identifier,
        ]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return;
        }

        $blockedUntil = $this->dateTimeOrNull($row['blocked_until'] ?? null);

        if ($blockedUntil !== null && $blockedUntil > $now) {
            $retryAfter = max(1, $blockedUntil->getTimestamp() - $now->getTimestamp());
            header('Retry-After: ' . $retryAfter);
            $this->error('요청이 너무 많습니다. 잠시 후 다시 시도해주세요.', 429);
        }

        $windowStartedAt = $this->dateTimeOrNull($row['window_started_at'] ?? null);
        $windowSeconds = max(60, $this->int('auth_rate_limit.window_seconds', 300));

        if ($windowStartedAt === null || $windowStartedAt->modify("+{$windowSeconds} seconds") <= $now) {
            $this->clearAuthFailures($scope);
        }
    }

    public function recordAuthFailure(string $scope): void
    {
        $this->migrateInstalledDatabase();
        $identifier = hash('sha256', $this->clientIpAddress());
        $now = new \DateTimeImmutable('now');
        $nowText = $now->format('Y-m-d H:i:s');
        $maxAttempts = max(3, $this->int('auth_rate_limit.max_attempts', 10));
        $windowSeconds = max(60, $this->int('auth_rate_limit.window_seconds', 300));
        $blockSeconds = max(60, $this->int('auth_rate_limit.block_seconds', 300));
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $select = $pdo->prepare(
                'SELECT attempts, window_started_at
                 FROM auth_rate_limits
                 WHERE scope = :scope AND identifier = :identifier'
            );
            $select->execute([
                ':scope' => $scope,
                ':identifier' => $identifier,
            ]);
            $row = $select->fetch();
            $windowStartedAt = is_array($row)
                ? $this->dateTimeOrNull($row['window_started_at'] ?? null)
                : null;
            $attempts = is_array($row) ? (int) ($row['attempts'] ?? 0) : 0;

            if ($windowStartedAt === null || $windowStartedAt->modify("+{$windowSeconds} seconds") <= $now) {
                $windowStartedAt = $now;
                $attempts = 0;
            }

            $attempts++;
            $blockedUntil = $attempts >= $maxAttempts
                ? $now->modify("+{$blockSeconds} seconds")
                : null;
            $upsert = $pdo->prepare(
                'INSERT INTO auth_rate_limits (
                    scope, identifier, attempts, window_started_at, blocked_until, updated_at
                 ) VALUES (
                    :scope, :identifier, :attempts, :window_started_at, :blocked_until, :updated_at
                 )
                 ON CONFLICT(scope, identifier) DO UPDATE SET
                    attempts = excluded.attempts,
                    window_started_at = excluded.window_started_at,
                    blocked_until = excluded.blocked_until,
                    updated_at = excluded.updated_at'
            );
            $upsert->execute([
                ':scope' => $scope,
                ':identifier' => $identifier,
                ':attempts' => $attempts,
                ':window_started_at' => $windowStartedAt->format('Y-m-d H:i:s'),
                ':blocked_until' => $blockedUntil?->format('Y-m-d H:i:s'),
                ':updated_at' => $nowText,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        if ($blockedUntil !== null) {
            header('Retry-After: ' . $blockSeconds);
            $this->error('요청이 너무 많습니다. 잠시 후 다시 시도해주세요.', 429);
        }
    }

    public function clearAuthFailures(string $scope): void
    {
        $this->migrateInstalledDatabase();
        $statement = $this->pdo()->prepare(
            'DELETE FROM auth_rate_limits WHERE scope = :scope AND identifier = :identifier'
        );
        $statement->execute([
            ':scope' => $scope,
            ':identifier' => hash('sha256', $this->clientIpAddress()),
        ]);
    }

    public function failWithException(string $message, Throwable $exception): never
    {
        try {
            $errorId = bin2hex(random_bytes(8));
        } catch (Throwable) {
            $errorId = str_replace('.', '', uniqid('', true));
        }

        error_log(sprintf(
            '[%s] %s: %s in %s:%d',
            $errorId,
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
        $this->error($message, 500, ['error_id' => $errorId]);
    }

    public function clientIpAddress(): string
    {
        $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false ? $remoteAddress : 'unknown';
    }

    public function clientUserAgent(): string
    {
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '알 수 없음'));

        if ($userAgent === '') {
            return '알 수 없음';
        }

        return function_exists('mb_substr')
            ? mb_substr($userAgent, 0, 500, 'UTF-8')
            : substr($userAgent, 0, 500);
    }

    public function asset(string $path): string
    {
        $assetPath = '/' . ltrim($path, '/');
        $separator = str_contains($assetPath, '?') ? '&' : '?';

        return $assetPath . $separator . 'v=' . rawurlencode($this->string('app_version'));
    }

    public function redirect(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderAdmin(string $template, array $data = []): never
    {
        $content = $this->renderTemplate($template, $data);
        echo $this->renderTemplate('admin/layout.php', array_merge($data, [
            'content' => $content,
            'menu' => [
                ['key' => 'dash', 'label' => '대시보드', 'url' => '/admin/dash.php'],
                ['key' => 'list', 'label' => '출석 목록', 'url' => '/admin/list.php'],
                ['key' => 'location', 'label' => '위치 설정', 'url' => '/admin/location.php'],
                ['key' => 'system', 'label' => '시스템 관리', 'url' => '/admin/system.php'],
                ['key' => 'password', 'label' => '비밀번호 변경', 'url' => '/admin/password.php'],
            ],
        ]));
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        $path = __DIR__ . '/../templates/' . ltrim($template, '/\\');

        if (!is_file($path)) {
            throw new \RuntimeException('템플릿 파일을 찾을 수 없습니다: ' . $template);
        }

        $app = $this;
        $h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $asset = fn (string $path): string => $this->asset($path);
        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    private function migrateInstalledDatabase(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $pdo = $this->pdo();
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        if ($version >= self::SCHEMA_VERSION) {
            $this->schemaReady = true;
            return;
        }

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

            if ($version >= self::SCHEMA_VERSION) {
                $pdo->commit();
                $this->schemaReady = true;
                return;
            }

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS auth_rate_limits (
                    scope TEXT NOT NULL,
                    identifier TEXT NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    window_started_at TEXT NOT NULL,
                    blocked_until TEXT,
                    updated_at TEXT NOT NULL,
                    PRIMARY KEY (scope, identifier)
                )"
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_rate_limits_updated_at ON auth_rate_limits(updated_at)');

            $hasAdminTokens = ((int) $pdo
                ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'admin_tokens'")
                ->fetchColumn()) > 0;

            if ($hasAdminTokens) {
                $tokenColumns = [];
                foreach ($pdo->query('PRAGMA table_info(admin_tokens)')->fetchAll() as $column) {
                    if (isset($column['name'])) {
                        $tokenColumns[(string) $column['name']] = true;
                    }
                }

                $tokenColumnDefinitions = [
                    'last_seen_at' => 'last_seen_at TEXT',
                    'ip_address' => 'ip_address TEXT',
                    'user_agent' => 'user_agent TEXT',
                ];

                foreach ($tokenColumnDefinitions as $name => $definition) {
                    if (!isset($tokenColumns[$name])) {
                        $pdo->exec("ALTER TABLE admin_tokens ADD COLUMN {$definition}");
                    }
                }

                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_tokens_expired_at ON admin_tokens(expired_at)');
            }

            $hasAttendance = ((int) $pdo
                ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'attendance'")
                ->fetchColumn()) > 0;

            if ($hasAttendance) {
                $columns = [];
                foreach ($pdo->query('PRAGMA table_info(attendance)')->fetchAll() as $column) {
                    if (isset($column['name'])) {
                        $columns[(string) $column['name']] = true;
                    }
                }

                $addColumn = static function (string $name, string $definition) use ($pdo, &$columns): void {
                    if (isset($columns[$name])) {
                        return;
                    }

                    $pdo->exec("ALTER TABLE attendance ADD COLUMN {$definition}");
                    $columns[$name] = true;
                };

                $addColumn('location_status', "location_status TEXT NOT NULL DEFAULT 'unchecked'");
                $addColumn('location_latitude', 'location_latitude REAL');
                $addColumn('location_longitude', 'location_longitude REAL');
                $addColumn('location_accuracy', 'location_accuracy REAL');
                $addColumn('location_distance_meters', 'location_distance_meters REAL');
                $addColumn('location_message', 'location_message TEXT');
                $addColumn('location_checked_at', 'location_checked_at TEXT');
                $addColumn('location_approved_at', 'location_approved_at TEXT');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_attend_date ON attendance(attend_date)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_location_status ON attendance(location_status)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_created_at ON attendance(created_at)');
            }

            $pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            $pdo->commit();
            $this->schemaReady = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    }

    private function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(self)');
    }

    private function serverHeaderValue(string $headerName): string
    {
        $key = strtoupper(str_replace('-', '_', trim($headerName)));
        $serverKey = str_starts_with($key, 'HTTP_') ? $key : 'HTTP_' . $key;
        $value = trim((string) ($_SERVER[$serverKey] ?? ''));

        if ($value !== '' || !function_exists('getallheaders')) {
            return $value;
        }

        $headers = getallheaders();

        if (!is_array($headers)) {
            return '';
        }

        foreach ($headers as $name => $headerValue) {
            if (strcasecmp((string) $name, $headerName) === 0) {
                return trim((string) $headerValue);
            }
        }

        return '';
    }

    private function dateTimeOrNull(mixed $value): ?\DateTimeImmutable
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($text);
        } catch (Throwable) {
            return null;
        }
    }
}
