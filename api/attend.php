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
    $student = $app->validatedStudentInput($input);
    $studentNo = $student['student_no'];
    $name = $student['name'];

    $attendDate = $app->today();
    $attendDateTime = $app->now();
    $locationSettings = $app->locationSettings();
    $locationStatus = 'unchecked';
    $locationLatitude = null;
    $locationLongitude = null;
    $locationAccuracy = null;
    $locationDistanceMeters = null;
    $locationMessage = null;
    $locationCheckedAt = null;

    if ($locationSettings['enabled']) {
        $locationCheckedAt = $attendDateTime;
        $hasLatitude = array_key_exists('latitude', $input) && is_numeric($input['latitude']);
        $hasLongitude = array_key_exists('longitude', $input) && is_numeric($input['longitude']);

        if ($hasLatitude && $hasLongitude) {
            $locationLatitude = (float) $input['latitude'];
            $locationLongitude = (float) $input['longitude'];
            $locationAccuracy = is_numeric($input['accuracy'] ?? null) ? max(0, (float) $input['accuracy']) : null;

            if ($locationLatitude < -90 || $locationLatitude > 90 || $locationLongitude < -180 || $locationLongitude > 180) {
                $app->error('위치 정보 형식이 올바르지 않습니다.', 400);
            }

            if (!$locationSettings['configured']) {
                $locationStatus = 'pending';
                $locationMessage = '위치 설정 미완료로 관리자 승인 대기 상태입니다.';
            } else {
                $locationDistanceMeters = $app->distanceMeters(
                    $locationLatitude,
                    $locationLongitude,
                    (float) $locationSettings['latitude'],
                    (float) $locationSettings['longitude']
                );
                $radiusMeters = (int) $locationSettings['radius_meters'];

                if ($locationDistanceMeters > $radiusMeters) {
                    if (filter_var($input['location_override_confirmed'] ?? false, FILTER_VALIDATE_BOOL)) {
                        $locationStatus = 'pending';
                        $locationMessage = '교내 출석 가능 범위를 벗어나 관리자 승인 대기 상태입니다.';
                    } else {
                        $app->error('교내 출석 가능 범위를 벗어났습니다.', 200, [
                            'requires_location_confirmation' => true,
                        ]);
                    }
                } else {
                    $locationStatus = 'verified';
                    $locationMessage = '위치 인증 완료';
                }
            }
        } else {
            $message = trim((string) ($input['location_message'] ?? '위치 권한을 사용할 수 없어 관리자 승인 대기 상태입니다.'));
            $locationStatus = 'pending';
            $locationMessage = $message === '' ? '위치 권한을 사용할 수 없어 관리자 승인 대기 상태입니다.' : $message;
        }
    }

    $statement = $app->pdo()->prepare(
        'INSERT INTO attendance (
            student_no,
            name,
            attend_date,
            created_at,
            location_status,
            location_latitude,
            location_longitude,
            location_accuracy,
            location_distance_meters,
            location_message,
            location_checked_at
        ) VALUES (
            :student_no,
            :name,
            :attend_date,
            :created_at,
            :location_status,
            :location_latitude,
            :location_longitude,
            :location_accuracy,
            :location_distance_meters,
            :location_message,
            :location_checked_at
        )'
    );

    try {
        $statement->execute([
            ':student_no' => $studentNo,
            ':name' => $name,
            ':attend_date' => $attendDate,
            ':created_at' => $attendDateTime,
            ':location_status' => $locationStatus,
            ':location_latitude' => $locationLatitude,
            ':location_longitude' => $locationLongitude,
            ':location_accuracy' => $locationAccuracy,
            ':location_distance_meters' => $locationDistanceMeters,
            ':location_message' => $locationMessage,
            ':location_checked_at' => $locationCheckedAt,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        $duplicate = $app->pdo()->prepare(
            'SELECT created_at, location_status, location_approved_at FROM attendance WHERE student_no = :student_no AND attend_date = :attend_date'
        );
        $duplicate->execute([
            ':student_no' => $studentNo,
            ':attend_date' => $attendDate,
        ]);
        $duplicateRecord = $duplicate->fetch() ?: [];
        $createdAt = $app->formatDateTime($duplicateRecord['created_at'] ?? $attendDateTime);

        $app->error('이미 출석 처리되었습니다.', 200, [
            'student_no' => $studentNo,
            'name' => $name,
            'attend_date' => $attendDate,
            'attend_datetime' => $createdAt,
            'location_status' => $duplicateRecord['location_status'] ?? 'unchecked',
        ]);
    }

    $successMessage = $locationStatus === 'pending'
        ? '관리자 승인 대기 상태로 출석 요청이 기록되었습니다.'
        : '성공적으로 처리되었습니다.';

    $app->success($successMessage, [
        'student_no' => $studentNo,
        'name' => $name,
        'attend_date' => $attendDate,
        'attend_datetime' => $app->formatDateTime($attendDateTime),
        'attend_time' => $app->formatDateTime($attendDateTime),
        'location_status' => $locationStatus,
    ]);
} catch (Throwable $exception) {
    $app->error('출석 처리 중 오류가 발생했습니다.', 500, ['detail' => $exception->getMessage()]);
}
