<div class="admin-page-header">
  <div>
    <p class="section-kicker">System</p>
    <h1>시스템 관리</h1>
    <p>초기화와 업데이트처럼 영향 범위가 큰 작업을 한곳에서 확인하고 실행합니다.</p>
  </div>
</div>

<div id="adminAlert"></div>

<div class="system-grid">
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
            <small>출석 기록과 저장된 앱 설정을 초기화합니다. 관리자 비밀번호는 유지합니다.</small>
          </span>
        </label>
      </fieldset>

      <div class="mt-3">
        <label class="form-label" for="systemResetPasswordInput">관리자 비밀번호</label>
        <input class="form-control" id="systemResetPasswordInput" type="password" autocomplete="current-password" required>
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
        <span class="section-kicker">Update</span>
        <h2>업데이트</h2>
      </div>
      <i class="bi bi-cloud-download"></i>
    </div>

    <div class="update-status-box" id="updateStatusBox">
      <span>현재 버전</span>
      <strong id="currentVersionText"><?php echo $h($app->string('app_version')); ?></strong>
      <small id="latestVersionText">릴리즈 정보를 확인해주세요.</small>
    </div>

    <div class="system-actions">
      <button class="btn btn-outline-success" id="updateCheckButton" type="button">
        <i class="bi bi-arrow-repeat me-1"></i> 업데이트 확인
      </button>
    </div>

    <form id="updateInstallForm" class="update-install-form" hidden>
      <label class="form-label" for="releaseSelect">설치할 버전</label>
      <select class="form-select" id="releaseSelect"></select>

      <label class="form-label mt-3" for="updatePasswordInput">관리자 비밀번호</label>
      <input class="form-control" id="updatePasswordInput" type="password" autocomplete="current-password" required>

      <div class="system-actions">
        <button class="btn btn-success" id="updateInstallButton" type="submit">
          <i class="bi bi-download me-1"></i> 다운로드 후 업그레이드
        </button>
      </div>
    </form>

    <div class="release-list" id="releaseList"></div>
  </section>
</div>
