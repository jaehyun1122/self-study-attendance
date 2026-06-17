<?php

declare(strict_types=1);

return [
    'app_name' => '자습 출결 체크',
    'app_version' => 'v1.6.3',
    'repository_url' => 'https://github.com/jaehyun1122/self-study-attendance',
    'update_repository' => 'jaehyun1122/self-study-attendance',
    'powered_by' => '정재현',
    'timezone' => 'Asia/Seoul',
    'database_path' => __DIR__ . '/database.sqlite',
    'schema_path' => __DIR__ . '/schema.sql',
    'token_expire_hours' => 24,
    'initial_admin_password' => 'admin1234',
    'password_length' => [4, 20],
    'student_no_length' => [5, 5],
    'student_name_length' => [1, 10],
    'server_time_sync_interval_seconds' => 5,
    'attendance_location' => [
        'enabled' => false,
        'latitude' => null,
        'longitude' => null,
        'radius_meters' => 100,
        'timeout_seconds' => 30,
    ],
];
