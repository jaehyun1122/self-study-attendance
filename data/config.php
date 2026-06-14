<?php

declare(strict_types=1);

return [
    'app_name' => '자습 출결 체크 시스템',
    'app_version' => '1.0.0',
    'min_php_version' => '8.5.0',
    'timezone' => 'Asia/Seoul',
    'database_path' => __DIR__ . '/database.sqlite',
    'schema_path' => __DIR__ . '/schema.sql',
    'token_expire_hours' => 24,
    'password_min_length' => 4,
    'student_no_length' => 5,
    'student_name_max_length' => 5,
];
