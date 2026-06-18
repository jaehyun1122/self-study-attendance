<div class="admin-page-header">
  <div>
    <p class="section-kicker">Attendance List</p>
    <h1>출석 목록</h1>
    <p>날짜별 출석 현황을 확인하고 필요한 기록만 빠르게 수정하세요.</p>
  </div>
</div>

<section class="admin-list-toolbar" aria-label="출석 목록 조회">
  <form class="date-filter-card compact-filter-card" id="attendanceFilter">
    <div class="list-filter-grid list-filter-grid-compact">
      <div>
        <label class="form-label" for="startDateInput">조회 시작일</label>
        <input class="form-control" id="startDateInput" type="date" name="start_date" required>
      </div>
      <div>
        <label class="form-label" for="endDateInput">조회 종료일</label>
        <input class="form-control" id="endDateInput" type="date" name="end_date" required>
      </div>
      <div>
        <label class="form-label" for="locationStatusFilterInput">위치 인증</label>
        <select class="form-select" id="locationStatusFilterInput" name="location_status">
          <option value="">전체</option>
          <option value="unchecked">미사용</option>
          <option value="verified">인증 완료</option>
          <option value="pending">승인 대기</option>
          <option value="approved">관리자 승인</option>
          <option value="rejected">반려</option>
        </select>
      </div>
      <div>
        <label class="form-label" for="keywordFilterInput">검색</label>
        <input class="form-control" id="keywordFilterInput" type="search" autocomplete="off" placeholder="학번 또는 이름">
      </div>
    </div>
    <input id="sortByInput" type="hidden" value="created_at">
    <input id="sortOrderInput" type="hidden" value="asc">
    <div class="date-filter-actions list-toolbar-actions">
      <div class="filter-navigation">
        <button class="btn btn-sm btn-outline-secondary" id="previousFilterButton" type="button" disabled>이전</button>
        <button class="btn btn-sm btn-outline-secondary" id="nextFilterButton" type="button" disabled>이후</button>
        <span class="filter-history-help" tabindex="0" data-tooltip="현재 화면에서 조회한 조건만 이전/이후로 이동합니다." aria-label="조회 조건 이동 안내">
          <i class="bi bi-info-circle"></i>
        </span>
      </div>
      <button class="btn btn-success px-4" id="loadListButton" type="submit">조회</button>
      <div class="list-bulk-actions">
        <button class="btn btn-outline-secondary px-4" id="bulkDeleteButton" type="button" disabled>
          <i class="bi bi-trash3 me-1"></i> 선택 삭제
        </button>
      </div>
    </div>
  </form>
</section>

<div id="adminAlert"></div>

<section class="admin-card">
  <div class="admin-card-header">
    <div>
      <strong id="listTitle">출석 기록</strong>
    </div>
    <div class="list-card-actions">
      <span class="badge rounded-pill text-bg-success" id="attendanceCount">0건</span>
      <button class="btn btn-sm btn-outline-success" id="exportListButton" type="button">
        <i class="bi bi-download me-1"></i> 엑셀로 내보내기
      </button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0">
      <thead>
        <tr>
          <th class="text-center" style="width: 52px;">
            <input class="form-check-input" id="selectAllRowsInput" type="checkbox" aria-label="전체 선택">
          </th>
          <th style="width: 72px;">순서</th>
          <th style="width: 120px;">
            <button class="table-sort-button" type="button" data-sort-key="student_no">학번 <span data-sort-icon="student_no"></span></button>
          </th>
          <th style="width: 140px;">
            <button class="table-sort-button" type="button" data-sort-key="name">이름 <span data-sort-icon="name"></span></button>
          </th>
          <th>
            <button class="table-sort-button" type="button" data-sort-key="created_at">출석일시 <span data-sort-icon="created_at"></span></button>
          </th>
          <th style="width: 150px;">
            <button class="table-sort-button" type="button" data-sort-key="location_status">위치 인증 <span data-sort-icon="location_status"></span></button>
          </th>
          <th class="text-end" style="width: 280px;">관리</th>
        </tr>
      </thead>
      <tbody id="attendanceTableBody">
        <tr>
          <td class="text-center py-5" colspan="7">
            <div class="empty-table-state">
              <div class="loading-spinner" aria-hidden="true"></div>
              <strong>불러오는 중입니다.</strong>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<div class="admin-modal" id="locationDetailModal" hidden>
  <section class="admin-modal-dialog location-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="locationDetailTitle">
    <div class="admin-modal-header">
      <h2 id="locationDetailTitle">위치 인증 상세</h2>
      <button class="btn btn-sm btn-outline-secondary" id="closeLocationDetailButton" type="button" aria-label="닫기">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <dl class="info-list" id="locationDetailList"></dl>
    <div class="location-map detail-map" id="locationDetailMap" aria-label="위치 인증 지도"></div>
  </section>
</div>
