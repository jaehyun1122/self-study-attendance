<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>관리자 로그인 - <?php echo $h($app->string('app_name')); ?></title>
  <link rel="icon" type="image/png" href="<?php echo $h($asset('/assets/logo.png')); ?>">
  <link rel="apple-touch-icon" href="<?php echo $h($asset('/assets/logo.png')); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css">
  <link rel="stylesheet" href="<?php echo $h($asset('/assets/styles.css')); ?>">
</head>
<body class="bg-body-tertiary">
  <main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <section class="card border-0 shadow-sm w-100 auth-card">
      <div class="card-body p-4">
        <div class="auth-logo-line">
          <img class="brand-logo" src="<?php echo $h($asset('/assets/logo.png')); ?>" width="24" height="24" alt="" aria-hidden="true">
          <p class="text-success fw-bold text-uppercase small mb-0">Admin</p>
          <button class="theme-toggle-button" type="button" data-theme-toggle aria-label="현재 테마: 시스템. 밝게 모드로 전환" title="현재 테마: 시스템. 밝게 모드로 전환">
            <i class="bi bi-circle-half"></i>
          </button>
        </div>
        <h1 class="h3 mb-2">관리자 로그인</h1>
        <p class="text-secondary">관리자 비밀번호를 입력해주세요.</p>

        <form id="adminLoginForm">
          <div class="mb-3">
            <label class="form-label" for="adminPasswordInput">비밀번호</label>
            <div class="password-field">
              <input class="form-control form-control-lg" id="adminPasswordInput" type="password" autocomplete="current-password" required>
              <button class="password-toggle-button" type="button" data-password-toggle="adminPasswordInput" aria-label="비밀번호 표시">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <button class="btn btn-success btn-lg w-100" id="loginButton" type="submit">로그인</button>
        </form>

        <button class="forgot-password-link" id="forgotPasswordButton" type="button">비밀번호를 잊으셨나요?</button>
        <p class="small-note" id="installNotice"></p>
        <p class="small-note version-note">현재 버전 <?php echo $h($app->string('app_version')); ?></p>
      </div>
    </section>
  </main>

  <div class="admin-modal" id="forgotPasswordModal" hidden>
    <section class="admin-modal-dialog forgot-password-dialog" role="dialog" aria-modal="true" aria-labelledby="forgotPasswordTitle">
      <div class="admin-modal-header">
        <h2 id="forgotPasswordTitle">비밀번호 재설정 안내</h2>
        <button class="btn btn-sm btn-outline-secondary" id="closeForgotPasswordButton" type="button" aria-label="닫기">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <p>관리자 비밀번호는 서버에서 다시 설정할 수 있습니다.</p>
      <p>서버 터미널에서 프로젝트 폴더로 이동한 뒤 아래 명령어를 실행해주세요.</p>
      <div class="password-reset-command-row">
        <code class="password-reset-command" id="passwordResetCommand">php cli/reset-admin-password.php</code>
        <button class="password-reset-copy-button" id="copyPasswordResetCommandButton" type="button" aria-label="명령어 복사" title="명령어 복사">
          <i class="bi bi-copy" aria-hidden="true"></i>
        </button>
      </div>
      <p class="form-text mb-0">보안 정책에 따라 비밀번호 재설정 후에는 기존 로그인 세션이 모두 만료됩니다.</p>
    </section>
  </div>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0"></script>
  <script src="<?php echo $h($asset('/assets/public-utils.js')); ?>"></script>
  <script src="<?php echo $h($asset('/assets/admin-login.js')); ?>"></script>
</body>
</html>
