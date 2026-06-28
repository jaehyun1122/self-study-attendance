<?php
  $passwordRange = $app->lengthRange('password_length', 4, 32);
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>초기 설치 - <?php echo $h($app->string('app_name')); ?></title>
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
          <button class="theme-toggle-button" type="button" data-theme-toggle aria-label="다크 모드로 전환" title="다크 모드로 전환">
            <i class="bi bi-moon-stars"></i>
          </button>
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
          <p><code>data/config.php</code>의 <code>initial_admin_password</code> 값으로 설치를 승인한 뒤, 실제 관리자 비밀번호를 새로 설정합니다.</p>
          <form id="installForm">
            <div class="mb-3">
              <label class="form-label" for="installPasswordInput">설치 승인 비밀번호</label>
              <div class="password-field">
                <input class="form-control form-control-lg" id="installPasswordInput" type="password" autocomplete="current-password" required>
                <button class="password-toggle-button" type="button" data-password-toggle="installPasswordInput" aria-label="비밀번호 표시">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="adminPasswordInput">새 관리자 비밀번호</label>
              <div class="password-field">
                <input class="form-control form-control-lg" id="adminPasswordInput" type="password" autocomplete="new-password" minlength="<?php echo $h($passwordRange['min']); ?>" maxlength="<?php echo $h($passwordRange['max']); ?>" required>
                <button class="password-toggle-button" type="button" data-password-toggle="adminPasswordInput" aria-label="비밀번호 표시">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text"><?php echo $h($app->lengthRequirementText('관리자 비밀번호는', 'password_length', 4, 32)); ?></div>
            </div>
            <div class="mb-4">
              <label class="form-label" for="adminPasswordConfirmInput">새 관리자 비밀번호 확인</label>
              <div class="password-field">
                <input class="form-control form-control-lg" id="adminPasswordConfirmInput" type="password" autocomplete="new-password" minlength="<?php echo $h($passwordRange['min']); ?>" maxlength="<?php echo $h($passwordRange['max']); ?>" required>
                <button class="password-toggle-button" type="button" data-password-toggle="adminPasswordConfirmInput" aria-label="비밀번호 표시">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <button class="btn btn-success btn-lg w-100" id="installButton" type="submit">
              <i class="bi bi-magic me-1"></i> 설치 시작
            </button>
          </form>
        </div>
      </section>
    </section>
  </main>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0"></script>
  <script src="<?php echo $h($asset('/assets/public-utils.js')); ?>"></script>
  <script src="<?php echo $h($asset('/assets/install.js')); ?>"></script>
</body>
</html>
