<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $input = $app->jsonInput();
    $type = (string) ($input['type'] ?? '');

    if ($type === 'reset') {
        $app->requireFields($input, ['password', 'scope']);
        $app->verifyAdminPassword($input['password'] ?? '', 403);

        $scope = (string) ($input['scope'] ?? 'attendance');

        if (!in_array($scope, ['attendance', 'all'], true)) {
            $app->error('지원하지 않는 초기화 범위입니다.', 400);
        }

        $result = resetSystemData($app, $scope);

        if ($scope === 'all') {
            $app->clearAdminCookie();
        }

        $app->success(
            $scope === 'all' ? '모든 데이터가 초기화되었습니다. 초기 설치를 다시 진행해주세요.' : '출석 기록이 초기화되었습니다.',
            $result
        );
    }

    if ($type === 'update_check') {
        $releaseInfo = githubReleaseInfo($app);
        $publicRelease = static fn (array $release): array => [
            'tag_name' => (string) ($release['tag_name'] ?? ''),
            'name' => (string) ($release['name'] ?? ''),
            'published_at_text' => (string) ($release['published_at_text'] ?? ''),
            'body' => (string) ($release['body'] ?? ''),
            'prerelease' => (bool) ($release['prerelease'] ?? false),
            'is_newer' => (bool) ($release['is_newer'] ?? false),
        ];
        $releases = array_map($publicRelease, $releaseInfo['releases'] ?? []);
        $latest = is_array($releaseInfo['latest'] ?? null)
            ? $publicRelease($releaseInfo['latest'])
            : null;

        $app->success('릴리즈 정보를 확인했습니다.', [
            'current_version' => (string) ($releaseInfo['current_version'] ?? ''),
            'latest' => $latest,
            'update_available' => (bool) ($releaseInfo['update_available'] ?? false),
            'releases' => $releases,
        ]);
    }

    if ($type === 'server_info') {
        $app->success('서버 정보를 불러왔습니다.', serverInfo($app));
    }

    if ($type === 'update_install') {
        $app->requireFields($input, ['password']);
        $app->verifyAdminPassword($input['password'] ?? '', 403);

        $releaseInfo = githubReleaseInfo($app);
        $tag = trim((string) ($input['tag'] ?? ($releaseInfo['latest']['tag_name'] ?? '')));
        $release = findReleaseByTag($releaseInfo['releases'], $tag);

        if ($release === null) {
            $app->error('설치할 릴리즈를 찾을 수 없습니다.', 404);
        }

        if (compareVersionLabel($release['tag_name'], $app->string('app_version')) <= 0) {
            $app->error('현재 버전보다 높은 릴리즈만 설치할 수 있습니다.', 400);
        }

        $result = installRelease($app, $release, 'pre-update');
        $app->success('업데이트가 완료되었습니다.', $result);
    }

    if ($type === 'repair_install') {
        $app->requireFields($input, ['password']);
        $app->verifyAdminPassword($input['password'] ?? '', 403);

        $releaseInfo = githubReleaseInfo($app);
        $tag = trim($app->string('app_version'));
        $release = findReleaseByTag($releaseInfo['releases'], $tag);

        if ($release === null) {
            $app->error('현재 버전과 일치하는 릴리즈 태그를 찾을 수 없어 재설치할 수 없습니다.', 404);
        }

        $result = installRelease($app, $release, 'pre-repair');
        $app->success('재설치가 완료되었습니다.', $result);
    }

    $app->error('지원하지 않는 시스템 요청입니다.', 400);
} catch (Throwable $exception) {
    $app->failWithException('시스템 작업 중 오류가 발생했습니다.', $exception);
}

/**
 * @return array{deleted_attendance: int, deleted_settings: int, deleted_tokens: int, deleted_admins: int}
 */
function resetSystemData(Controller $app, string $scope): array
{
    $pdo = $app->pdo();
    $pdo->beginTransaction();

    try {
        $deletedAttendance = (int) $pdo->exec('DELETE FROM attendance');
        $deletedSettings = 0;
        $deletedTokens = 0;
        $deletedAdmins = 0;
        $hasSequence = (int) $pdo
            ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'")
            ->fetchColumn();

        if ($hasSequence > 0) {
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'attendance'");
        }

        if ($scope === 'all') {
            $deletedSettings = (int) $pdo->exec('DELETE FROM app_settings');
            $deletedTokens = (int) $pdo->exec('DELETE FROM admin_tokens');
            $pdo->exec('DELETE FROM auth_rate_limits');
            $deletedAdmins = (int) $pdo->exec('DELETE FROM admin');

            if ($hasSequence > 0) {
                $sequenceTables = ['attendance', 'admin', 'admin_tokens'];
                $placeholders = implode(',', array_fill(0, count($sequenceTables), '?'));
                $sequence = $pdo->prepare("DELETE FROM sqlite_sequence WHERE name IN ({$placeholders})");
                $sequence->execute($sequenceTables);
            }
        }

        $pdo->commit();

        return [
            'deleted_attendance' => $deletedAttendance,
            'deleted_settings' => $deletedSettings,
            'deleted_tokens' => $deletedTokens,
            'deleted_admins' => $deletedAdmins,
        ];
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

/**
 * @return array<string, mixed>
 */
function githubReleaseInfo(Controller $app): array
{
    [$owner, $repo] = configuredRepositoryParts($app);
    $repository = [
        'owner' => $owner,
        'repo' => $repo,
        'url' => "https://github.com/{$owner}/{$repo}",
    ];
    $releases = [];

    try {
        $releasePayload = fetchJson("https://api.github.com/repos/{$owner}/{$repo}/releases");

        foreach ($releasePayload as $release) {
            if (!is_array($release) || !empty($release['draft'])) {
                continue;
            }

            $tag = trim((string) ($release['tag_name'] ?? ''));

            if ($tag === '') {
                continue;
            }

            $releases[] = [
                'tag_name' => $tag,
                'name' => (string) ($release['name'] ?? $tag),
                'published_at' => (string) ($release['published_at'] ?? ''),
                'published_at_text' => releaseDateText((string) ($release['published_at'] ?? '')),
                'html_url' => (string) ($release['html_url'] ?? "https://github.com/{$owner}/{$repo}/releases/tag/{$tag}"),
                'zip_url' => archiveUrl($owner, $repo, $tag),
                'body' => (string) ($release['body'] ?? ''),
                'prerelease' => (bool) ($release['prerelease'] ?? false),
            ];
        }
    } catch (Throwable) {
        $releases = [];
    }

    if ($releases === []) {
        $tagPayload = fetchJson("https://api.github.com/repos/{$owner}/{$repo}/tags");

        foreach ($tagPayload as $tagItem) {
            if (!is_array($tagItem)) {
                continue;
            }

            $tag = trim((string) ($tagItem['name'] ?? ''));

            if ($tag === '') {
                continue;
            }

            $releases[] = [
                'tag_name' => $tag,
                'name' => $tag,
                'published_at' => '',
                'published_at_text' => '태그',
                'html_url' => "https://github.com/{$owner}/{$repo}/releases/tag/{$tag}",
                'zip_url' => archiveUrl($owner, $repo, $tag),
                'body' => '',
                'prerelease' => false,
            ];
        }
    }

    usort($releases, static function (array $first, array $second): int {
        $versionCompare = compareVersionLabel((string) $second['tag_name'], (string) $first['tag_name']);

        if ($versionCompare !== 0) {
            return $versionCompare;
        }

        return strcmp((string) ($second['published_at'] ?? ''), (string) ($first['published_at'] ?? ''));
    });

    $currentVersion = $app->string('app_version');
    $releases = array_map(static function (array $release) use ($currentVersion): array {
        $release['is_newer'] = compareVersionLabel((string) ($release['tag_name'] ?? ''), $currentVersion) > 0;

        return $release;
    }, array_slice($releases, 0, 20));
    $latest = $releases[0] ?? null;

    return [
        'repository' => $repository,
        'current_version' => $currentVersion,
        'latest' => $latest,
        'update_available' => $latest !== null && compareVersionLabel((string) $latest['tag_name'], $currentVersion) > 0,
        'releases' => $releases,
    ];
}

/**
 * @return array{0: string, 1: string}
 */
function configuredRepositoryParts(Controller $app): array
{
    $owner = normalizeRepositoryInput($app->string('update_repository_owner'));
    $repo = normalizeRepositoryInput($app->string('update_repository_name'));

    if ($owner !== '' && $repo !== '') {
        return [$owner, $repo];
    }

    if ($owner !== '' || $repo !== '') {
        throw new RuntimeException('update_repository_owner와 update_repository_name을 모두 설정해주세요.');
    }

    return repositoryParts($app->string('update_repository'));
}

function normalizeRepositoryInput(string $repository): string
{
    return trim(preg_replace('/\s+/', '', $repository) ?? '');
}

/**
 * @return array{0: string, 1: string}
 */
function repositoryParts(string $repositoryUrl): array
{
    $repositoryUrl = normalizeRepositoryInput($repositoryUrl);
    $path = str_contains($repositoryUrl, '://')
        ? trim((string) (parse_url($repositoryUrl, PHP_URL_PATH) ?: ''), '/')
        : trim($repositoryUrl, '/');
    $parts = explode('/', preg_replace('/\.git$/', '', $path) ?? '');

    if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
        throw new RuntimeException('저장소 주소 형식이 올바르지 않습니다.');
    }

    return [$parts[0], $parts[1]];
}

function releaseDateText(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '날짜 없음';
    }

    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return $value;
    }
}

function archiveUrl(string $owner, string $repo, string $tag): string
{
    $tagPath = implode('/', array_map('rawurlencode', explode('/', $tag)));

    return "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$tagPath}.zip";
}

/**
 * @return array<int, mixed>
 */
function fetchJson(string $url): array
{
    $payload = fetchUrl($url, true);
    $decoded = json_decode($payload, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('릴리즈 응답을 해석할 수 없습니다.');
    }

    return $decoded;
}

function fetchUrl(string $url, bool $json = false): string
{
    $headers = [
        'User-Agent: self-study-attendance-updater',
        $json ? 'Accept: application/vnd.github+json' : 'Accept: application/octet-stream',
    ];

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $body === '' || $statusCode >= 400) {
            throw new RuntimeException($error !== '' ? $error : "HTTP {$statusCode} 응답을 받았습니다.");
        }

        return $body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 90,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';

    if (!is_string($body) || $body === '' || preg_match('/\s([45]\d{2})\s/', $statusLine)) {
        throw new RuntimeException($statusLine !== '' ? $statusLine : '원격 파일을 내려받을 수 없습니다.');
    }

    return $body;
}

/**
 * @return array{number: string, prerelease: bool, suffix: string}
 */
function versionInfo(string $label): array
{
    if (!preg_match('/v?(\d+(?:\.\d+){0,3})(?:[\s_-]+([0-9A-Za-z][0-9A-Za-z.-]*))?/i', trim($label), $matches)) {
        return ['number' => '0.0.0', 'prerelease' => true, 'suffix' => 'unknown'];
    }

    $suffix = strtolower((string) ($matches[2] ?? ''));

    return [
        'number' => $matches[1],
        'prerelease' => $suffix !== '',
        'suffix' => $suffix,
    ];
}

function compareVersionLabel(string $first, string $second): int
{
    $firstInfo = versionInfo($first);
    $secondInfo = versionInfo($second);
    $baseCompare = version_compare($firstInfo['number'], $secondInfo['number']);

    if ($baseCompare !== 0) {
        return $baseCompare;
    }

    if ($firstInfo['prerelease'] !== $secondInfo['prerelease']) {
        return $firstInfo['prerelease'] ? -1 : 1;
    }

    return strcmp($firstInfo['suffix'], $secondInfo['suffix']);
}

/**
 * @param list<array<string, mixed>> $releases
 */
function findReleaseByTag(array $releases, string $tag): ?array
{
    foreach ($releases as $release) {
        if ((string) ($release['tag_name'] ?? '') === $tag) {
            return $release;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function serverInfo(Controller $app): array
{
    $uptimeSeconds = serverUptimeSeconds();
    $extensions = [
        ['name' => 'pdo_sqlite', 'label' => 'SQLite DB', 'required' => true, 'loaded' => extension_loaded('pdo_sqlite')],
        ['name' => 'zip', 'label' => '업데이트 압축 해제', 'required' => true, 'loaded' => class_exists('ZipArchive')],
        ['name' => 'openssl', 'label' => 'HTTPS 다운로드', 'required' => true, 'loaded' => extension_loaded('openssl')],
        ['name' => 'curl', 'label' => 'GitHub API 요청', 'required' => false, 'loaded' => function_exists('curl_init')],
        ['name' => 'mbstring', 'label' => '문자 길이 처리', 'required' => false, 'loaded' => extension_loaded('mbstring')],
        ['name' => 'intl', 'label' => '정확한 글자 수 계산', 'required' => false, 'loaded' => extension_loaded('intl')],
    ];

    return [
        'server_time' => $app->now(),
        'timezone' => date_default_timezone_get(),
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'os' => serverOsText(),
        'uptime' => $uptimeSeconds === null ? null : formatUptime($uptimeSeconds),
        'uptime_seconds' => $uptimeSeconds,
        'extensions' => $extensions,
        'memory_limit' => ini_get('memory_limit') ?: '',
        'allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL),
    ];
}

function serverUptimeSeconds(): ?int
{
    $seconds = null;

    if (is_readable('/proc/uptime')) {
        $contents = file_get_contents('/proc/uptime');
        $seconds = is_string($contents) ? (int) floor((float) strtok($contents, ' ')) : null;
    }

    if ($seconds === null && PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
        $output = @shell_exec('wmic os get lastbootuptime /value 2>NUL');

        if (is_string($output) && preg_match('/LastBootUpTime=(\d{14})/', $output, $matches)) {
            $boot = DateTimeImmutable::createFromFormat('YmdHis', $matches[1]);

            if ($boot instanceof DateTimeImmutable) {
                $seconds = time() - $boot->getTimestamp();
            }
        }
    }

    if ($seconds === null || $seconds < 0) {
        return null;
    }

    return $seconds;
}

function formatUptime(int $seconds): string
{
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $seconds %= 60;

    $parts = [];

    if ($days > 0) {
        $parts[] = "{$days}일";
    }

    if ($hours > 0) {
        $parts[] = "{$hours}시간";
    }

    if ($minutes > 0) {
        $parts[] = "{$minutes}분";
    }

    if ($seconds > 0 || $parts === []) {
        $parts[] = "{$seconds}초";
    }

    return implode(' ', $parts);
}

function serverOsText(): string
{
    $kernel = trim(php_uname('r'));

    if (PHP_OS_FAMILY !== 'Linux') {
        return trim(PHP_OS_FAMILY . ' ' . $kernel);
    }

    $prettyName = linuxPrettyName();
    $kernelText = trim('Linux ' . $kernel);

    if ($prettyName === '') {
        return $kernelText;
    }

    return "{$prettyName} ({$kernelText})";
}

function linuxPrettyName(): string
{
    $path = '/etc/os-release';
    $contents = is_readable($path) ? file_get_contents($path) : false;

    if (!is_string($contents)) {
        return '';
    }

    foreach (explode("\n", $contents) as $line) {
        if (!str_starts_with($line, 'PRETTY_NAME=')) {
            continue;
        }

        $value = trim(substr($line, strlen('PRETTY_NAME=')));
        return trim($value, " \t\n\r\0\x0B\"'");
    }

    return '';
}

/**
 * @param array<string, mixed> $release
 * @return array<string, mixed>
 */
function installRelease(Controller $app, array $release, string $backupPrefix): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive 확장이 필요합니다.');
    }

    $root = realpath(dirname(__DIR__));

    if (!is_string($root)) {
        throw new RuntimeException('프로젝트 경로를 확인할 수 없습니다.');
    }

    $dataDir = dirname($app->string('database_path'));
    $updatesDir = $dataDir . DIRECTORY_SEPARATOR . 'updates';
    $backupsDir = $dataDir . DIRECTORY_SEPARATOR . 'backups';
    ensureDirectory($updatesDir);
    ensureDirectory($backupsDir);

    $tag = (string) $release['tag_name'];
    $safeTag = preg_replace('/[^0-9A-Za-z._-]+/', '-', $tag) ?: 'release';
    $zipPath = $updatesDir . DIRECTORY_SEPARATOR . "{$safeTag}.zip";
    $extractDir = $updatesDir . DIRECTORY_SEPARATOR . "extract-{$safeTag}-" . time();
    $zipBody = fetchUrl((string) $release['zip_url']);

    if (strlen($zipBody) > 50 * 1024 * 1024) {
        throw new RuntimeException('업데이트 압축 파일이 허용 크기(50MB)를 초과합니다.');
    }

    if (file_put_contents($zipPath, $zipBody) === false) {
        throw new RuntimeException('업데이트 파일을 저장할 수 없습니다.');
    }

    ensureDirectory($extractDir);
    $zip = new ZipArchive();

    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('업데이트 압축 파일을 열 수 없습니다.');
    }

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        $segments = explode('/', $entry);

        if (
            $entry === ''
            || str_starts_with($entry, '/')
            || preg_match('/^[A-Za-z]:\//', $entry) === 1
            || in_array('..', $segments, true)
        ) {
            $zip->close();
            throw new RuntimeException('업데이트 압축 파일에 안전하지 않은 경로가 있습니다.');
        }
    }

    if (!$zip->extractTo($extractDir)) {
        $zip->close();
        throw new RuntimeException('업데이트 압축을 해제할 수 없습니다.');
    }

    $zip->close();
    $sourceDir = locateExtractedSource($extractDir);
    $safeBackupPrefix = preg_replace('/[^0-9A-Za-z._-]+/', '-', $backupPrefix) ?: 'pre-update';
    $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $safeBackupPrefix . '-' . date('Ymd-His') . '.zip';
    createBackup($root, $backupPath);
    copyUpdateFiles($sourceDir, $root);
    applyDatabaseSchema($app);
    updateConfigVersion($app, $tag);
    removeDirectory($extractDir);

    return [
        'installed_version' => $tag,
        'backup_path' => 'data/backups/' . basename($backupPath),
    ];
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("디렉터리를 만들 수 없습니다: {$directory}");
    }
}

function locateExtractedSource(string $extractDir): string
{
    if (is_file($extractDir . DIRECTORY_SEPARATOR . 'index.php')) {
        return $extractDir;
    }

    foreach (new DirectoryIterator($extractDir) as $item) {
        if ($item->isDot() || !$item->isDir()) {
            continue;
        }

        $candidate = $item->getPathname();

        if (is_file($candidate . DIRECTORY_SEPARATOR . 'index.php')) {
            return $candidate;
        }
    }

    throw new RuntimeException('업데이트 패키지 구조가 올바르지 않습니다.');
}

function shouldSkipManagedPath(string $relativePath): bool
{
    $path = str_replace('\\', '/', ltrim($relativePath, '/'));

    return $path === '.git'
        || str_starts_with($path, '.git/')
        || $path === '.agents'
        || str_starts_with($path, '.agents/')
        || $path === '.codex'
        || str_starts_with($path, '.codex/')
        || $path === 'data/config.php'
        || $path === 'data/database.sqlite'
        || preg_match('#^data/.*\.sqlite(?:-.+)?$#', $path) === 1
        || str_starts_with($path, 'data/updates/')
        || str_starts_with($path, 'data/backups/');
}

function createBackup(string $root, string $backupPath): void
{
    $zip = new ZipArchive();

    if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('백업 파일을 만들 수 없습니다.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));

        if ($item->isDir() || shouldSkipManagedPath($relative)) {
            continue;
        }

        $zip->addFile($path, $relative);
    }

    $zip->close();
}

function copyUpdateFiles(string $sourceDir, string $root): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relative = str_replace('\\', '/', substr($sourcePath, strlen($sourceDir) + 1));

        if (shouldSkipManagedPath($relative)) {
            continue;
        }

        $targetPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($item->isDir()) {
            ensureDirectory($targetPath);
            continue;
        }

        ensureDirectory(dirname($targetPath));

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("파일을 업데이트할 수 없습니다: {$relative}");
        }
    }
}

function applyDatabaseSchema(Controller $app): void
{
    $schemaPath = $app->string('schema_path');
    $schema = is_readable($schemaPath) ? file_get_contents($schemaPath) : false;

    if (!is_string($schema) || trim($schema) === '') {
        throw new RuntimeException('업데이트된 스키마 파일을 읽을 수 없습니다.');
    }

    foreach (schemaStatements($schema) as $statement) {
        applySafeSchemaStatement($app, $statement);
    }
}

/**
 * @return list<string>
 */
function schemaStatements(string $schema): array
{
    return array_values(array_filter(array_map('trim', explode(';', $schema)), static fn (string $statement): bool => $statement !== ''));
}

function applySafeSchemaStatement(Controller $app, string $statement): void
{
    $normalized = trim(preg_replace('/\s+/', ' ', $statement) ?? '');

    if ($normalized === '') {
        return;
    }

    $allowed = preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+/i', $normalized) === 1
        || preg_match('/^CREATE\s+(UNIQUE\s+)?INDEX\s+IF\s+NOT\s+EXISTS\s+/i', $normalized) === 1
        || preg_match('/^ALTER\s+TABLE\s+\S+\s+ADD\s+COLUMN\s+/i', $normalized) === 1;

    if (!$allowed) {
        throw new RuntimeException('데이터 보존을 위해 안전하지 않은 스키마 문장은 실행하지 않습니다.');
    }

    $app->pdo()->exec($statement);
}

function updateConfigVersion(Controller $app, string $version): void
{
    $configPath = dirname($app->string('database_path')) . DIRECTORY_SEPARATOR . 'config.php';
    $contents = is_readable($configPath) ? file_get_contents($configPath) : false;

    if (!is_string($contents)) {
        throw new RuntimeException('설정 파일을 읽을 수 없습니다.');
    }

    $escapedVersion = str_replace(["\\", "'"], ["\\\\", "\\'"], $version);
    $updated = preg_replace(
        "/'app_version'\s*=>\s*'[^']*'/",
        "'app_version' => '{$escapedVersion}'",
        $contents,
        1,
        $count
    );

    if (!is_string($updated) || $count < 1) {
        throw new RuntimeException('설정 파일에서 버전 항목을 찾을 수 없습니다.');
    }

    if (file_put_contents($configPath, $updated) === false) {
        throw new RuntimeException('설정 파일을 업데이트할 수 없습니다.');
    }
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($directory);
}
