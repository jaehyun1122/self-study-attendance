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
    $type = (string) ($input['type'] ?? '');

    if ($type === '') {
        $app->error('수정 유형을 선택해주세요.', 400);
    }

    $idFromInput = static function (array $input) use ($app): int {
        $id = (int) ($input['id'] ?? 0);

        if ($id < 1) {
            $app->error('올바른 출석 기록을 선택해주세요.', 400);
        }

        return $id;
    };

    $recordById = static function (int $id) use ($app): array {
        $statement = $app->pdo()->prepare(
            'SELECT
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
            WHERE id = :id'
        );
        $statement->execute([':id' => $id]);
        $record = $statement->fetch();

        if (!$record) {
            $app->error('출석 기록을 찾을 수 없습니다.', 404);
        }

        return $record;
    };

    $normalizeDateTime = static function (mixed $value, string $label) use ($app): string {
        $text = trim(str_replace('T', ' ', (string) $value));

        if ($text === '') {
            $app->error("{$label}을 입력해주세요.", 400);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $text)) {
            $text .= ':00';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $text)) {
            $app->error("{$label} 형식은 YYYY-MM-DD HH:mm:ss이어야 합니다.", 400);
        }

        $timestamp = strtotime($text);

        if ($timestamp === false) {
            $app->error("{$label}이 올바르지 않습니다.", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    };

    $nullableDateTime = static function (mixed $value, string $label) use ($normalizeDateTime): ?string {
        if ($value === null) {
            return null;
        }

        $text = trim(str_replace('T', ' ', (string) $value));

        return $text === '' ? null : $normalizeDateTime($text, $label);
    };

    $nullableFloat = static function (mixed $value, string $label, ?float $min = null, ?float $max = null) use ($app): ?float {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (!is_numeric($text)) {
            $app->error("{$label}은 숫자로 입력해주세요.", 400);
        }

        $number = (float) $text;

        if ($min !== null && $number < $min) {
            $app->error("{$label}은 {$min} 이상이어야 합니다.", 400);
        }

        if ($max !== null && $number > $max) {
            $app->error("{$label}은 {$max} 이하이어야 합니다.", 400);
        }

        return $number;
    };

    $verifyAdminPassword = static function (mixed $password) use ($app): void {
        $app->verifyAdminPassword($password, 403);
    };

    if ($type === 'get') {
        $record = $recordById($idFromInput($input));
        $record['attend_datetime'] = $app->formatDateTime($record['created_at'] ?? $record['attend_datetime'] ?? null);

        $app->success('성공적으로 처리되었습니다.', $record);
    }

    if ($type === 'delete') {
        $statement = $app->pdo()->prepare('DELETE FROM attendance WHERE id = :id');
        $statement->execute([':id' => $idFromInput($input)]);
        $app->success('삭제되었습니다.');
    }

    if ($type === 'bulk_delete') {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            is_array($input['ids'] ?? null) ? $input['ids'] : []
        ), static fn (int $value): bool => $value > 0)));

        if (count($ids) < 1) {
            $app->error('삭제할 출석 기록을 선택해주세요.', 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $app->pdo()->prepare("DELETE FROM attendance WHERE id IN ({$placeholders})");
        $statement->execute($ids);

        $app->success('선택한 출석 기록이 삭제되었습니다.', ['deleted' => $statement->rowCount()]);
    }

    if ($type === 'reset_attendance') {
        $verifyAdminPassword($input['password'] ?? '');

        $pdo = $app->pdo();
        $pdo->beginTransaction();

        try {
            $deleted = $pdo->exec('DELETE FROM attendance');
            $hasSequence = (int) $pdo
                ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'")
                ->fetchColumn();

            if ($hasSequence > 0) {
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'attendance'");
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $app->success('출석 기록이 초기화되었습니다.', ['deleted' => (int) $deleted]);
    }

    if ($type === 'approve_location') {
        $statement = $app->pdo()->prepare(
            "UPDATE attendance
            SET location_status = 'approved',
                location_approved_at = :location_approved_at,
                location_message = :location_message
            WHERE id = :id AND location_status = 'pending'"
        );
        $statement->execute([
            ':id' => $idFromInput($input),
            ':location_approved_at' => $app->now(),
            ':location_message' => '관리자 승인 완료',
        ]);

        if ($statement->rowCount() < 1) {
            $app->error('관리자 승인 대기 중인 출석 기록을 찾을 수 없습니다.', 404);
        }

        $app->success('위치 인증을 승인했습니다.');
    }

    if ($type === 'reject_location') {
        $statement = $app->pdo()->prepare(
            "UPDATE attendance
            SET location_status = 'rejected',
                location_approved_at = :location_approved_at,
                location_message = :location_message
            WHERE id = :id AND location_status = 'pending'"
        );
        $statement->execute([
            ':id' => $idFromInput($input),
            ':location_approved_at' => $app->now(),
            ':location_message' => '위치 인증이 관리자에 의해 반려되었습니다.',
        ]);

        if ($statement->rowCount() < 1) {
            $app->error('관리자 승인 대기 중인 출석 기록을 찾을 수 없습니다.', 404);
        }

        $app->success('위치 인증을 반려했습니다.');
    }

    if ($type !== 'update') {
        $app->error('지원하지 않는 수정 유형입니다.', 400);
    }

    $locationMessageTemplates = [
        'verified' => '위치 인증 완료',
        'pending_range' => '교내 출석 가능 범위 밖으로 확인되어 관리자 승인 이후 정상 출결로 처리됩니다.',
        'pending_settings' => '위치 설정을 확인할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.',
        'approved' => '관리자 승인 완료',
        'rejected' => '위치 인증이 관리자에 의해 반려되었습니다.',
        'unchecked' => '위치 인증 미사용',
    ];

    $computedLocationState = static function (?float $latitude, ?float $longitude) use ($app, $locationMessageTemplates): array {
        if ($latitude === null || $longitude === null) {
            return [
                'status' => 'unchecked',
                'distance' => null,
                'message' => $locationMessageTemplates['unchecked'],
            ];
        }

        $settings = $app->locationSettings();

        if (!$settings['configured']) {
            return [
                'status' => 'pending',
                'distance' => null,
                'message' => $locationMessageTemplates['pending_settings'],
            ];
        }

        $distance = $app->distanceMeters(
            $latitude,
            $longitude,
            (float) $settings['latitude'],
            (float) $settings['longitude']
        );

        if ($distance > (int) $settings['radius_meters']) {
            return [
                'status' => 'pending',
                'distance' => $distance,
                'message' => $locationMessageTemplates['pending_range'],
            ];
        }

        return [
            'status' => 'verified',
            'distance' => $distance,
            'message' => $locationMessageTemplates['verified'],
        ];
    };

    $id = $idFromInput($input);
    $existing = $recordById($id);
    $student = $app->validatedStudentInput($input);
    $studentNo = $student['student_no'];
    $name = $student['name'];
    $createdAt = $normalizeDateTime($input['created_at'] ?? ($input['attend_datetime'] ?? $existing['created_at']), '출석일시');
    $attendDate = substr($createdAt, 0, 10);

    $locationLatitude = $nullableFloat($input['location_latitude'] ?? null, '위도', -90, 90);
    $locationLongitude = $nullableFloat($input['location_longitude'] ?? null, '경도', -180, 180);
    $locationAccuracy = $nullableFloat($input['location_accuracy'] ?? null, '정확도', 0);
    $locationCheckedAt = $nullableDateTime($input['location_checked_at'] ?? null, '위치 확인 시각');
    $locationApprovedAt = $nullableDateTime($input['location_approved_at'] ?? null, '승인/반려 시각');

    if (($locationLatitude === null) !== ($locationLongitude === null)) {
        $app->error('위도와 경도를 함께 입력해주세요.', 400);
    }

    if ($locationLatitude === null) {
        $locationAccuracy = null;
        $locationCheckedAt = null;
    } elseif ($locationCheckedAt === null) {
        $locationCheckedAt = $existing['location_checked_at'] ?: $app->now();
    }

    $computed = $computedLocationState($locationLatitude, $locationLongitude);
    $locationStatus = $computed['status'];
    $locationDistanceMeters = $computed['distance'];
    $messageTemplate = (string) ($input['location_message_template'] ?? 'auto');
    $allowedMessageTemplates = array_merge(['auto', 'custom'], array_keys($locationMessageTemplates));

    if (!in_array($messageTemplate, $allowedMessageTemplates, true)) {
        $app->error('지원하지 않는 위치 메시지 템플릿입니다.', 400);
    }

    if ($messageTemplate === 'custom') {
        $locationMessageText = trim((string) ($input['location_message'] ?? ''));
        $locationMessage = $locationMessageText === '' ? null : $locationMessageText;
    } elseif ($messageTemplate === 'auto') {
        $locationMessage = $computed['message'];
    } else {
        $locationMessage = $locationMessageTemplates[$messageTemplate];
    }

    $statement = $app->pdo()->prepare(
        'UPDATE attendance
        SET student_no = :student_no,
            name = :name,
            attend_date = :attend_date,
            created_at = :created_at,
            location_status = :location_status,
            location_latitude = :location_latitude,
            location_longitude = :location_longitude,
            location_accuracy = :location_accuracy,
            location_distance_meters = :location_distance_meters,
            location_message = :location_message,
            location_checked_at = :location_checked_at,
            location_approved_at = :location_approved_at
        WHERE id = :id'
    );

    try {
        $statement->execute([
            ':student_no' => $studentNo,
            ':name' => $name,
            ':attend_date' => $attendDate,
            ':created_at' => $createdAt,
            ':location_status' => $locationStatus,
            ':location_latitude' => $locationLatitude,
            ':location_longitude' => $locationLongitude,
            ':location_accuracy' => $locationAccuracy,
            ':location_distance_meters' => $locationDistanceMeters,
            ':location_message' => $locationMessage,
            ':location_checked_at' => $locationCheckedAt,
            ':location_approved_at' => $locationApprovedAt,
            ':id' => $id,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            $app->error('같은 날짜에 동일한 학번 출석 기록이 이미 있습니다.', 409);
        }

        throw $exception;
    }

    $app->success('저장되었습니다.', [
        'attend_date' => $attendDate,
        'created_at' => $createdAt,
    ]);
} catch (Throwable $exception) {
    $app->error('출석 기록 저장 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
