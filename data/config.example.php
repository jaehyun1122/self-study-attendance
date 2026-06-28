<?php

declare(strict_types=1);

return [
    // 기본 정보
    'app_name' => '자습 출결 체크', // 화면에 표시할 서비스 이름
    'app_version' => 'v1.9.7', // 현재 설치된 앱 버전
    'developer_name' => '정재현', // 관리자 화면 하단에 표시할 개발자/운영자 이름
    'powered_by_url' => 'https://github.com/jaehyun1122/self-study-attendance', // 개발자 이름에 연결할 주소
    'timezone' => 'Asia/Seoul', // 날짜와 시간을 계산할 기본 시간대
    'auto_refresh_seconds' => 5, // 화면 데이터 자동 갱신 주기(초), 0이면 자동 갱신 안 함

    // 업데이트 설정
    'update_repository_owner' => 'jaehyun1122', // 업데이트 확인에 사용할 GitHub 저장소 소유자
    'update_repository_name' => 'self-study-attendance', // 업데이트 확인에 사용할 GitHub 저장소 이름

    // 인증 및 설치 설정
    'initial_admin_password' => 'admin1234', // 배포 전에 반드시 설정할 설치 마법사 승인 비밀번호
    'token_expire_hours' => 24, // 로그인 토큰 유지 시간
    'token_refresh_threshold_hours' => 3, // 활동 시 토큰을 연장할 만료 전 남은 시간(0이면 자동 연장 안 함)
    'password_length' => [4, 32], // 관리자 비밀번호 최소/최대 글자 수
    'auth_rate_limit' => [
        'max_attempts' => 10, // 동일 IP에서 로그인 실패를 허용할 횟수
        'window_seconds' => 300, // 로그인 실패 횟수를 계산할 시간(초)
        'block_seconds' => 300, // 로그인 횟수 초과 시 차단할 시간(초)
    ],

    // 학생 입력 제한
    'student_no_length' => [5, 5], // 학번 최소/최대 자리 수
    'student_name_length' => [1, 10], // 이름 최소/최대 글자 수

    // 경로 설정
    'database_path' => __DIR__ . '/database.sqlite', // SQLite 데이터베이스 파일 경로
    'schema_path' => __DIR__ . '/schema.sql', // 초기 설치와 업데이트 후 적용할 DB 스키마 파일 경로

    // 출석 위치 인증 기본값
    'attendance_location' => [
        'enabled' => false, // 위치 인증 사용 여부
        'latitude' => null, // 출석 가능 중심 위도
        'longitude' => null, // 출석 가능 중심 경도
        'radius_meters' => 150, // 출석 허용 반경(미터)
        'timeout_seconds' => 30, // 브라우저 위치 요청 제한 시간(초)
    ],
];
