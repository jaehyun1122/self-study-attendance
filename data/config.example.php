<?php

declare(strict_types=1);

return [
    // 기본 정보
    'app_name' => '자습 출결 체크', // 화면에 표시할 서비스 이름
    'app_version' => 'v1.7.7', // 현재 설치된 앱 버전
    'developer_name' => '정재현', // 관리자 화면 하단에 표시할 개발자/운영자 이름
    'powered_by_url' => 'https://svsvo.com/profile/', // 개발자 이름에 연결할 주소
    'timezone' => 'Asia/Seoul', // 날짜와 시간을 계산할 기본 시간대

    // 업데이트 설정
    'update_repository_owner' => 'jaehyun1122', // 업데이트 확인에 사용할 GitHub 저장소 소유자
    'update_repository_name' => 'self-study-attendance', // 업데이트 확인에 사용할 GitHub 저장소 이름

    // 인증 및 설치 설정
    'initial_admin_password' => 'admin1234', // 설치 마법사 실행을 승인하는 비밀번호
    'token_expire_hours' => 24, // 관리자 로그인 토큰 유지 시간
    'password_length' => [4, 20], // 관리자 비밀번호 최소/최대 글자 수

    // 학생 입력 제한
    'student_no_length' => [5, 5], // 학번 최소/최대 자리 수
    'student_name_length' => [1, 10], // 이름 최소/최대 글자 수

    // 화면 동기화 설정
    'server_time_sync_interval_seconds' => 5, // 서버 시간/정보를 다시 동기화할 간격(초)

    // 경로 설정
    'database_path' => __DIR__ . '/database.sqlite', // SQLite 데이터베이스 파일 경로
    'schema_path' => __DIR__ . '/schema.sql', // 초기 설치와 업데이트 후 적용할 DB 스키마 파일 경로

    // 출석 위치 인증 기본값
    'attendance_location' => [
        'enabled' => false, // 위치 인증 사용 여부
        'latitude' => null, // 출석 가능 중심 위도
        'longitude' => null, // 출석 가능 중심 경도
        'radius_meters' => 100, // 출석 허용 반경(미터)
        'timeout_seconds' => 30, // 브라우저 위치 요청 제한 시간(초)
    ],
];
