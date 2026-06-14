<div class="admin-page-header">
  <div>
    <p class="section-kicker">Attendance Admin</p>
    <h1>관리자 대시보드</h1>
    <p>오늘 출석과 전체 기록을 한눈에 확인합니다.</p>
  </div>
</div>

<div class="row g-3 align-items-stretch mb-3">
  <div class="col-md-4">
    <section class="stat-card">
      <span>오늘 출석</span>
      <strong id="todayCount">0건</strong>
    </section>
  </div>
  <div class="col-md-4">
    <section class="stat-card">
      <span>전체 출석</span>
      <strong id="totalCount">0건</strong>
    </section>
  </div>
  <div class="col-md-4">
    <section class="stat-card">
      <span>서버 시간</span>
      <strong class="fs-5" id="summaryServerTime">-</strong>
    </section>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <a class="admin-action-card" href="/admin/list.php">
      <i class="bi bi-calendar-check"></i>
      <span>
        <strong>출석 목록</strong>
        <small>날짜별 출석 기록을 조회하고 수정 또는 삭제합니다.</small>
      </span>
    </a>
  </div>
  <div class="col-lg-6">
    <a class="admin-action-card" href="/admin/password.php">
      <i class="bi bi-shield-lock"></i>
      <span>
        <strong>비밀번호 변경</strong>
        <small>비밀번호 변경 후 기존 로그인 토큰을 만료합니다.</small>
      </span>
    </a>
  </div>
</div>
