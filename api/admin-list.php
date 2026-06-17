<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->assertRuntimeForApi();
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $input = $app->jsonInput();
    $legacyDate = trim((string) ($input['date'] ?? ''));
    $startDate = trim((string) ($input['start_date'] ?? ($legacyDate !== '' ? $legacyDate : $app->today())));
    $endDate = trim((string) ($input['end_date'] ?? $startDate));
    $studentNo = trim((string) ($input['student_no'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $locationStatus = trim((string) ($input['location_status'] ?? ($input['status'] ?? '')));
    $keyword = trim((string) ($input['keyword'] ?? ''));
    $sortBy = (string) ($input['sort_by'] ?? 'created_at');
    $sortOrderValue = (string) ($input['sort_order'] ?? ($input['sort_dir'] ?? 'asc'));
    $sortOrder = strtolower($sortOrderValue) === 'desc' ? 'DESC' : 'ASC';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
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
        $where[] = 'student_no LIKE :student_no';
        $params[':student_no'] = '%' . $studentNo . '%';
    }

    if ($name !== '') {
        $where[] = 'name LIKE :name';
        $params[':name'] = '%' . $name . '%';
    }

    if ($keyword !== '') {
        $where[] = '(student_no LIKE :keyword OR name LIKE :keyword)';
        $params[':keyword'] = '%' . $keyword . '%';
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
    $app->error('출석 목록 조회 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
