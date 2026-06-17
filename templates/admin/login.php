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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="<?php echo $h($asset('/assets/styles.css')); ?>">
</head>
<body class="bg-body-tertiary">
  <main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <section class="card border-0 shadow-sm w-100 auth-card">
      <div class="card-body p-4">
        <div class="auth-logo-line">
          <img class="brand-logo" src="<?php echo $h($asset('/assets/logo.png')); ?>" width="24" height="24" alt="" aria-hidden="true">
          <p class="text-success fw-bold text-uppercase small mb-0">Admin</p>
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

        <p class="small-note" id="installNotice"></p>
        <p class="small-note version-note">현재 버전 <?php echo $h($app->string('app_version')); ?></p>
      </div>
    </section>
  </main>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="<?php echo $h($asset('/assets/admin-login.js')); ?>"></script>
</body>
</html>
