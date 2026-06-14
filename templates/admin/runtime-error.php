<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PHP 버전 오류</title>
  <link rel="icon" type="image/png" href="/assets/logo.png">
  <link rel="apple-touch-icon" href="/assets/logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="bg-body-tertiary">
  <main class="container py-5">
    <section class="p-4 bg-white border rounded-3 shadow-sm">
      <div class="auth-logo-line">
        <img class="brand-logo" src="/assets/logo.png" width="24" height="24" alt="" aria-hidden="true">
        <p class="text-success fw-bold text-uppercase small mb-0">Runtime Error</p>
      </div>
      <h1 class="h3">PHP <?php echo $h($required); ?> 이상이 필요합니다.</h1>
      <p class="text-secondary">현재 서버 PHP 버전: <?php echo $h($current); ?></p>
      <a class="btn btn-success" href="/admin/">로그인 페이지로 이동</a>
    </section>
  </main>
</body>
</html>
