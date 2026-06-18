<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $h($title ?? '관리자'); ?> - <?php echo $h($app->string('app_name')); ?></title>
  <link rel="icon" type="image/png" href="<?php echo $h($asset('/assets/logo.png')); ?>">
  <link rel="apple-touch-icon" href="<?php echo $h($asset('/assets/logo.png')); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
  <link rel="stylesheet" href="<?php echo $h($asset('/assets/styles.css')); ?>">
</head>
<body class="bg-body-tertiary">
  <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top d-lg-none">
    <div class="container py-2">
      <a class="navbar-brand admin-brand-inline text-success" href="/admin/dash.php">
        <img class="brand-logo" src="<?php echo $h($asset('/assets/logo.png')); ?>" width="24" height="24" alt="" aria-hidden="true">
        <span><?php echo $h($app->string('app_name')); ?></span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="메뉴 열기">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php foreach ($menu as $item): ?>
            <li class="nav-item">
              <a class="nav-link <?php echo ($active ?? '') === $item['key'] ? 'active fw-semibold' : ''; ?>" href="<?php echo $h($item['url']); ?>">
                <?php echo $h($item['label']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="admin-developer-meta d-lg-none">
          <span>개발자: <a href="<?php echo $h($app->string('powered_by_url', $app->string('repository_url'))); ?>" target="_blank" rel="noopener noreferrer"><?php echo $h($app->string('developer_name', $app->string('powered_by'))); ?></a></span>
          <span>현재 버전 <?php echo $h($app->string('app_version')); ?></span>
        </div>
        <button class="btn btn-outline-secondary btn-sm js-logout-button" type="button">로그아웃</button>
      </div>
    </div>
  </nav>

  <div class="admin-layout">
    <aside class="admin-sidebar d-none d-lg-flex">
      <div>
        <a class="admin-brand" href="/admin/dash.php">
          <img class="brand-logo" src="<?php echo $h($asset('/assets/logo.png')); ?>" width="24" height="24" alt="" aria-hidden="true">
          <span><?php echo $h($app->string('app_name')); ?></span>
        </a>
        <nav class="admin-menu" aria-label="관리자 메뉴">
          <?php foreach ($menu as $item): ?>
            <a class="admin-menu-link <?php echo ($active ?? '') === $item['key'] ? 'is-active' : ''; ?>" href="<?php echo $h($item['url']); ?>">
              <?php if ($item['key'] === 'dash'): ?><i class="bi bi-speedometer2"></i><?php endif; ?>
              <?php if ($item['key'] === 'list'): ?><i class="bi bi-table"></i><?php endif; ?>
              <?php if ($item['key'] === 'location'): ?><i class="bi bi-geo-alt"></i><?php endif; ?>
              <?php if ($item['key'] === 'system'): ?><i class="bi bi-tools"></i><?php endif; ?>
              <?php if ($item['key'] === 'password'): ?><i class="bi bi-shield-lock"></i><?php endif; ?>
              <span><?php echo $h($item['label']); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="admin-sidebar-footer">
        <div class="admin-developer-meta">
          <span>개발자: <a href="<?php echo $h($app->string('powered_by_url', $app->string('repository_url'))); ?>" target="_blank" rel="noopener noreferrer"><?php echo $h($app->string('developer_name', $app->string('powered_by'))); ?></a></span>
          <span>현재 버전 <?php echo $h($app->string('app_version')); ?></span>
        </div>
        <button class="btn btn-outline-secondary w-100 js-logout-button" type="button">
          <i class="bi bi-box-arrow-right me-1"></i> 로그아웃
        </button>
      </div>
    </aside>

    <main class="admin-content">
      <?php echo $content; ?>
    </main>
  </div>

  <div id="toastRoot" class="toast-root" aria-live="polite"></div>
  <script>window.ADMIN_TOKEN = <?php echo json_encode($adminToken ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
  <script src="<?php echo $h($asset('/assets/public-utils.js')); ?>"></script>
  <script src="<?php echo $h($asset('/assets/admin.js')); ?>"></script>
</body>
</html>
