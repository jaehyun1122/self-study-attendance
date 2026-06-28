<div class="admin-page-header">
  <div>
    <p class="section-kicker">Attendance Admin</p>
    <div class="dashboard-title-row">
      <h1>관리자 대시보드</h1>
    </div>
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
    <span>승인 대기</span>
    <strong id="pendingCount">0건</strong>
  </section>
  <section class="stat-card">
    <span>오늘 출석률 <small>(기록 학생 기준)</small></span>
    <strong id="todayRate">0%</strong>
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
