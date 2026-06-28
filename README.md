# 학교 자습 출결 체크 시스템

PHP와 SQLite로 동작하는 학교 자습 출석 관리 시스템입니다.
학생은 학번과 이름으로 출석하고, 관리자는 출석 기록과 위치 인증,
로그인 세션 및 시스템 업데이트를 관리할 수 있습니다.

- 현재 버전: `v1.9.3`
- 저장소:
  [jaehyun1122/self-study-attendance](https://github.com/jaehyun1122/self-study-attendance)
- 라이선스: MIT

## 주요 기능

- 학번과 이름을 이용한 학생 출석
- 날짜, 검색어, 위치 인증 상태를 이용한 출석 기록 조회
- 출석 기록 수정, 선택 삭제 및 내보내기
- 브라우저 위치 정보를 이용한 출석 위치 검증
- 관리자 승인 대기 및 승인·반려 처리
- 출석 현황과 통계 그래프가 포함된 관리자 대시보드
- 관리자 로그인 세션 조회 및 개별 로그아웃
- GitHub 릴리즈 기반 업데이트 및 현재 버전 재설치
- 출석 기록 또는 전체 시스템 데이터 초기화

## 요구 사항

- PHP 8.0 이상
- PHP 확장
  - 필수: `pdo_sqlite`, `openssl`, `zip`
  - 선택: `curl`, `mbstring`, `intl`
- 웹 서버의 `data/` 디렉터리 쓰기 권한
- 업데이트 기능을 사용할 경우 GitHub에 연결할 수 있는 네트워크 환경

Composer나 별도의 데이터베이스 서버는 필요하지 않습니다.

## 설치

1. 프로젝트를 웹 서버에 배포합니다.
2. `data/config.example.php`를 복사해 `data/config.php`를 만듭니다.
3. `data/config.php`의 `initial_admin_password`를 변경합니다.
4. 브라우저에서 `/install.php`에 접속합니다.
5. 설치 승인 비밀번호와 새 관리자 비밀번호를 입력합니다.

설치가 완료되면 `data/database.sqlite`가 생성됩니다. 이 파일에는 출석
기록과 관리자 정보가 저장되므로 Git에 커밋하지 마세요.

### 로컬 실행

프로젝트 루트에서 PHP 내장 서버를 실행합니다.

```bash
php -S localhost:8000
```

브라우저에서 <http://localhost:8000>으로 접속합니다.

### Apache

프로젝트 루트의 단일 `.htaccess`는 `data/`, `App/`, `templates/`,
`cli/`, 숨김 경로 및 민감 파일의 직접 접근만 차단합니다.

Apache 가상 호스트에서 프로젝트 루트를 `DocumentRoot`로 지정하고
`.htaccess`를 읽을 수 있도록 `AllowOverride All`을 설정하세요.
루트 접근 제어에는 `mod_rewrite`를 사용합니다.

### Nginx

Nginx에서는 PHP-FPM 연결 외에 `data/`, `App/`, `templates/`, `cli/`와
숨김 경로의 외부 접근을 서버 설정에서 반드시 차단하세요. PHP-FPM
소켓과 프로젝트 루트는 배포 환경에 맞게 설정합니다.

## 설정

모든 주요 설정은 `data/config.php`에서 관리합니다.
기본값과 설명은 `data/config.example.php`에서 확인할 수 있습니다.

### 기본 정보

- `app_name`: 화면에 표시할 서비스 이름
- `app_version`: 현재 설치된 버전
- `developer_name`: 관리자 화면에 표시할 개발자 또는 운영자 이름
- `powered_by_url`: 개발자 또는 프로젝트 링크
- `timezone`: 날짜와 시간을 계산할 기본 시간대

### 업데이트

- `update_repository_owner`: GitHub 저장소 소유자
- `update_repository_name`: GitHub 저장소 이름

### 인증 및 입력 제한

- `initial_admin_password`: 설치 마법사 승인 비밀번호
- `token_expire_hours`: 관리자 페이지 이동 시 갱신되는 로그인 세션 유지 시간
- `password_length`: 관리자 비밀번호 최소·최대 길이, 기본 4~32자
- `auth_rate_limit`: 인증 실패 횟수, 계산 시간 및 차단 시간
- `student_no_length`: 학번 최소·최대 길이
- `student_name_length`: 학생 이름 최소·최대 길이

### 동기화 및 위치 인증

- `attendance_location.enabled`: 위치 인증 사용 여부
- `attendance_location.latitude`: 출석 가능 중심 위도
- `attendance_location.longitude`: 출석 가능 중심 경도
- `attendance_location.radius_meters`: 출석 허용 반경
- `attendance_location.timeout_seconds`: 브라우저 위치 요청 제한 시간

### 파일 경로

- `database_path`: SQLite 데이터베이스 파일 경로
- `schema_path`: 설치와 업데이트에 사용할 스키마 파일 경로

## 화면 경로

- `/`: 학생 출석 화면
- `/install.php`: 초기 설치 마법사
- `/admin/`: 관리자 로그인
- `/admin/dash.php`: 출석 현황 및 통계 대시보드
- `/admin/list.php`: 출석 기록 조회, 삭제 및 내보내기
- `/admin/edit.php?id=1`: 출석 기록 수정
- `/admin/location.php`: 위치 인증 설정
- `/admin/system.php`: 세션, 초기화, 업데이트 및 서버 정보 관리
- `/admin/password.php`: 관리자 비밀번호 변경

## 인증과 세션

관리자 로그인 토큰은 `HttpOnly` 쿠키인 `admin_token`에만 저장됩니다.
원본 토큰은 HTML이나 브라우저 저장소에 노출하지 않습니다.

서버의 `admin_tokens` 테이블에는 원본 토큰의 SHA-256 해시와 다음 정보가
저장됩니다.

- 생성 시각
- 만료 시각
- 최근 활동 시각
- IP 주소
- User-Agent

사용자가 활동 중이고 세션 만료까지 3시간 이하로 남으면 세션 만료
시각과 쿠키 만료 시각이 자동으로 연장됩니다.

브라우저 저장소는 다음 용도로만 사용합니다.

- `localStorage.attendance_student`: 학생 학번과 이름
- `localStorage.attendance_filter_history`: 관리자 출석 목록 필터 기록
- `sessionStorage`: 사용하지 않음

## 관리자 비밀번호 초기화

관리자 비밀번호를 잊었다면 서버 터미널에서 프로젝트 루트로 이동한 뒤
다음 명령을 실행합니다.

```bash
php cli/reset-admin-password.php
```

새 비밀번호를 두 번 입력하면 관리자 비밀번호가 변경됩니다. 보안을 위해
기존 관리자 로그인 세션은 모두 종료됩니다.

## 위치 인증

위치 인증을 활성화하면 출석 시 브라우저에서 위치 권한을 요청합니다.
출석 가능 여부는 서버에서 검증하며, 공개 상태 API에는 중심 좌표와
허용 반경을 제공하지 않습니다.

출석 허용 반경 밖이거나 브라우저 위치 정보를 사용할 수 없는 경우에는
승인 대기 기록으로 저장할 수 있습니다. 관리자는 출석 목록에서 위치
상세 정보와 거리를 확인한 뒤 승인하거나 반려할 수 있습니다.

## 업데이트와 재설치

시스템 관리 화면에서 GitHub 릴리즈를 확인하고 새 버전을 설치할 수
있습니다. 현재 버전 파일을 다시 내려받아 재설치하는 기능도 제공합니다.

업데이트 및 재설치 시 다음 정책을 적용합니다.

- `data/config.php`는 사용자 설정을 보존하기 위해 덮어쓰지 않습니다.
- SQLite 파일과 `data/updates/`, `data/backups/`는 덮어쓰지 않습니다.
- `data/schema.sql`은 업데이트한 뒤 현재 데이터베이스에 적용합니다.
- 설치된 버전은 `data/config.php`의 `app_version`에 반영합니다.
- 릴리즈에 포함된 파일만 덮어쓰며 기존의 다른 파일은 삭제하지 않습니다.
- 작업 전 백업 파일을 `data/backups/`에 생성합니다.

업데이트 또는 재설치가 완료되면 `F5`를 눌러 정적 리소스를 새로
불러오세요.

## API

모든 API는 JSON 형식으로 응답합니다.

```json
{
  "status": 1,
  "msg": "성공적으로 처리되었습니다.",
  "time": "2026-06-18 12:00:00",
  "result": null
}
```

`status`가 `1`이면 성공, `2`이면 오류입니다. 관리자 API는 로그인 후
발급되는 `HttpOnly` 쿠키를 사용합니다.

### 공개 API

- `GET /api/status.php`: 설치 상태와 공개 설정 조회
- `POST /api/install.php`: 초기 설치
- `POST /api/attend.php`: 학생 출석
- `POST /api/admin-verify.php`: 학생 정보 수정을 위한 관리자 확인

### 관리자 API

- `POST /api/admin-login.php`: 관리자 로그인
- `POST /api/admin-logout.php`: 현재 세션 로그아웃
- `POST /api/admin-summary.php`: 대시보드 통계 조회
- `POST /api/admin-list.php`: 출석 기록 조회와 삭제
- `POST /api/admin-edit.php`: 출석 기록 조회와 수정
- `POST /api/admin-location.php`: 위치 인증 설정
- `POST /api/admin-settings.php`: 위치 인증 설정 호환 경로
- `POST /api/admin-sessions.php`: 관리자 세션 조회와 로그아웃
- `POST /api/admin-system.php`: 초기화, 서버 정보 및 업데이트
- `POST /api/admin-password.php`: 관리자 비밀번호 변경

### 설치 API 입력 예시

```json
{
  "install_password": "config의 initial_admin_password",
  "password": "새 관리자 비밀번호"
}
```

새 관리자 비밀번호 확인은 설치 화면에서 먼저 검사하며, API에는
`password` 값만 전송합니다.

## 프로젝트 구조

```text
.htaccess

App/
  Controller.php
  Database.php

admin/
  index.php
  dash.php
  list.php
  edit.php
  location.php
  system.php
  password.php

api/
  install.php
  status.php
  attend.php
  admin-*.php

assets/
  app.js
  admin.js
  admin-login.js
  attendance-location.js
  install.js
  public-utils.js
  public.css
  styles.css

cli/
  reset-admin-password.php

data/
  config.php
  config.example.php
  schema.sql
  database.sqlite

templates/
  admin/
  public/
```

정적 리소스 URL에는 현재 앱 버전을 이용한 `?v={app_version}`
캐시 버스터가 추가됩니다.

## 백업

운영 환경에서는 다음 파일과 디렉터리를 정기적으로 백업하세요.

- `data/database.sqlite`
- `data/config.php`
- `data/backups/`

## 라이선스

이 프로젝트는 [MIT 라이선스](LICENSE)로 배포됩니다.
