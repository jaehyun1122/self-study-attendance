<div class="admin-page-header">
  <div>
    <p class="section-kicker">Attendance Location</p>
    <h1>위치 설정</h1>
    <p>교내 출석 가능 위치와 허용 반경을 관리합니다.</p>
  </div>
</div>

<section class="admin-card form-card location-form-card">
  <form id="locationForm">
    <div class="form-check form-switch mb-4">
      <input class="form-check-input" id="locationEnabledInput" type="checkbox" role="switch">
      <label class="form-check-label fw-semibold location-enabled-label" for="locationEnabledInput">
        <span>위치 기반 출석</span>
        <span class="badge text-bg-secondary location-enabled-state" id="locationEnabledState">미사용</span>
      </label>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="latitudeInput">중심 위도</label>
        <input class="form-control form-control-lg" id="latitudeInput" type="number" inputmode="decimal" step="0.000001" min="-90" max="90" placeholder="37.123456">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="longitudeInput">중심 경도</label>
        <input class="form-control form-control-lg" id="longitudeInput" type="number" inputmode="decimal" step="0.000001" min="-180" max="180" placeholder="127.123456">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="radiusInput">허용 반경</label>
        <div class="input-group input-group-lg">
          <input class="form-control" id="radiusInput" type="number" inputmode="numeric" min="10" max="5000" step="10" value="150">
          <span class="input-group-text">m</span>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="timeoutInput">위치 확인 제한 시간</label>
        <div class="input-group input-group-lg">
          <input class="form-control" id="timeoutInput" type="number" inputmode="numeric" min="3" max="60" step="1" value="10">
          <span class="input-group-text">초</span>
        </div>
      </div>
    </div>

    <div class="location-setting-actions mt-4">
      <button class="btn btn-outline-success" id="useCurrentLocationButton" type="button">
        <i class="bi bi-crosshair me-1"></i> 현재 위치 사용
      </button>
      <button class="btn btn-success px-4" id="saveLocationButton" type="submit">저장</button>
    </div>
  </form>

  <div class="location-map-panel">
    <div class="location-map-heading">
      <strong>출석 가능 범위</strong>
      <span>지도를 클릭하면 중심 위치가 바뀝니다.</span>
    </div>
    <div class="location-map" id="locationSettingsMap" aria-label="출석 가능 위치 지도"></div>
  </div>
</section>
