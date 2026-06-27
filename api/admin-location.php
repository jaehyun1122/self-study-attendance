<?php

declare(strict_types=1);

use App\Controller;

require_once __DIR__ . '/../App/Controller.php';

$app = new Controller();

try {
    $app->requireMethod('POST');
    $app->requireAdminApi();

    $input = $app->jsonInput();
    $type = (string) ($input['type'] ?? 'get');

    if ($type === 'get') {
        $app->success('성공적으로 처리되었습니다.', $app->locationSettings());
    }

    if ($type !== 'save') {
        $app->error('지원하지 않는 요청 유형입니다.', 400);
    }

    $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOL);
    $latitude = is_numeric($input['latitude'] ?? null) ? (float) $input['latitude'] : null;
    $longitude = is_numeric($input['longitude'] ?? null) ? (float) $input['longitude'] : null;
    $radiusMeters = is_numeric($input['radius_meters'] ?? null) ? (int) $input['radius_meters'] : null;
    $timeoutSeconds = is_numeric($input['timeout_seconds'] ?? null) ? (int) $input['timeout_seconds'] : null;

    if ($enabled && ($latitude === null || $longitude === null || $radiusMeters === null)) {
        $app->error('위치 기반 출석을 사용하려면 중심 좌표와 허용 반경을 입력해주세요.', 400);
    }

    if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
        $app->error('위도는 -90에서 90 사이여야 합니다.', 400);
    }

    if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
        $app->error('경도는 -180에서 180 사이여야 합니다.', 400);
    }

    if ($radiusMeters !== null && ($radiusMeters < 10 || $radiusMeters > 5000)) {
        $app->error('허용 반경은 10m 이상 5000m 이하로 입력해주세요.', 400);
    }

    if ($timeoutSeconds !== null && ($timeoutSeconds < 3 || $timeoutSeconds > 60)) {
        $app->error('위치 확인 제한 시간은 3초 이상 60초 이하로 입력해주세요.', 400);
    }

    $settings = [
        'enabled' => $enabled,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radius_meters' => $radiusMeters,
        'timeout_seconds' => $timeoutSeconds,
    ];

    if ($app->isDefaultLocationSettingPayload($settings)) {
        $app->deleteSetting('attendance_location');
    } else {
        $app->saveSetting('attendance_location', $settings);
    }

    $app->success('위치 설정이 저장되었습니다.', $app->locationSettings());
} catch (Throwable $exception) {
    $app->failWithException('위치 설정 처리 중 오류가 발생했습니다.', $exception);
}
