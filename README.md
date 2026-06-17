# 학교 자습 출결 체크 시스템

학번과 이름으로 자습 출석을 기록하고, 관리자 페이지에서 날짜별 출석 내역을 확인·수정·삭제할 수 있는 PHP + SQLite 기반 출결 체크 시스템입니다.

공개 저장소: https://github.com/jaehyun1122/self-study-attendance

## 요구 환경

- PHP
- PHP `pdo_sqlite` 확장
- 웹 서버의 `data/` 폴더 쓰기 권한

환경 설정은 `data/config.php`에서 관리합니다. 앱 이름/버전, 공개 저장소 URL, 시간대, DB 경로, 스키마 경로, 토큰 만료 시간, 비밀번호/학번/이름 길이 범위처럼 실제로 바꿀 가능성이 있는 값만 둡니다.

## 설치

1. 프로젝트를 웹 서버에 배포합니다.
2. `data/` 폴더에 쓰기 권한을 부여합니다.
3. 설치 API로 단일 관리자 초기 비밀번호를 설정합니다.

```bash
curl -X POST http://localhost:8000/api/install.php \
  -H "Content-Type: application/json" \
  -d "{\"password\":\"admin1234\"}"
```

설치 시 `data/schema.sql`을 읽어 SQLite 테이블을 생성합니다. `data/database.sqlite`는 런타임에 생성되며 Git에는 포함하지 않습니다.

로컬 테스트 서버 예시:

```bash
php -S localhost:8000
```

## 주요 기능

- `/` 학생 출석 화면
- 학생 정보 로컬 저장 후 AJAX 출석 처리
- 같은 날짜, 같은 학번 중복 출석 방지
- 화면 출석일은 `YYYY-MM-DD HH:mm:ss` 형식으로 표시
- 학번은 숫자 5자리만 입력 가능하고, 이름은 Unicode 기준 1~10자까지 입력 가능
- 관리자 비밀번호 로그인과 토큰 기반 API 인증
- 관리자 대시보드, 목록, 수정/삭제, 비밀번호 변경을 AJAX로 처리
- 관리자 권한 없이 보호 페이지 접근 시 `/admin/?reason=login-required`로 리다이렉션

## 관리자 페이지

- `/admin/`: 관리자 로그인
- `/admin/dash.php`: 관리자 대시보드
- `/admin/list.php`: 날짜별 출석 목록
- `/admin/location.php`: 출석 가능 위치 설정
- `/admin/edit.php?id=1`: 출석 기록 수정
- `/admin/password.php`: 관리자 비밀번호 변경

기존 `index.html`은 `/`로 이동하는 호환용 리다이렉트 파일입니다. 관리자 보호 페이지의 HTML은 `templates/admin/`에 보관하고, 데이터 조회와 변경은 `assets/admin.js`에서 AJAX로 처리합니다.

## API

모든 API는 아래 JSON 형식을 사용합니다.

```json
{
  "status": 1,
  "msg": "성공적으로 처리되었습니다.",
  "time": "2026-04-05 12:00:00",
  "result": null
}
```

API 목록:

- `POST /api/install.php`
- `GET /api/status.php`
- `POST /api/attend.php`
- `POST /api/admin-login.php`
- `POST /api/admin-logout.php`
- `POST /api/admin-list.php`
- `POST /api/admin-edit.php`
- `POST /api/admin-location.php`
- `POST /api/admin-settings.php`
- `POST /api/admin-verify.php`
- `POST /api/admin-password.php`
- `POST /api/admin-summary.php`

관리자 로그인 이후 API는 아래 헤더가 필요합니다.

```txt
Authorization: Bearer 관리자토큰
```

## 구조

```txt
App/
  Controller.php
  Database.php

admin/
  index.php
  dash.php
  list.php
  edit.php
  password.php

api/
  install.php
  status.php
  attend.php
  admin-login.php
  admin-logout.php
  admin-list.php
  admin-edit.php
  admin-location.php
  admin-settings.php
  admin-verify.php
  admin-password.php
  admin-summary.php

assets/
  app.js
  admin.js
  admin-login.js
  styles.css

data/
  config.php
  schema.sql
  database.sqlite

templates/
  public/
    index.php
  admin/
    login.php
    layout.php
    dash.php
    list.php
    edit.php
    password.php
```

`App/Controller.php`는 설정, JSON 응답, DB 접근, 관리자 인증, 템플릿 렌더링처럼 2곳 이상 반복되는 공통 기능만 담습니다. 개별 API와 관리자 페이지의 실제 업무 처리는 각 PHP 파일 안에 가깝게 둡니다.
