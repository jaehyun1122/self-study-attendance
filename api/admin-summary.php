<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $pdo = $app->pdo();
    $today = $app->today();
    $trendStart = date('Y-m-d', strtotime($today . ' -13 days'));
    $countedStatuses = "('verified', 'approved', 'unchecked')";

    $todayStatement = $pdo->prepare(
        "SELECT COUNT(*) FROM attendance
         WHERE attend_date = :attend_date AND location_status IN {$countedStatuses}"
    );
    $todayStatement->execute([':attend_date' => $today]);
    $todayCount = (int) $todayStatement->fetchColumn();

    $totalCount = (int) $pdo
        ->query("SELECT COUNT(*) FROM attendance WHERE location_status IN {$countedStatuses}")
        ->fetchColumn();
    $pendingCount = (int) $pdo
        ->query("SELECT COUNT(*) FROM attendance WHERE location_status = 'pending'")
        ->fetchColumn();
    $studentCount = (int) $pdo
        ->query("SELECT COUNT(DISTINCT student_no) FROM attendance WHERE location_status IN {$countedStatuses}")
        ->fetchColumn();

    $trendStatement = $pdo->prepare(
        "SELECT attend_date, COUNT(*) AS attendance_count
         FROM attendance
         WHERE attend_date BETWEEN :start_date AND :end_date
           AND location_status IN {$countedStatuses}
         GROUP BY attend_date
         ORDER BY attend_date ASC"
    );
    $trendStatement->execute([
        ':start_date' => $trendStart,
        ':end_date' => $today,
    ]);
    $trendByDate = [];

    foreach ($trendStatement->fetchAll() as $row) {
        $trendByDate[(string) $row['attend_date']] = (int) $row['attendance_count'];
    }

    $dailyTrend = [];
    $trendDate = new DateTimeImmutable($trendStart);
    $trendEnd = new DateTimeImmutable($today);

    while ($trendDate <= $trendEnd) {
        $date = $trendDate->format('Y-m-d');
        $dailyTrend[] = [
            'date' => $date,
            'count' => $trendByDate[$date] ?? 0,
        ];
        $trendDate = $trendDate->modify('+1 day');
    }

    $gradeStatement = $pdo->prepare(
        "SELECT
            substr(student_no, 1, 1) AS grade,
            COUNT(DISTINCT student_no) AS student_count,
            COUNT(DISTINCT CASE WHEN attend_date = :today THEN student_no END) AS today_count,
            COUNT(*) AS attendance_count
         FROM attendance
         WHERE location_status IN {$countedStatuses}
           AND substr(student_no, 1, 1) BETWEEN '1' AND '9'
         GROUP BY substr(student_no, 1, 1)
         ORDER BY CAST(substr(student_no, 1, 1) AS INTEGER)"
    );
    $gradeStatement->execute([':today' => $today]);
    $gradeStats = array_map(static function (array $row): array {
        $students = (int) $row['student_count'];
        $todayStudents = (int) $row['today_count'];

        return [
            'grade' => (int) $row['grade'],
            'student_count' => $students,
            'today_count' => $todayStudents,
            'attendance_count' => (int) $row['attendance_count'],
            'today_rate' => $students > 0 ? round($todayStudents / $students * 100, 1) : 0.0,
        ];
    }, $gradeStatement->fetchAll());

    $locationStatement = $pdo->query(
        'SELECT location_status AS status, COUNT(*) AS status_count
         FROM attendance
         GROUP BY location_status
         ORDER BY status_count DESC'
    );
    $locationStats = array_map(static fn (array $row): array => [
        'status' => (string) $row['status'],
        'count' => (int) $row['status_count'],
    ], $locationStatement->fetchAll());

    $hourStatement = $pdo->query(
        "SELECT substr(created_at, 12, 2) AS hour, COUNT(*) AS attendance_count
         FROM attendance
         WHERE location_status IN {$countedStatuses}
           AND created_at GLOB '????-??-?? ??:??:*'
         GROUP BY substr(created_at, 12, 2)
         ORDER BY hour"
    );
    $hourCounts = [];

    foreach ($hourStatement->fetchAll() as $row) {
        $hour = (int) $row['hour'];

        if ($hour >= 0 && $hour <= 23) {
            $hourCounts[$hour] = (int) $row['attendance_count'];
        }
    }

    $hourlyStats = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $hourlyStats[] = [
            'hour' => $hour,
            'count' => $hourCounts[$hour] ?? 0,
        ];
    }

    $app->success('대시보드 정보를 불러왔습니다.', [
        'today' => $todayCount,
        'total' => $totalCount,
        'pending' => $pendingCount,
        'student_count' => $studentCount,
        'today_rate' => $studentCount > 0 ? round($todayCount / $studentCount * 100, 1) : 0.0,
        'daily_trend' => $dailyTrend,
        'grade_stats' => $gradeStats,
        'location_stats' => $locationStats,
        'hourly_stats' => $hourlyStats,
        'server_time' => $app->now(),
    ]);
} catch (Throwable $exception) {
    $app->failWithException('대시보드 정보를 불러오는 중 오류가 발생했습니다.', $exception);
}
