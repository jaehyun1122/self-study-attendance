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

    /** @var array<string, mixed> */
    private array $config;

    private Database $database;

    public function __construct()
    {
        $config = require __DIR__ . '/../data/config.php';
        $this->config = is_array($config) ? $config : [];
        date_default_timezone_set($this->string('timezone', 'Asia/Seoul'));
        $this->database = new Database($this->config);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
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

        return preg_match_all('/./us', $value, $matches) ?: strlen($value);
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

        echo json_encode([
            'status' => $status,
            'msg' => $message,
            'time' => $this->now(),
            'result' => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonInput(): array
    {
        $raw = trim((string) file_get_contents('php://input'));

        if ($raw === '') {
            return [];
        }

        $input = json_decode($raw, true);

        if (!is_array($input)) {
            $this->error('JSON 요청 형식이 올바르지 않습니다.', 400);
        }

        return $input;
    }

    public function requireMethod(string $method): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper($method)) {
            $this->error('허용되지 않은 요청 방식입니다.', 405);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $fields
     */
    public function requireFields(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
                $this->error('필수 입력값이 누락되었습니다: ' . $field, 400);
            }
        }
    }

    public function isRuntimeReady(): bool
    {
        return version_compare(PHP_VERSION, $this->string('min_php_version', '8.5.0'), '>=');
    }

    public function assertRuntimeForApi(): void
    {
        if ($this->isRuntimeReady()) {
            return;
        }

        $this->error('PHP 8.5 이상이 필요합니다.', 500, [
            'required_php_version' => $this->string('min_php_version', '8.5.0'),
            'current_php_version' => PHP_VERSION,
        ]);
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

    public function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        if ($header === null && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!is_string($header) || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    public function checkAdminToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $now = $this->now();
            $this->pdo()->prepare('DELETE FROM admin_tokens WHERE expired_at < :now')->execute([':now' => $now]);
            $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM admin_tokens WHERE token = :token AND expired_at >= :now');
            $statement->execute([
                ':token' => $this->hashToken($token),
                ':now' => $now,
            ]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function requireAdminApi(): string
    {
        $this->requireInstalled();
        $cookieToken = $_COOKIE['admin_token'] ?? null;
        $tokens = [];
        $bearerToken = $this->getBearerToken();

        if ($bearerToken !== null) {
            $tokens[] = $bearerToken;
        }

        if (is_string($cookieToken) && !in_array($cookieToken, $tokens, true)) {
            $tokens[] = $cookieToken;
        }

        foreach ($tokens as $token) {
            if ($this->checkAdminToken($token)) {
                return $token;
            }
        }

        $this->error('로그인이 필요합니다.', 401);
    }

    public function requireAdminPage(): string
    {
        $cookieName = 'admin_token';
        $token = $_COOKIE[$cookieName] ?? null;

        if (!is_string($token) || $token === '') {
            $this->redirect('/admin/?reason=login-required');
        }

        if (!$this->checkAdminToken($token)) {
            $this->redirect('/admin/?reason=session-expired');
        }

        if (!$this->isRuntimeReady()) {
            $this->renderRuntimeErrorPage();
        }

        return $token;
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

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
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
        $cfg = fn (string $key, mixed $default = null): mixed => $this->get($key, $default);
        $cfgString = fn (string $key, string $default = ''): string => $this->string($key, $default);
        $cfgInt = fn (string $key, int $default = 0): int => $this->int($key, $default);
        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    public function renderRuntimeErrorPage(): never
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderTemplate('admin/runtime-error.php', [
            'required' => $this->string('min_php_version', '8.5.0'),
            'current' => PHP_VERSION,
        ]);
        exit;
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    }
}
