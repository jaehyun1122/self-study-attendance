# 학교 자습 출결 체크 시스템

PHP와 SQLite로 동작하는 자습 출석 체크 시스템입니다. 학생은 학번과 이름으로 출석하고, 관리자는 출석 목록, 위치 인증, 기록 수정, 초기화, 업데이트를 관리합니다.

- 현재 버전: `v1.8.6`
- 공개 저장소: <https://github.com/jaehyun1122/self-study-attendance>

## 요구 환경

- PHP 8.x 권장
- PHP 확장: `pdo_sqlite`, `openssl`, `zip`
- 선택 확장: `curl`, `mbstring`, `intl`
- 웹 서버의 `data/` 폴더 쓰기 권한

## 설치

1. 프로젝트를 웹 서버에 배포합니다.
2. `data/config.example.php`를 참고해 `data/config.php`를 준비합니다.
3. `data/config.php`의 `initial_admin_password`를 설치 승인용 비밀번호로 설정합니다.
4. 브라우저에서 `/install.php`에 접속합니다.
5. 설치 승인 비밀번호와 새 관리자 비밀번호를 입력해 설치합니다.

설치가 완료되면 `data/database.sqlite`가 생성됩니다. 이 파일은 출석 기록과 관리자 정보를 담으므로 Git에 포함하지 않습니다.

로컬 테스트 서버 예시:

```bash
php -S localhost:8000
```

## 설정

주요 설정은 `data/config.php`에서 관리합니다.

- 기본 정보: 앱 이름, 버전, 개발자 표시명, 개발자 링크, 시간대
- 업데이트: 업데이트 확인용 GitHub 저장소 소유자와 저장소 이름
- 경로: SQLite DB 경로, 스키마 파일 경로
- 인증/설치: 로그인 토큰 만료 시간, 설치 승인 비밀번호, 관리자 비밀번호 길이
- 학생 입력 제한: 학번/이름 길이
- 화면 동기화: 서버 시간/서버 정보 갱신 간격
- 위치 인증 기본값: 사용 여부, 중심 좌표, 반경, 위치 요청 제한 시간

## 주요 기능

- `/`: 학생 출석 화면
- `/install.php`: 초기 설치 마법사
- `/admin/`: 관리자 로그인
- `/admin/dash.php`: 대시보드
- `/admin/list.php`: 출석 목록, 위치 인증 상태 조회, 선택 삭제, 내보내기
- `/admin/location.php`: 출석 가능 위치 설정
- `/admin/edit.php?id=1`: 출석 기록 수정
- `/admin/system.php`: 초기화, 업데이트, 재설치(복구), 서버 정보 확인
- `/admin/password.php`: 관리자 비밀번호 변경

## 위치 인증

위치 인증을 켜면 출석 시 브라우저 위치 권한을 요청합니다. 출석 가능 여부는 서버에서 검증하며, 공개 상태 API에는 중심 좌표와 반경을 내려주지 않습니다. 출석 가능 반경 밖이거나 위치 권한을 사용할 수 없으면 출석 요청을 기록하고 관리자 승인 이후 정상 출결로 처리할 수 있습니다.

관리자는 출석 목록에서 위치 인증 필터로 상태를 조회하고, 테이블의 위치 인증 배지를 눌러 상세 위치와 거리를 확인할 수 있습니다.

## 업데이트

시스템 관리 화면에서 GitHub 릴리즈를 확인하고 업데이트할 수 있습니다.
현재 버전 파일을 다시 내려받아 덮어쓰는 재설치(복구)도 지원합니다. 이 작업은 DB와 `data/config.php`를 보존합니다.

업데이트 정책:

- `data/config.php`는 사용자 설정 보존을 위해 덮어쓰지 않습니다.
- SQLite DB 파일과 `data/updates/`, `data/backups/`는 덮어쓰지 않습니다.
- `data/schema.sql`은 업데이트 대상이며, 업데이트 후 현재 DB에 한 번 적용합니다.
- 업데이트 완료 후 `config.php`의 `app_version` 값만 설치된 버전으로 갱신합니다.
- 릴리즈에 없는 기존 파일은 삭제하지 않고, 릴리즈에 포함된 파일만 덮어씁니다.

## API

모든 API는 JSON 응답을 사용합니다.

```json
{
  "status": 1,
  "msg": "성공적으로 처리되었습니다.",
  "time": "2026-06-18 12:00:00",
  "result": null
}
```

주요 API:

- `GET /api/status.php`
- `POST /api/install.php`
- `POST /api/attend.php`
- `POST /api/admin-login.php`
- `POST /api/admin-logout.php`
- `POST /api/admin-summary.php`
- `POST /api/admin-list.php`
- `POST /api/admin-edit.php`
- `POST /api/admin-location.php`
- `POST /api/admin-system.php`
- `POST /api/admin-password.php`
- `POST /api/admin-verify.php`

설치 API 입력:

```json
{
  "install_password": "config의 initial_admin_password",
  "password": "새 관리자 비밀번호"
}
```

설치 화면에서는 새 관리자 비밀번호 확인을 브라우저에서 먼저 검사한 뒤 API에는 `password` 값만 보냅니다.

관리자 로그인 이후 API는 `Authorization: Bearer 관리자토큰` 헤더 또는 관리자 쿠키를 사용합니다.

## 구조

```txt
App/
  Controller.php
  Database.php

admin/
  index.php
  dash.php
  list.php
  location.php
  system.php
  edit.php
  password.php

api/
  install.php
  status.php
  attend.php
  admin-*.php

assets/
  app.js
  attendance-location.js
  install.js
  public-utils.js
  admin.js
  admin-login.js
  public.css
  styles.css

data/
  config.php
  config.example.php
  schema.sql
  database.sqlite

templates/
  public/
  admin/
```

로컬 정적 리소스는 앱 버전을 붙인 `?v={현재버전}` 캐시버스터로 로드됩니다.
