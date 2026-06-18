<div class="admin-page-header">
  <div>
    <p class="section-kicker">System</p>
    <h1>시스템 관리</h1>
    <p>초기화와 업데이트처럼 영향 범위가 큰 작업을 한곳에서 확인하고 실행합니다.</p>
  </div>
</div>

<div id="adminAlert"></div>

<div class="system-grid">
  <div class="system-stack">
    <section class="admin-card form-card system-card">
      <div class="system-card-heading">
        <div>
          <span class="section-kicker">Reset</span>
          <h2>초기화</h2>
        </div>
        <i class="bi bi-arrow-counterclockwise"></i>
      </div>

      <form id="systemResetForm">
        <fieldset class="system-option-list">
          <legend class="form-label">초기화 범위</legend>
          <label class="system-option">
            <input class="form-check-input" id="resetAttendanceOnlyInput" type="radio" name="reset_scope" value="attendance" checked>
            <span>
              <strong>출석 기록만 초기화</strong>
              <small>출석 데이터와 출석 번호만 초기화하고 위치 설정과 관리자 비밀번호는 유지합니다.</small>
            </span>
          </label>
          <label class="system-option">
            <input class="form-check-input" id="resetAllSettingsInput" type="radio" name="reset_scope" value="all">
            <span>
              <strong>모든 설정 초기화</strong>
              <small>출석 기록, 저장된 앱 설정, 관리자 계정과 로그인 세션을 모두 삭제해 초기 설치 상태로 되돌립니다.</small>
            </span>
          </label>
        </fieldset>

        <div class="mt-3">
          <label class="form-label" for="systemResetPasswordInput">관리자 비밀번호</label>
          <div class="password-field">
            <input class="form-control" id="systemResetPasswordInput" type="password" autocomplete="current-password" required>
            <button class="password-toggle-button" type="button" data-password-toggle="systemResetPasswordInput" aria-label="비밀번호 표시">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="system-actions">
          <button class="btn btn-danger" id="systemResetButton" type="submit">
            <i class="bi bi-exclamation-triangle me-1"></i> 초기화 실행
          </button>
        </div>
      </form>
    </section>

    <section class="admin-card form-card system-card">
      <div class="system-card-heading">
        <div>
          <span class="section-kicker">Server</span>
          <h2>서버 정보</h2>
        </div>
        <i class="bi bi-hdd-network"></i>
      </div>

      <div class="server-info-list" id="serverInfoList">
        <div class="empty-table-state">
          <div class="loading-spinner" aria-hidden="true"></div>
          <strong>서버 정보를 불러오는 중입니다.</strong>
        </div>
      </div>
    </section>
  </div>

  <section class="admin-card form-card system-card system-update-card">
    <div class="system-card-heading">
      <div>
        <span class="section-kicker">Update & Repair</span>
        <h2>업데이트/복구</h2>
      </div>
      <i class="bi bi-cloud-download"></i>
    </div>

    <div class="update-status-box" id="updateStatusBox">
      <span>현재 버전</span>
      <strong id="currentVersionText"><?php echo $h($app->string('app_version')); ?></strong>
      <small id="latestVersionText">릴리즈 정보를 확인해주세요.</small>
    </div>
    <p class="form-text mt-2 mb-0">업데이트 저장소는 <code>data/config.php</code>의 <code>update_repository_owner</code>, <code>update_repository_name</code> 값으로 설정합니다.</p>

    <div class="system-actions">
      <button class="btn btn-outline-success" id="updateCheckButton" type="button">
        <i class="bi bi-arrow-repeat me-1"></i> 업데이트 확인
      </button>
    </div>

    <form id="updateInstallForm" class="update-install-form" hidden>
      <label class="form-label" for="releaseSelect">설치할 버전</label>
      <select class="form-select" id="releaseSelect"></select>

      <label class="form-label mt-3" for="updatePasswordInput">관리자 비밀번호</label>
      <div class="password-field">
        <input class="form-control" id="updatePasswordInput" type="password" autocomplete="current-password" required>
        <button class="password-toggle-button" type="button" data-password-toggle="updatePasswordInput" aria-label="비밀번호 표시">
          <i class="bi bi-eye"></i>
        </button>
      </div>

      <div class="system-actions">
        <button class="btn btn-success" id="updateInstallButton" type="submit">
          <i class="bi bi-download me-1"></i> 업데이트
        </button>
      </div>
    </form>

    <div class="release-list" id="releaseList"></div>

    <form id="repairInstallForm" class="repair-install-form">
      <div>
        <span class="section-kicker">Repair</span>
        <h3>현재 버전 재설치(복구)</h3>
        <p class="form-text mb-3">현재 버전 파일을 다시 내려받아 덮어씁니다. 데이터베이스와 <code>data/config.php</code>는 보존합니다.</p>
      </div>

      <label class="form-label" for="repairPasswordInput">관리자 비밀번호</label>
      <div class="password-field">
        <input class="form-control" id="repairPasswordInput" type="password" autocomplete="current-password" required>
        <button class="password-toggle-button" type="button" data-password-toggle="repairPasswordInput" aria-label="비밀번호 표시">
          <i class="bi bi-eye"></i>
        </button>
      </div>

      <div class="system-actions">
        <button class="btn btn-outline-success" id="repairInstallButton" type="submit">
          <i class="bi bi-arrow-clockwise me-1"></i> 재설치(복구)
        </button>
      </div>
    </form>
  </section>
</div>

<div class="admin-modal" id="releaseDetailModal" hidden>
  <section class="admin-modal-dialog release-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="releaseDetailTitle">
    <div class="admin-modal-header">
      <h2 id="releaseDetailTitle">릴리즈 정보</h2>
      <button class="btn btn-sm btn-outline-secondary" id="closeReleaseDetailButton" type="button" aria-label="닫기">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="release-detail-meta" id="releaseDetailMeta"></div>
    <pre class="release-detail-body" id="releaseDetailBody"></pre>
  </section>
</div>

<div class="admin-modal" id="updateProgressModal" hidden>
  <section class="admin-modal-dialog update-progress-dialog" role="dialog" aria-modal="true" aria-labelledby="updateProgressTitle">
    <div class="admin-modal-header">
      <h2 id="updateProgressTitle">업데이트 중</h2>
    </div>
    <div class="update-progress-bar" aria-hidden="true">
      <span id="updateProgressFill"></span>
    </div>
    <ol class="update-progress-steps" id="updateProgressSteps"></ol>
  </section>
</div>
