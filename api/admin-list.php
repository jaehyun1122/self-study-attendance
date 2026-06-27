<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $input = $app->jsonInput();
    $legacyDate = filterText($app, $input, 'date', 10);
    $startDate = filterText($app, $input, 'start_date', 10, $legacyDate !== '' ? $legacyDate : $app->today());
    $endDate = filterText($app, $input, 'end_date', 10, $startDate);
    $studentNo = filterText($app, $input, 'student_no', 20);
    $name = filterText($app, $input, 'name', 50);
    $locationStatus = filterText($app, $input, 'location_status', 20, filterText($app, $input, 'status', 20));
    $keyword = filterText($app, $input, 'keyword', 50);
    $sortBy = filterText($app, $input, 'sort_by', 30, 'created_at');
    $sortOrderValue = filterText($app, $input, 'sort_order', 4, filterText($app, $input, 'sort_dir', 4, 'asc'));
    $sortOrder = strtolower($sortOrderValue) === 'desc' ? 'DESC' : 'ASC';

    if (!validDate($startDate) || !validDate($endDate)) {
        $app->error('날짜 형식은 YYYY-MM-DD이어야 합니다.', 400);
    }

    if ($startDate > $endDate) {
        $app->error('조회 시작일은 종료일보다 늦을 수 없습니다.', 400);
    }

    $where = ['attend_date BETWEEN :start_date AND :end_date'];
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ];

    if ($studentNo !== '') {
        if (!preg_match('/^\d+$/', $studentNo)) {
            $app->error('학번 검색은 숫자만 입력할 수 있습니다.', 400);
        }

        $where[] = "student_no LIKE :student_no ESCAPE '\\'";
        $params[':student_no'] = '%' . escapeLike($studentNo) . '%';
    }

    if ($name !== '') {
        $where[] = "name LIKE :name ESCAPE '\\'";
        $params[':name'] = '%' . escapeLike($name) . '%';
    }

    if ($keyword !== '') {
        $where[] = "(student_no LIKE :keyword ESCAPE '\\' OR name LIKE :keyword ESCAPE '\\')";
        $params[':keyword'] = '%' . escapeLike($keyword) . '%';
    }

    $statusOptions = ['unchecked', 'verified', 'pending', 'approved', 'rejected'];

    if ($locationStatus !== '') {
        if (!in_array($locationStatus, $statusOptions, true)) {
            $app->error('지원하지 않는 위치 인증 상태입니다.', 400);
        }

        $where[] = 'location_status = :location_status';
        $params[':location_status'] = $locationStatus;
    }

    $sortColumns = [
        'attend_date' => 'attend_date',
        'created_at' => 'created_at',
        'student_no' => 'student_no',
        'name' => 'name COLLATE NOCASE',
        'location_status' => 'location_status',
    ];
    $sortColumn = $sortColumns[$sortBy] ?? $sortColumns['created_at'];
    $whereSql = implode(' AND ', $where);

    $statement = $app->pdo()->prepare(
        "SELECT
            id,
            student_no,
            name,
            attend_date,
            created_at,
            created_at AS attend_datetime,
            location_status,
            location_latitude,
            location_longitude,
            location_accuracy,
            location_distance_meters,
            location_message,
            location_checked_at,
            location_approved_at
        FROM attendance
        WHERE {$whereSql}
        ORDER BY {$sortColumn} {$sortOrder}, id {$sortOrder}"
    );
    $statement->execute($params);

    $rows = array_map(static function (array $row) use ($app): array {
        $row['attend_datetime'] = $app->formatDateTime($row['created_at'] ?? $row['attend_datetime'] ?? null);
        return $row;
    }, $statement->fetchAll());

    $app->success('성공적으로 처리되었습니다.', $rows);
} catch (Throwable $exception) {
    $app->failWithException('출석 목록 조회 중 오류가 발생했습니다.', $exception);
}

/**
 * @param array<string, mixed> $input
 */
function filterText(Controller $app, array $input, string $key, int $maxLength, string $default = ''): string
{
    if (!array_key_exists($key, $input)) {
        return $default;
    }

    if (!is_string($input[$key]) && !is_numeric($input[$key])) {
        $app->error("{$key} 입력 형식이 올바르지 않습니다.", 400);
    }

    $value = trim((string) $input[$key]);

    if ($app->textLength($value) > $maxLength) {
        $app->error("{$key} 입력은 {$maxLength}자 이하여야 합니다.", 400);
    }

    return $value;
}

function validDate(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        return false;
    }

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
}

function escapeLike(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}
