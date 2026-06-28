<?php
  $studentNoRange = $app->lengthRange('student_no_length', 5, 5);
  $studentNoPattern = $studentNoRange['min'] === $studentNoRange['max']
    ? '\d{' . $studentNoRange['min'] . '}'
    : '\d{' . $studentNoRange['min'] . ',' . $studentNoRange['max'] . '}';
  $studentNameRange = $app->lengthRange('student_name_length', 1, 10);
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $h($app->string('app_name')); ?></title>
  <link rel="icon" type="image/png" href="<?php echo $h($asset('/assets/logo.png')); ?>">
  <link rel="apple-touch-icon" href="<?php echo $h($asset('/assets/logo.png')); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css">
  <link rel="stylesheet" href="<?php echo $h($asset('/assets/styles.css')); ?>">
  <link rel="stylesheet" href="<?php echo $h($asset('/assets/public.css')); ?>">
</head>
<body class="attendance-body">
  <main class="attendance-shell">
    <section class="attendance-panel" aria-live="polite">
      <header class="attendance-panel-header">
        <div class="public-logo-line">
          <img class="brand-logo" src="<?php echo $h($asset('/assets/logo.png')); ?>" width="24" height="24" alt="" aria-hidden="true">
          <span class="section-kicker">Self Study Attendance</span>
          <span class="app-version"><?php echo $h($app->string('app_version')); ?></span>
          <button class="theme-toggle-button" type="button" data-theme-toggle aria-label="현재 테마: 시스템. 밝게 모드로 전환" title="현재 테마: 시스템. 밝게 모드로 전환">
            <i class="bi bi-circle-half"></i>
          </button>
        </div>
        <div class="attendance-heading-copy">
          <h1>자습 출석 체크</h1>
          <p>학번과 이름을 저장한 뒤 한 번의 클릭으로 출석을 기록합니다.</p>
        </div>
        <div class="attendance-panel-tools">
          <div class="attendance-clock">
            <i class="bi bi-clock"></i>
            <span id="serverTime">현재시간: 불러오는 중...</span>
          </div>
        </div>
      </header>

      <section id="studentFormView">
        <div class="attendance-step-heading">
          <span class="section-kicker">Check In</span>
          <h2>출석 정보</h2>
        </div>
        <form id="studentForm">
          <div class="mb-3">
            <label class="form-label" for="studentNoInput">학번</label>
            <input class="form-control form-control-lg" id="studentNoInput" name="student_no" autocomplete="off" inputmode="numeric" pattern="<?php echo $h($studentNoPattern); ?>" maxlength="<?php echo $h($studentNoRange['max']); ?>" minlength="<?php echo $h($studentNoRange['min']); ?>" placeholder="10101" required>
            <div class="form-text"><?php echo $h($app->lengthRequirementText('학번은', 'student_no_length', 5, 5)); ?></div>
          </div>
          <div class="mb-4">
            <label class="form-label" for="studentNameInput">이름</label>
            <input class="form-control form-control-lg" id="studentNameInput" name="name" autocomplete="name" maxlength="<?php echo $h($studentNameRange['max']); ?>" minlength="<?php echo $h($studentNameRange['min']); ?>" placeholder="홍길동" required>
            <div class="form-text"><?php echo $h($app->lengthRequirementText('이름은', 'student_name_length', 1, 10)); ?></div>
          </div>
          <button class="btn btn-success btn-lg w-100" type="submit">
            <i class="bi bi-person-check me-1"></i> 저장하고 시작
          </button>
        </form>
      </section>

      <section id="attendanceView" hidden>
        <div class="student-chip" id="studentChip">
          <span>학생 정보</span>
          <strong id="studentText"></strong>
        </div>
        <p class="attendance-location-notice" id="attendanceLocationNotice" hidden>
          <i class="bi bi-geo-alt"></i>
          <span>교내 출석 여부를 확인하기 위해 위치 권한이 필요합니다.</span>
        </p>
        <button class="btn btn-success btn-lg w-100 attendance-submit" id="attendButton" type="button">
          <i class="bi bi-check2-circle me-1"></i> 출석하기
        </button>
      </section>

      <section id="resultView" hidden>
        <div class="result-card" id="resultCard">
          <div class="result-icon" id="resultIcon">✓</div>
          <div class="result-copy">
            <h2 id="resultTitle">출석 완료</h2>
            <p class="result-student" id="resultStudent"></p>
            <p class="result-time" id="resultTime"></p>
            <p class="result-message" id="resultMessage"></p>
          </div>
          <button class="btn btn-outline-secondary w-100" id="backButton" type="button">돌아가기</button>
        </div>
      </section>
    </section>
  </main>

  <div class="student-edit-modal" id="studentEditModal" hidden>
    <section class="student-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="studentEditTitle">
      <h2 id="studentEditTitle">학생 정보 수정 확인</h2>
      <p>관리자 비밀번호를 확인한 뒤 학생 정보를 다시 입력할 수 있습니다.</p>
      <form id="studentEditVerifyForm">
        <label class="form-label" for="studentEditPasswordInput">관리자 비밀번호</label>
        <div class="password-field">
          <input class="form-control form-control-lg" id="studentEditPasswordInput" type="password" autocomplete="current-password" required>
          <button class="password-toggle-button" type="button" data-password-toggle="studentEditPasswordInput" aria-label="비밀번호 표시">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="student-edit-actions">
          <button class="btn btn-outline-secondary" id="cancelStudentEditButton" type="button">취소</button>
          <button class="btn btn-success" id="verifyStudentEditButton" type="submit">확인</button>
        </div>
      </form>
    </section>
  </div>

  <div class="student-edit-modal" id="locationConfirmModal" hidden>
    <section class="student-edit-dialog location-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="locationConfirmTitle">
      <h2 id="locationConfirmTitle">위치 인증 확인</h2>
      <p id="locationConfirmMessage">출석을 위해 현재 위치 인증이 필요합니다.</p>
      <div class="location-help-box" id="locationHelpBox" hidden></div>
      <div class="student-edit-actions location-confirm-actions">
        <button class="btn btn-outline-secondary" id="cancelLocationConfirmButton" type="button">취소</button>
        <button class="btn btn-outline-success" id="requestLocationAgainButton" type="button" hidden>다시 위치 요청</button>
        <button class="btn btn-success" id="confirmLocationOverrideButton" type="button" hidden>출석 계속하기</button>
      </div>
    </section>
  </div>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0"></script>
  <script src="<?php echo $h($asset('/assets/public-utils.js')); ?>"></script>
  <script src="<?php echo $h($asset('/assets/attendance-location.js')); ?>"></script>
  <script src="<?php echo $h($asset('/assets/app.js')); ?>"></script>
</body>
</html>
