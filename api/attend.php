<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
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
                $locationMessage = '위치 설정을 확인할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.';
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
                        $locationMessage = '교내 출석 가능 범위 밖으로 확인되어 관리자 승인 이후 정상 출결로 처리됩니다.';
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
            $locationUnavailableReason = (string) ($input['location_unavailable_reason'] ?? '');
            $locationUnavailableMessages = [
                'settings_unavailable' => '위치 설정을 확인할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.',
                'insecure_context' => 'HTTPS가 아니어서 위치 권한을 요청할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.',
                'unsupported' => '이 브라우저에서 위치 기능을 지원하지 않아 관리자 승인 이후 정상 출결로 처리됩니다.',
                'permission_denied' => '위치 권한을 사용할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.',
                'position_unavailable' => '현재 위치를 확인할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.',
                'timeout' => '위치 확인 시간이 초과되어 관리자 승인 이후 정상 출결로 처리됩니다.',
            ];
            $locationStatus = 'pending';
            $locationMessage = $locationUnavailableMessages[$locationUnavailableReason]
                ?? '위치 인증을 완료할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.';
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
            'SELECT created_at, location_status FROM attendance WHERE student_no = :student_no AND attend_date = :attend_date'
        );
        $duplicate->execute([
            ':student_no' => $studentNo,
            ':attend_date' => $attendDate,
        ]);
        $duplicateRecord = $duplicate->fetch() ?: [];
        $createdAt = $app->formatDateTime($duplicateRecord['created_at'] ?? $attendDateTime);

        $app->error('이미 출석 처리되었습니다.', 200, [
            'attend_datetime' => $createdAt,
            'location_status' => $duplicateRecord['location_status'] ?? 'unchecked',
        ]);
    }

    $successMessage = $locationStatus === 'pending'
        ? '출석 요청이 기록되었습니다. 관리자 승인 이후 정상 출결로 처리됩니다.'
        : '성공적으로 처리되었습니다.';

    $app->success($successMessage, [
        'attend_datetime' => $app->formatDateTime($attendDateTime),
        'location_status' => $locationStatus,
    ]);
} catch (Throwable $exception) {
    $app->failWithException('출석 처리 중 오류가 발생했습니다.', $exception);
}
