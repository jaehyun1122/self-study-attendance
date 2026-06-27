<div class="admin-page-header">
  <div>
    <p class="section-kicker">Attendance Admin</p>
    <h1>관리자 대시보드</h1>
    <p>출석 현황과 학년별 통계를 한눈에 확인합니다.</p>
  </div>
  <div class="dashboard-server-time">
    <span>서버 시간</span>
    <strong id="summaryServerTime">-</strong>
  </div>
</div>

<div class="dashboard-stat-grid">
  <section class="stat-card">
    <span>오늘 출석</span>
    <strong id="todayCount">0건</strong>
  </section>
  <section class="stat-card">
    <span>전체 출석 기록</span>
    <strong id="totalCount">0건</strong>
  </section>
  <section class="stat-card">
    <span>기록된 학생</span>
    <strong id="studentCount">0명</strong>
  </section>
  <section class="stat-card">
    <span>오늘 출석률 <small>(기록 학생 기준)</small></span>
    <strong id="todayRate">0%</strong>
  </section>
  <section class="stat-card">
    <span>승인 대기</span>
    <strong id="pendingCount">0건</strong>
  </section>
</div>

<div class="dashboard-chart-grid">
  <section class="admin-card dashboard-chart-card dashboard-chart-wide">
    <div class="dashboard-chart-heading">
      <div>
        <span class="section-kicker">Last 14 days</span>
        <h2>최근 출석 추이</h2>
      </div>
    </div>
    <div class="dashboard-chart"><canvas id="dailyTrendChart"></canvas></div>
  </section>

  <section class="admin-card dashboard-chart-card">
    <div class="dashboard-chart-heading">
      <div>
        <span class="section-kicker">By grade</span>
        <h2>학년별 오늘 출석률</h2>
      </div>
    </div>
    <div class="dashboard-chart"><canvas id="gradeRateChart"></canvas></div>
    <div class="dashboard-grade-list" id="gradeStatsList"></div>
  </section>

  <section class="admin-card dashboard-chart-card">
    <div class="dashboard-chart-heading">
      <div>
        <span class="section-kicker">Location</span>
        <h2>위치 인증 상태</h2>
      </div>
    </div>
    <div class="dashboard-chart dashboard-chart-doughnut"><canvas id="locationStatusChart"></canvas></div>
  </section>

  <section class="admin-card dashboard-chart-card dashboard-chart-wide">
    <div class="dashboard-chart-heading">
      <div>
        <span class="section-kicker">By hour</span>
        <h2>시간대별 출석 분포</h2>
      </div>
    </div>
    <div class="dashboard-chart"><canvas id="hourlyChart"></canvas></div>
  </section>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-4">
    <a class="admin-action-card" href="/admin/list.php">
      <i class="bi bi-calendar-check"></i>
      <span>
        <strong>출석 목록</strong>
        <small>날짜별 출석 기록을 조회하고 수정 또는 삭제합니다.</small>
      </span>
    </a>
  </div>
  <div class="col-lg-4">
    <a class="admin-action-card" href="/admin/location.php">
      <i class="bi bi-geo-alt"></i>
      <span>
        <strong>위치 설정</strong>
        <small>출석 가능 위치와 허용 반경을 지도에서 관리합니다.</small>
      </span>
    </a>
  </div>
  <div class="col-lg-4">
    <a class="admin-action-card" href="/admin/system.php">
      <i class="bi bi-tools"></i>
      <span>
        <strong>시스템 관리</strong>
        <small>초기화와 업데이트를 별도 메뉴에서 확인하고 실행합니다.</small>
      </span>
    </a>
  </div>
  <div class="col-lg-4">
    <a class="admin-action-card" href="/admin/password.php">
      <i class="bi bi-shield-lock"></i>
      <span>
        <strong>비밀번호 변경</strong>
        <small>비밀번호 변경 후 기존 로그인 토큰을 만료합니다.</small>
      </span>
    </a>
  </div>
</div>
