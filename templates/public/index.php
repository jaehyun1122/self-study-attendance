<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $h($app->string('app_name')); ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="attendance-body">
  <main class="attendance-shell">
    <section class="attendance-hero">
      <div class="attendance-hero-copy">
        <span class="section-kicker">Self Study Attendance</span>
        <h1>자습 출석 체크</h1>
        <p>학번과 이름을 저장한 뒤 한 번의 클릭으로 출석을 기록합니다.</p>
      </div>
      <div class="attendance-clock">
        <i class="bi bi-clock"></i>
        <span id="serverTime">현재시간: 불러오는 중...</span>
      </div>
    </section>

    <section class="attendance-card" aria-live="polite">
      <div class="attendance-card-header">
        <div>
          <span class="section-kicker">Check In</span>
          <h2>출석 정보</h2>
        </div>
        <button class="btn btn-light icon-round" id="infoButton" type="button" aria-label="시스템 정보">
          <i class="bi bi-info-lg"></i>
        </button>
      </div>

      <section id="studentFormView">
        <form id="studentForm">
          <div class="mb-3">
            <label class="form-label" for="studentNoInput">학번</label>
            <input class="form-control form-control-lg" id="studentNoInput" name="student_no" autocomplete="off" inputmode="numeric" maxlength="<?php echo $h($app->int('student_no_length', 5)); ?>" minlength="<?php echo $h($app->int('student_no_length', 5)); ?>" placeholder="10101" required>
            <div class="form-text">학번은 <?php echo $h($app->int('student_no_length', 5)); ?>자로 입력해주세요.</div>
          </div>
          <div class="mb-4">
            <label class="form-label" for="studentNameInput">이름</label>
            <input class="form-control form-control-lg" id="studentNameInput" name="name" autocomplete="name" maxlength="<?php echo $h($app->int('student_name_max_length', 5)); ?>" placeholder="홍길동" required>
            <div class="form-text">이름은 <?php echo $h($app->int('student_name_max_length', 5)); ?>자까지 입력할 수 있습니다.</div>
          </div>
          <button class="btn btn-success btn-lg w-100" type="submit">
            <i class="bi bi-person-check me-1"></i> 저장하고 시작
          </button>
        </form>
      </section>

      <section id="attendanceView" hidden>
        <div class="student-chip">
          <span>학생</span>
          <strong id="studentText"></strong>
        </div>
        <button class="btn btn-success btn-lg w-100 attendance-submit" id="attendButton" type="button">
          <i class="bi bi-check2-circle me-1"></i> 출석하기
        </button>
        <button class="btn btn-link link-success d-block mx-auto text-decoration-none mt-3" id="changeStudentButton" type="button">학생 정보 변경</button>
      </section>

      <section id="resultView" hidden>
        <div class="text-center py-4 result-card" id="resultCard">
          <div class="result-icon mx-auto mb-3" id="resultIcon">✓</div>
          <h2 class="h4" id="resultTitle">출석 완료</h2>
          <p class="fw-bold mb-1" id="resultStudent"></p>
          <p class="text-secondary mb-3" id="resultTime"></p>
          <p class="text-secondary" id="resultMessage"></p>
          <button class="btn btn-outline-secondary" id="backButton" type="button">돌아가기</button>
        </div>
      </section>
    </section>
  </main>

  <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <section class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="infoTitle">시스템 정보</h2>
          <button class="btn-close" id="closeInfoButton" type="button" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>
        <div class="modal-body">
          <dl class="info-list" id="infoList">
            <div><dt>상태</dt><dd>불러오는 중...</dd></div>
          </dl>
        </div>
      </section>
    </div>
  </div>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="/assets/app.js"></script>
</body>
</html>
