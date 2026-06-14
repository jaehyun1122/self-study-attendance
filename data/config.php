<?php

declare(strict_types=1);

return [
    'app_name' => '자습 출결 체크',
    'app_version' => 'v1.5.0',
    'repository_url' => 'https://github.com/jaehyun1122/self-study-attendance',
    'powered_by' => 'self-study-attendance (jaehyun1122)',
    'min_php_version' => 'v8.5.0',
    'timezone' => 'Asia/Seoul',
    'database_path' => __DIR__ . '/database.sqlite',
    'schema_path' => __DIR__ . '/schema.sql',
    'token_expire_hours' => 24,
    'password_length' => [4, 20],
    'student_no_length' => [5, 5],
    'student_name_length' => [1, 5],
    'server_time_sync_interval_seconds' => 5,
];
