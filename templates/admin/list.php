<div class="admin-page-header">
  <div>
    <p class="section-kicker">Attendance List</p>
    <h1>출석 목록</h1>
    <p>날짜별 출석 현황을 확인하고 필요한 기록만 빠르게 수정하세요.</p>
  </div>
  <form class="date-filter-card" id="attendanceFilter">
    <label class="form-label mb-0" for="dateInput">조회 날짜</label>
    <div class="d-flex gap-2">
      <input class="form-control" id="dateInput" type="date" name="date">
      <button class="btn btn-success px-4" id="loadListButton" type="submit">조회</button>
    </div>
  </form>
</div>

<div id="adminAlert"></div>

<section class="admin-card">
  <div class="admin-card-header">
    <div>
      <strong id="listTitle">출석 기록</strong>
      <p class="mb-0 text-secondary small">출석일은 초 단위까지 표시됩니다.</p>
    </div>
    <span class="badge rounded-pill text-bg-success" id="attendanceCount">0건</span>
  </div>
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0">
      <thead>
        <tr>
          <th style="width: 72px;">번호</th>
          <th style="width: 120px;">학번</th>
          <th style="width: 140px;">이름</th>
          <th>출석일시</th>
          <th class="text-end" style="width: 150px;">관리</th>
        </tr>
      </thead>
      <tbody id="attendanceTableBody">
        <tr>
          <td class="text-center py-5" colspan="5">
            <div class="empty-table-state">
              <div class="empty-table-icon">...</div>
              <strong>불러오는 중입니다.</strong>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</section>
