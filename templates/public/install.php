<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>초기 설치 - <?php echo $h($app->string('app_name')); ?></title>
  <link rel="icon" type="image/png" href="/assets/logo.png">
  <link rel="apple-touch-icon" href="/assets/logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="/assets/styles.css">
  <link rel="stylesheet" href="/assets/public.css">
</head>
<body class="attendance-body">
  <main class="attendance-shell">
    <section class="attendance-panel" aria-live="polite">
      <header class="attendance-panel-header">
        <div class="public-logo-line">
          <img class="brand-logo" src="/assets/logo.png" width="24" height="24" alt="" aria-hidden="true">
          <span class="section-kicker">Self Study Attendance</span>
          <span class="app-version"><?php echo $h($app->string('app_version')); ?></span>
        </div>
        <div class="attendance-heading-copy">
          <h1>초기 설치</h1>
          <p>출석 시스템을 처음 사용할 수 있도록 데이터베이스와 관리자 계정을 준비합니다.</p>
        </div>
      </header>

      <section>
        <div class="attendance-step-heading">
          <span class="section-kicker">Install</span>
          <h2>설치 마법사</h2>
        </div>
        <div class="install-wizard-box">
          <p><code>data/config.php</code>의 <code>initial_admin_password</code> 값으로 초기 관리자 비밀번호를 설정합니다.</p>
          <button class="btn btn-success btn-lg w-100" id="installButton" type="button">
            <i class="bi bi-magic me-1"></i> 설치 시작
          </button>
        </div>
      </section>
    </section>
  </main>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="/assets/public-utils.js"></script>
  <script src="/assets/install.js"></script>
</body>
</html>
