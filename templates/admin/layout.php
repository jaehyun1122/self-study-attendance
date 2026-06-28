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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css">
  <?php if (in_array(($active ?? ''), ['list', 'location'], true)): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="<?php echo $h($asset('/assets/styles.css')); ?>">
</head>
<body class="bg-body-tertiary" data-auto-refresh-seconds="<?php echo $h((string) max(0, min(86400, $app->int('auto_refresh_seconds', 5)))); ?>">
  <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top d-lg-none">
    <div class="container py-2">
      <a class="navbar-brand admin-brand-inline text-success" href="/admin/dash.php">
        <img class="brand-logo" src="<?php echo $h($asset('/assets/logo.png')); ?>" width="24" height="24" alt="" aria-hidden="true">
        <span><?php echo $h($app->string('app_name')); ?></span>
      </a>
      <div class="admin-mobile-actions">
        <button class="theme-toggle-button" type="button" data-theme-toggle aria-label="현재 테마: 시스템. 밝게 모드로 전환" title="현재 테마: 시스템. 밝게 모드로 전환">
          <i class="bi bi-circle-half"></i>
        </button>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="메뉴 열기">
          <span class="navbar-toggler-icon"></span>
        </button>
      </div>
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
        <div class="admin-sidebar-footer-meta">
          <div class="admin-developer-meta">
            <span>개발자: <a href="<?php echo $h($app->string('powered_by_url', $app->string('repository_url'))); ?>" target="_blank" rel="noopener noreferrer"><?php echo $h($app->string('developer_name', $app->string('powered_by'))); ?></a></span>
            <span>현재 버전 <?php echo $h($app->string('app_version')); ?></span>
          </div>
          <button class="theme-toggle-button" type="button" data-theme-toggle aria-label="현재 테마: 시스템. 밝게 모드로 전환" title="현재 테마: 시스템. 밝게 모드로 전환">
            <i class="bi bi-circle-half"></i>
          </button>
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0"></script>
  <?php if (in_array(($active ?? ''), ['list', 'location'], true)): ?>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
  <?php endif; ?>
  <?php if (($active ?? '') === 'dash'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <?php endif; ?>
  <script src="<?php echo $h($asset('/assets/public-utils.js')); ?>"></script>
  <script src="<?php echo $h($asset('/assets/admin.js')); ?>"></script>
</body>
</html>
