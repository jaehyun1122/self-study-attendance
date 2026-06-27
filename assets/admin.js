(function () {
  const TOKEN_KEY = 'admin_token';
  const FILTER_HISTORY_KEY = 'attendance_filter_history_v1';
  const FILTER_HISTORY_LIMIT = 30;
  const DEFAULT_SYNC_INTERVAL_MS = 5000;
  const DEFAULT_MAP_CENTER = [37.5665, 126.9780];
  const token = window.ADMIN_TOKEN || localStorage.getItem(TOKEN_KEY);
  const path = window.location.pathname;
  const {
    formatDateTime,
    formatDateTimeText,
    formatUptimeSeconds,
    fromDateTimeLocal,
    initPasswordToggles,
    inputRange,
    meterText,
    nullableNumber,
    parseServerTime,
    toDateTimeLocal,
    valueOrDash,
    validateLength,
  } = window.PublicUtils;

  let summaryServerTime = null;
  let summaryClockTimer = null;
  let summarySyncTimer = null;
  let summarySyncIntervalMs = DEFAULT_SYNC_INTERVAL_MS;

  if (window.ADMIN_TOKEN) {
    localStorage.setItem(TOKEN_KEY, window.ADMIN_TOKEN);
  }

  function toast(message, type = 'success') {
    if (window.Toastify) {
      window.Toastify({
        text: message,
        duration: 2400,
        gravity: 'top',
        position: 'center',
        style: {
          background: type === 'error' ? '#dc3545' : '#198754',
          borderRadius: '8px',
        },
      }).showToast();
      return;
    }

    const root = document.getElementById('toastRoot');
    if (!root) return;

    const item = document.createElement('div');
    item.className = `fallback-toast ${type}`;
    item.textContent = message;
    root.appendChild(item);
    setTimeout(() => item.remove(), 2400);
  }

  function ensureAdminDialog() {
    let modal = document.getElementById('adminPopupModal');

    if (modal) {
      return modal;
    }

    modal = document.createElement('div');
    modal.className = 'admin-modal admin-popup-modal';
    modal.id = 'adminPopupModal';
    modal.hidden = true;
    modal.innerHTML = `
      <section class="admin-modal-dialog admin-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="adminPopupTitle">
        <div class="admin-modal-header">
          <h2 id="adminPopupTitle">알림</h2>
          <button class="btn btn-sm btn-outline-secondary" id="adminPopupCloseButton" type="button" aria-label="닫기">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <p class="admin-popup-message" id="adminPopupMessage"></p>
        <div class="admin-popup-actions">
          <button class="btn btn-outline-secondary" id="adminPopupCancelButton" type="button">취소</button>
          <button class="btn btn-success" id="adminPopupConfirmButton" type="button">확인</button>
        </div>
      </section>
    `;
    document.body.appendChild(modal);

    return modal;
  }

  function openAdminDialog(options = {}) {
    return new Promise((resolve) => {
      const modal = ensureAdminDialog();
      const title = modal.querySelector('#adminPopupTitle');
      const message = modal.querySelector('#adminPopupMessage');
      const closeButton = modal.querySelector('#adminPopupCloseButton');
      const cancelButton = modal.querySelector('#adminPopupCancelButton');
      const confirmButton = modal.querySelector('#adminPopupConfirmButton');
      const hasCancel = Boolean(options.cancelText);

      title.textContent = options.title || '알림';
      message.textContent = options.message || '';
      cancelButton.textContent = options.cancelText || '취소';
      cancelButton.hidden = !hasCancel;
      confirmButton.textContent = options.confirmText || '확인';
      confirmButton.className = `btn ${options.confirmClass || (options.type === 'danger' ? 'btn-danger' : 'btn-success')}`;
      modal.hidden = false;

      const close = (result) => {
        modal.hidden = true;
        closeButton.onclick = null;
        cancelButton.onclick = null;
        confirmButton.onclick = null;
        modal.onclick = null;
        document.removeEventListener('keydown', handleKeydown);
        resolve(result);
      };

      const handleKeydown = (event) => {
        if (modal.hidden) {
          return;
        }

        if (event.key === 'Escape') {
          event.preventDefault();
          close(false);
        }

        if (event.key === 'Enter') {
          event.preventDefault();
          close(true);
        }
      };

      closeButton.onclick = () => close(false);
      cancelButton.onclick = () => close(false);
      confirmButton.onclick = () => close(true);
      modal.onclick = (event) => {
        if (event.target === modal) {
          close(false);
        }
      };

      document.addEventListener('keydown', handleKeydown);
      confirmButton.focus();
    });
  }

  function confirmAction(message, options = {}) {
    return openAdminDialog({
      title: options.title || '확인',
      message,
      type: options.type || 'danger',
      confirmText: options.confirmText || '확인',
      cancelText: options.cancelText || '취소',
      confirmClass: options.confirmClass,
    });
  }

  function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function showAlert(message, type = 'danger') {
    toast(message, type === 'danger' ? 'error' : 'success');
  }

  function clearAlert() {
    const box = document.getElementById('adminAlert');
    if (box) box.innerHTML = '';
  }

  function redirectLogin(reason = 'etc') {
    localStorage.removeItem(TOKEN_KEY);
    window.location.href = `/admin/?reason=${encodeURIComponent(reason || 'etc')}`;
  }

  async function api(url, body = {}) {
    const headers = {
      'Content-Type': 'application/json',
    };

    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => null);

    if (response.status === 401) {
      redirectLogin('session-expired');
      return null;
    }

    if (!data) {
      throw new Error('서버 응답을 읽을 수 없습니다.');
    }

    return data;
  }

  function todayForInput() {
    const date = new Date();
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    })[char]);
  }

  function syncIntervalMs(result = null) {
    const seconds = Number(result?.server_time_sync_interval_seconds || 5);
    return Math.max(1, seconds) * 1000;
  }

  function syncSummaryServerTime(value, element) {
    summaryServerTime = parseServerTime(value);
    updateSummaryServerTime(element);

    if (summaryClockTimer) {
      return;
    }

    summaryClockTimer = setInterval(() => {
      if (!summaryServerTime) {
        return;
      }

      summaryServerTime.setSeconds(summaryServerTime.getSeconds() + 1);
      updateSummaryServerTime(element);
    }, 1000);
  }

  function updateSummaryServerTime(element) {
    if (!element) {
      return;
    }

    element.textContent = summaryServerTime ? formatDateTime(summaryServerTime) : '-';
  }

  function scheduleSummarySync(result, loadSummary) {
    const nextIntervalMs = syncIntervalMs(result);

    if (summarySyncTimer && nextIntervalMs === summarySyncIntervalMs) {
      return;
    }

    summarySyncIntervalMs = nextIntervalMs;

    if (summarySyncTimer) {
      clearInterval(summarySyncTimer);
    }

    summarySyncTimer = setInterval(() => loadSummary(false), summarySyncIntervalMs);
  }

  function validateStudentInfo(studentNo, name) {
    const studentNoInput = document.getElementById('studentNoInput');
    const nameInput = document.getElementById('nameInput');
    const studentNoText = String(studentNo || '').trim();

    if (!/^\d+$/.test(studentNoText)) {
      showAlert('학번은 숫자만 입력해주세요.');
      return false;
    }

    if (!validateLength(studentNoText, '학번은', inputRange(studentNoInput, 5, 5))) {
      return false;
    }

    return validateLength(name, '이름은', inputRange(nameInput, 1, 10));
  }

  function locationStatusInfo(value) {
    return ({
      verified: ['위치 인증 완료', 'text-bg-success'],
      pending: ['관리자 승인 대기', 'text-bg-warning'],
      approved: ['관리자 승인 완료', 'text-bg-info'],
      rejected: ['위치 인증 반려', 'text-bg-danger'],
      unchecked: ['위치 인증 미사용', 'text-bg-secondary'],
    })[value] || ['위치 인증 미사용', 'text-bg-secondary'];
  }

  function locationStatusText(value) {
    return locationStatusInfo(value)[0];
  }

  function leafletReady() {
    return typeof window.L !== 'undefined';
  }

  function buildMap(element, center = DEFAULT_MAP_CENTER, zoom = 16) {
    if (!element || !leafletReady()) {
      if (element) {
        element.innerHTML = '<div class="map-empty">지도를 불러오지 못했습니다.</div>';
      }
      return null;
    }

    const map = window.L.map(element, {
      scrollWheelZoom: false,
    }).setView(center, zoom);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    return map;
  }

  function initDash() {
    const todayCount = document.getElementById('todayCount');
    const totalCount = document.getElementById('totalCount');
    const studentCount = document.getElementById('studentCount');
    const todayRate = document.getElementById('todayRate');
    const pendingCount = document.getElementById('pendingCount');
    const serverTime = document.getElementById('summaryServerTime');
    const gradeStatsList = document.getElementById('gradeStatsList');
    const charts = {};
    const chartColors = {
      primary: '#198754',
      primarySoft: 'rgba(25, 135, 84, 0.16)',
      blue: '#0d6efd',
      amber: '#f0ad4e',
      red: '#dc3545',
      gray: '#adb5bd',
      teal: '#20c997',
    };

    function renderChart(key, elementId, config) {
      const canvas = document.getElementById(elementId);

      if (!canvas) {
        return;
      }

      if (typeof window.Chart === 'undefined') {
        canvas.parentElement.innerHTML = '<div class="dashboard-chart-fallback">그래프 모듈을 불러오지 못했습니다.</div>';
        return;
      }

      if (charts[key]) {
        charts[key].data = config.data;
        charts[key].options = config.options;
        charts[key].update();
        return;
      }

      charts[key] = new window.Chart(canvas, config);
    }

    function commonScales(percent = false) {
      return {
        x: {
          grid: { display: false },
          ticks: { color: '#68776f', maxRotation: 0 },
        },
        y: {
          beginAtZero: true,
          suggestedMax: percent ? 100 : undefined,
          max: percent ? 100 : undefined,
          grid: { color: 'rgba(104, 119, 111, 0.12)' },
          ticks: {
            color: '#68776f',
            callback: percent ? (value) => `${value}%` : undefined,
            precision: percent ? undefined : 0,
          },
        },
      };
    }

    function renderStatistics(result) {
      const dailyTrend = Array.isArray(result.daily_trend) ? result.daily_trend : [];
      const gradeStats = Array.isArray(result.grade_stats) ? result.grade_stats : [];
      const locationStats = Array.isArray(result.location_stats) ? result.location_stats : [];
      const hourlyStats = Array.isArray(result.hourly_stats) ? result.hourly_stats : [];

      renderChart('daily', 'dailyTrendChart', {
        type: 'line',
        data: {
          labels: dailyTrend.map((item) => item.date.slice(5).replace('-', '/')),
          datasets: [{
            label: '출석',
            data: dailyTrend.map((item) => Number(item.count || 0)),
            borderColor: chartColors.primary,
            backgroundColor: chartColors.primarySoft,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: true,
            tension: 0.3,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: commonScales(),
        },
      });

      renderChart('grade', 'gradeRateChart', {
        type: 'bar',
        data: {
          labels: gradeStats.map((item) => `${item.grade}학년`),
          datasets: [{
            label: '오늘 출석률',
            data: gradeStats.map((item) => Number(item.today_rate || 0)),
            backgroundColor: [chartColors.primary, chartColors.blue, chartColors.teal, chartColors.amber],
            borderRadius: 7,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (context) => ` ${context.raw}%`,
              },
            },
          },
          scales: commonScales(true),
        },
      });

      if (gradeStatsList) {
        gradeStatsList.innerHTML = gradeStats.length
          ? gradeStats.map((item) => `
              <div>
                <strong>${Number(item.grade)}학년</strong>
                <span>${Number(item.today_count || 0)} / ${Number(item.student_count || 0)}명</span>
                <b>${Number(item.today_rate || 0).toFixed(1)}%</b>
              </div>
            `).join('')
          : '<p class="text-secondary mb-0">학년별 데이터가 없습니다.</p>';
      }

      const locationLabels = {
        verified: '인증 완료',
        approved: '관리자 승인',
        pending: '승인 대기',
        rejected: '반려',
        unchecked: '미검사',
      };
      const locationColors = {
        verified: chartColors.primary,
        approved: chartColors.blue,
        pending: chartColors.amber,
        rejected: chartColors.red,
        unchecked: chartColors.gray,
      };

      renderChart('location', 'locationStatusChart', {
        type: 'doughnut',
        data: {
          labels: locationStats.map((item) => locationLabels[item.status] || item.status),
          datasets: [{
            data: locationStats.map((item) => Number(item.count || 0)),
            backgroundColor: locationStats.map((item) => locationColors[item.status] || chartColors.gray),
            borderColor: '#ffffff',
            borderWidth: 3,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { usePointStyle: true, boxWidth: 8 },
            },
          },
        },
      });

      renderChart('hourly', 'hourlyChart', {
        type: 'bar',
        data: {
          labels: hourlyStats.map((item) => `${String(item.hour).padStart(2, '0')}시`),
          datasets: [{
            label: '출석',
            data: hourlyStats.map((item) => Number(item.count || 0)),
            backgroundColor: chartColors.primarySoft,
            borderColor: chartColors.primary,
            borderWidth: 1,
            borderRadius: 5,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: commonScales(),
        },
      });
    }

    async function loadSummary(showError = true) {
      try {
        const data = await api('/api/admin-summary.php');
        if (!data) return;

        if (data.status !== 1) {
          if (showError) {
            toast(data.msg || '대시보드 정보를 불러오지 못했습니다.', 'error');
          }
          return;
        }

        const result = data.result || {};
        todayCount.textContent = `${Number(result.today || 0)}건`;
        totalCount.textContent = `${Number(result.total || 0)}건`;
        if (studentCount) {
          studentCount.textContent = `${Number(result.student_count || 0)}명`;
        }
        if (todayRate) {
          todayRate.textContent = `${Number(result.today_rate || 0).toFixed(1)}%`;
        }
        if (pendingCount) {
          pendingCount.textContent = `${Number(result.pending || 0)}건`;
        }
        renderStatistics(result);

        if (result.server_time) {
          syncSummaryServerTime(result.server_time, serverTime);
        }

        scheduleSummarySync(result, loadSummary);
      } catch (error) {
        scheduleSummarySync(null, loadSummary);

        if (showError) {
          toast('대시보드 정보를 불러오는 중 오류가 발생했습니다.', 'error');
        }
      }
    }

    loadSummary(true);
  }

  function initList() {
    const params = new URLSearchParams(window.location.search);
    const startDateInput = document.getElementById('startDateInput');
    const endDateInput = document.getElementById('endDateInput');
    const keywordFilterInput = document.getElementById('keywordFilterInput');
    const locationStatusFilterInput = document.getElementById('locationStatusFilterInput');
    const sortByInput = document.getElementById('sortByInput');
    const sortOrderInput = document.getElementById('sortOrderInput');
    const form = document.getElementById('attendanceFilter');
    const button = document.getElementById('loadListButton');
    const exportButton = document.getElementById('exportListButton');
    const bulkDeleteButton = document.getElementById('bulkDeleteButton');
    const previousFilterButton = document.getElementById('previousFilterButton');
    const nextFilterButton = document.getElementById('nextFilterButton');
    const selectAllRowsInput = document.getElementById('selectAllRowsInput');
    const locationDetailModal = document.getElementById('locationDetailModal');
    const closeLocationDetailButton = document.getElementById('closeLocationDetailButton');
    const locationDetailList = document.getElementById('locationDetailList');
    const locationDetailMap = document.getElementById('locationDetailMap');
    let currentRows = [];
    let locationSettings = null;
    let detailMap = null;
    let filterHistory = [];
    let filterHistoryIndex = -1;

    startDateInput.value = params.get('start_date') || params.get('date') || todayForInput();
    endDateInput.value = params.get('end_date') || params.get('date') || startDateInput.value;
    keywordFilterInput.value = params.get('keyword') || params.get('student_no') || params.get('name') || '';
    locationStatusFilterInput.value = params.get('location_status') || '';
    sortByInput.value = params.get('sort_by') || 'created_at';
    sortOrderInput.value = params.get('sort_order') || 'asc';

    if (!sortByInput.value) sortByInput.value = 'created_at';
    if (!sortOrderInput.value) sortOrderInput.value = 'asc';

    loadFilterHistory();
    ensureCurrentFilterHistory();

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      recordFilterHistory();
      syncFilterUrl();
      loadList();
    });

    document.querySelectorAll('[data-sort-key]').forEach((sortButton) => {
      sortButton.addEventListener('click', () => {
        const key = sortButton.dataset.sortKey;
        if (sortByInput.value === key) {
          sortOrderInput.value = sortOrderInput.value === 'asc' ? 'desc' : 'asc';
        } else {
          sortByInput.value = key;
          sortOrderInput.value = 'asc';
        }

        updateSortIndicators();
        recordFilterHistory();
        syncFilterUrl();
        loadList();
      });
    });

    previousFilterButton.addEventListener('click', () => moveFilterHistory(-1));
    nextFilterButton.addEventListener('click', () => moveFilterHistory(1));

    exportButton.addEventListener('click', () => {
      const ids = selectedIds();
      const rows = ids.length > 0
        ? currentRows.filter((row) => ids.includes(Number(row.id)))
        : currentRows;
      exportCsv(rows, ids.length > 0);
    });

    selectAllRowsInput.addEventListener('change', () => {
      document.querySelectorAll('[data-row-select]').forEach((checkbox) => {
        checkbox.checked = selectAllRowsInput.checked;
      });
      updateSelectionState();
    });

    bulkDeleteButton.addEventListener('click', async () => {
      const ids = selectedIds();
      if (ids.length < 1) {
        toast('삭제할 출석 기록을 선택해주세요.', 'error');
        return;
      }

      if (!await confirmAction(`선택한 ${ids.length}건의 출석 기록을 삭제할까요?`, {
        title: '선택 삭제',
        confirmText: '삭제',
        confirmClass: 'btn-danger',
      })) {
        return;
      }

      try {
        const data = await api('/api/admin-edit.php', {
          type: 'bulk_delete',
          ids,
        });
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '선택 삭제에 실패했습니다.');
          return;
        }

        toast('선택한 출석 기록이 삭제되었습니다.');
        loadList();
      } catch (error) {
        showAlert('선택 삭제 중 오류가 발생했습니다.');
      }
    });

    closeLocationDetailButton.addEventListener('click', closeLocationDetail);
    locationDetailModal.addEventListener('click', (event) => {
      if (event.target === locationDetailModal) {
        closeLocationDetail();
      }
    });

    async function loadLocationSettings() {
      try {
        const data = await api('/api/admin-location.php', { type: 'get' });
        if (data?.status === 1) {
          locationSettings = data.result || {};
        }
      } catch (error) {
        locationSettings = {};
      }
    }

    async function loadList() {
      clearAlert();
      currentRows = [];
      renderLoading();
      updateSelectionState();
      button.disabled = true;
      exportButton.disabled = true;
      button.textContent = '조회 중...';

      try {
        const data = await api('/api/admin-list.php', readFilters());
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '출석 목록을 불러오지 못했습니다.');
          renderTableNotice('출석 목록을 불러오지 못했습니다.', '잠시 후 다시 조회해주세요.');
          return;
        }

        renderList(Array.isArray(data.result) ? data.result : []);
      } catch (error) {
        showAlert('출석 목록을 불러오는 중 오류가 발생했습니다.');
        renderTableNotice('출석 목록을 불러오지 못했습니다.', '네트워크 상태를 확인한 뒤 다시 조회해주세요.');
      } finally {
        button.disabled = false;
        exportButton.disabled = false;
        button.textContent = '조회';
      }
    }

    function readFilters() {
      return {
        start_date: startDateInput.value,
        end_date: endDateInput.value,
        keyword: keywordFilterInput.value.trim(),
        location_status: locationStatusFilterInput.value,
        sort_by: sortByInput.value,
        sort_order: sortOrderInput.value,
      };
    }

    function syncFilterUrl() {
      const url = new URL(window.location.href);
      const filters = readFilters();
      url.searchParams.delete('date');
      url.searchParams.delete('student_no');
      url.searchParams.delete('name');
      url.searchParams.delete('location_status');

      Object.entries(filters).forEach(([key, value]) => {
        if (value) {
          url.searchParams.set(key, value);
        } else {
          url.searchParams.delete(key);
        }
      });

      window.history.replaceState(null, '', url);
    }

    function normalizedFilters(filters = readFilters()) {
      return {
        start_date: filters.start_date || '',
        end_date: filters.end_date || '',
        keyword: filters.keyword || '',
        location_status: filters.location_status || '',
        sort_by: filters.sort_by || 'created_at',
        sort_order: filters.sort_order || 'asc',
      };
    }

    function sameFilters(first, second) {
      return JSON.stringify(normalizedFilters(first)) === JSON.stringify(normalizedFilters(second));
    }

    function loadFilterHistory() {
      try {
        const saved = JSON.parse(localStorage.getItem(FILTER_HISTORY_KEY) || '[]');
        filterHistory = Array.isArray(saved)
          ? saved
            .filter((item) => item && typeof item === 'object')
            .map((item) => normalizedFilters(item))
            .slice(-FILTER_HISTORY_LIMIT)
          : [];

        localStorage.setItem(FILTER_HISTORY_KEY, JSON.stringify(filterHistory));
      } catch (error) {
        filterHistory = [];
      }
    }

    function saveFilterHistory() {
      filterHistory = filterHistory
        .map((item) => normalizedFilters(item))
        .slice(-FILTER_HISTORY_LIMIT);

      try {
        localStorage.setItem(FILTER_HISTORY_KEY, JSON.stringify(filterHistory));
      } catch (error) {
        // Browsers can block storage in private or restricted contexts.
      }

      updateFilterHistoryButtons();
    }

    function ensureCurrentFilterHistory() {
      const filters = normalizedFilters();
      filterHistoryIndex = filterHistory.findIndex((item) => sameFilters(item, filters));

      if (filterHistoryIndex < 0) {
        filterHistory.push(filters);
        filterHistory = filterHistory.slice(-FILTER_HISTORY_LIMIT);
        filterHistoryIndex = filterHistory.length - 1;
        saveFilterHistory();
        return;
      }

      updateFilterHistoryButtons();
    }

    function recordFilterHistory(filters = readFilters()) {
      const normalized = normalizedFilters(filters);

      if (filterHistoryIndex >= 0 && sameFilters(filterHistory[filterHistoryIndex], normalized)) {
        updateFilterHistoryButtons();
        return;
      }

      filterHistory = filterHistory.slice(0, filterHistoryIndex + 1);
      filterHistory.push(normalized);
      filterHistory = filterHistory.slice(-FILTER_HISTORY_LIMIT);
      filterHistoryIndex = filterHistory.length - 1;
      saveFilterHistory();
    }

    function moveFilterHistory(direction) {
      const nextIndex = filterHistoryIndex + direction;

      if (nextIndex < 0 || nextIndex >= filterHistory.length) {
        return;
      }

      filterHistoryIndex = nextIndex;
      applyFilters(filterHistory[filterHistoryIndex]);
      updateFilterHistoryButtons();
      syncFilterUrl();
      loadList();
    }

    function applyFilters(filters) {
      const normalized = normalizedFilters(filters);
      startDateInput.value = normalized.start_date;
      endDateInput.value = normalized.end_date;
      keywordFilterInput.value = normalized.keyword;
      locationStatusFilterInput.value = normalized.location_status;
      sortByInput.value = normalized.sort_by;
      sortOrderInput.value = normalized.sort_order;
      updateSortIndicators();
    }

    function updateFilterHistoryButtons() {
      previousFilterButton.disabled = filterHistoryIndex <= 0;
      nextFilterButton.disabled = filterHistoryIndex < 0 || filterHistoryIndex >= filterHistory.length - 1;
    }

    function currentRangeTitle() {
      const filters = readFilters();

      if (filters.start_date === filters.end_date) {
        return `${filters.start_date} 출석 기록`;
      }

      return `${filters.start_date} ~ ${filters.end_date} 출석 기록`;
    }

    function renderLoading() {
      document.getElementById('listTitle').textContent = currentRangeTitle();
      document.getElementById('attendanceCount').textContent = '조회 중';
      document.getElementById('attendanceTableBody').innerHTML = `
        <tr>
          <td class="text-center py-5" colspan="7">
            <div class="empty-table-state">
              <div class="loading-spinner" aria-hidden="true"></div>
              <strong>조회 중입니다.</strong>
            </div>
          </td>
        </tr>
      `;
    }

    function renderTableNotice(title, description) {
      currentRows = [];
      document.getElementById('attendanceCount').textContent = '0건';
      document.getElementById('attendanceTableBody').innerHTML = `
        <tr>
          <td class="text-center py-5" colspan="7">
            <div class="empty-table-state">
              <strong>${escapeHtml(title)}</strong>
              <p class="text-secondary mb-0">${escapeHtml(description)}</p>
            </div>
          </td>
        </tr>
      `;
      updateSelectionState();
    }

    function renderList(rows) {
      currentRows = rows;
      document.getElementById('listTitle').textContent = currentRangeTitle();
      document.getElementById('attendanceCount').textContent = `${rows.length}건`;
      const body = document.getElementById('attendanceTableBody');

      if (rows.length === 0) {
        body.innerHTML = `
          <tr>
            <td class="text-center py-5" colspan="7">
              <div class="empty-table-state">
                <strong>출석 기록이 없습니다.</strong>
                <p class="text-secondary mb-0">선택한 조건에 등록된 출석이 없어요.</p>
              </div>
            </td>
          </tr>
        `;
        updateSelectionState();
        return;
      }

      body.innerHTML = rows.map((row, index) => `
        <tr>
          <td class="text-center">
            <input class="form-check-input" type="checkbox" data-row-select="${escapeHtml(row.id)}" aria-label="${escapeHtml(row.name)} 선택">
          </td>
          <td><span class="text-secondary">${index + 1}</span></td>
          <td>${escapeHtml(row.student_no)}</td>
          <td class="fw-semibold">${escapeHtml(row.name)}</td>
          <td class="text-nowrap">${escapeHtml(formatDateTimeText(row.attend_datetime || row.created_at || row.attend_date))}</td>
          <td>${locationStatusHtml(row)}</td>
          <td class="text-end">
            <div class="attendance-row-actions">
              ${row.location_status === 'pending' ? `<button class="btn btn-sm btn-outline-primary" type="button" data-approve-id="${escapeHtml(row.id)}">승인</button>` : ''}
              ${row.location_status === 'pending' ? `<button class="btn btn-sm btn-outline-warning" type="button" data-reject-id="${escapeHtml(row.id)}">반려</button>` : ''}
              <a class="btn btn-sm btn-outline-success" href="/admin/edit.php?id=${encodeURIComponent(row.id)}">수정</a>
              <button class="btn btn-sm btn-outline-danger" type="button" data-delete-id="${escapeHtml(row.id)}">삭제</button>
            </div>
          </td>
        </tr>
      `).join('');

      body.querySelectorAll('[data-row-select]').forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectionState);
      });

      body.querySelectorAll('[data-location-detail-id]').forEach((detailButton) => {
        detailButton.addEventListener('click', () => {
          const row = currentRows.find((item) => String(item.id) === String(detailButton.dataset.locationDetailId));
          if (row) {
            openLocationDetail(row);
          }
        });
      });

      body.querySelectorAll('[data-approve-id]').forEach((approveButton) => {
        approveButton.addEventListener('click', async () => {
          if (!await confirmAction('이 위치 인증 대기 출석을 승인할까요?', {
            title: '승인 확인',
            confirmText: '승인',
            confirmClass: 'btn-primary',
            type: 'info',
          })) return;

          try {
            const data = await api('/api/admin-edit.php', {
              type: 'approve_location',
              id: Number(approveButton.dataset.approveId),
            });
            if (!data) return;

            if (data.status !== 1) {
              showAlert(data.msg || '승인에 실패했습니다.');
              return;
            }

            toast('승인되었습니다.');
            loadList();
          } catch (error) {
            showAlert('승인 중 오류가 발생했습니다.');
          }
        });
      });

      body.querySelectorAll('[data-reject-id]').forEach((rejectButton) => {
        rejectButton.addEventListener('click', async () => {
          if (!await confirmAction('이 위치 인증 대기 출석을 반려할까요?', {
            title: '반려 확인',
            confirmText: '반려',
            confirmClass: 'btn-warning',
          })) return;

          try {
            const data = await api('/api/admin-edit.php', {
              type: 'reject_location',
              id: Number(rejectButton.dataset.rejectId),
            });
            if (!data) return;

            if (data.status !== 1) {
              showAlert(data.msg || '반려에 실패했습니다.');
              return;
            }

            toast('반려했습니다.');
            loadList();
          } catch (error) {
            showAlert('반려 중 오류가 발생했습니다.');
          }
        });
      });

      body.querySelectorAll('[data-delete-id]').forEach((deleteButton) => {
        deleteButton.addEventListener('click', async () => {
          if (!await confirmAction('이 출석 기록을 삭제할까요?', {
            title: '삭제 확인',
            confirmText: '삭제',
            confirmClass: 'btn-danger',
          })) return;

          try {
            const data = await api('/api/admin-edit.php', {
              type: 'delete',
              id: Number(deleteButton.dataset.deleteId),
            });
            if (!data) return;

            if (data.status !== 1) {
              showAlert(data.msg || '삭제에 실패했습니다.');
              return;
            }

            toast('삭제되었습니다.');
            loadList();
          } catch (error) {
            showAlert('삭제 중 오류가 발생했습니다.');
          }
        });
      });

      updateSelectionState();
    }

    function locationStatusHtml(row) {
      const status = String(row.location_status || 'unchecked');
      const [label, className] = locationStatusInfo(status);
      const distance = row.location_distance_meters === null || row.location_distance_meters === undefined || row.location_distance_meters === ''
        ? ''
        : `<small class="location-distance">${Number(row.location_distance_meters).toFixed(1)}m</small>`;

      return `
        <button class="location-status-button" type="button" data-location-detail-id="${escapeHtml(row.id)}">
          <span class="badge rounded-pill ${className}">${escapeHtml(label)}</span>${distance}
        </button>
      `;
    }

    function selectedIds() {
      return Array.from(document.querySelectorAll('[data-row-select]:checked'))
        .map((checkbox) => Number(checkbox.dataset.rowSelect))
        .filter((id) => Number.isInteger(id) && id > 0);
    }

    function updateSelectionState() {
      const checkboxes = Array.from(document.querySelectorAll('[data-row-select]'));
      const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
      bulkDeleteButton.disabled = checkedCount < 1;
      selectAllRowsInput.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
      selectAllRowsInput.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }

    function updateSortIndicators() {
      document.querySelectorAll('[data-sort-icon]').forEach((icon) => {
        const key = icon.dataset.sortIcon;
        icon.textContent = key === sortByInput.value ? (sortOrderInput.value === 'desc' ? '↓' : '↑') : '↕';
      });
    }

    async function openLocationDetail(row) {
      if (!locationSettings) {
        await loadLocationSettings();
      }

      const settings = locationSettings || {};
      const hasStudentLocation = row.location_latitude !== null && row.location_latitude !== undefined
        && row.location_longitude !== null && row.location_longitude !== undefined;
      const hasCenter = settings.latitude !== null && settings.latitude !== undefined
        && settings.longitude !== null && settings.longitude !== undefined;
      const rows = [
        ['학생', `${row.student_no} ${row.name}`],
        ['출석일시', formatDateTimeText(row.attend_datetime || row.created_at)],
        ['상태', locationStatusText(row.location_status)],
        ['위도', valueOrDash(row.location_latitude)],
        ['경도', valueOrDash(row.location_longitude)],
        ['정확도', meterText(row.location_accuracy)],
        ['중심과 거리', meterText(row.location_distance_meters)],
        ['위치 메시지', valueOrDash(row.location_message)],
        ['위치 확인 시각', valueOrDash(formatDateTimeText(row.location_checked_at))],
        ['승인/반려 시각', valueOrDash(formatDateTimeText(row.location_approved_at))],
      ];

      locationDetailList.innerHTML = rows.map(([label, value]) => `
        <div>
          <dt>${escapeHtml(label)}</dt>
          <dd>${escapeHtml(value)}</dd>
        </div>
      `).join('');
      locationDetailModal.hidden = false;

      if (detailMap) {
        detailMap.remove();
        detailMap = null;
      }

      const center = hasStudentLocation
        ? [Number(row.location_latitude), Number(row.location_longitude)]
        : (hasCenter ? [Number(settings.latitude), Number(settings.longitude)] : DEFAULT_MAP_CENTER);
      detailMap = buildMap(locationDetailMap, center, hasStudentLocation || hasCenter ? 17 : 13);

      if (!detailMap) {
        return;
      }

      const bounds = [];

      if (hasCenter) {
        const centerLatLng = [Number(settings.latitude), Number(settings.longitude)];
        window.L.marker(centerLatLng).addTo(detailMap).bindPopup('출석 가능 중심');
        bounds.push(centerLatLng);

        if (settings.radius_meters !== null && settings.radius_meters !== undefined) {
          window.L.circle(centerLatLng, {
            radius: Number(settings.radius_meters),
            color: '#198754',
            fillColor: '#198754',
            fillOpacity: 0.12,
          }).addTo(detailMap);
        }
      }

      if (hasStudentLocation) {
        const studentLatLng = [Number(row.location_latitude), Number(row.location_longitude)];
        window.L.marker(studentLatLng).addTo(detailMap).bindPopup('학생 출석 위치');
        bounds.push(studentLatLng);
      }

      if (bounds.length > 1) {
        detailMap.fitBounds(bounds, { padding: [32, 32], maxZoom: 17 });
      }

      setTimeout(() => detailMap.invalidateSize(), 80);
    }

    function closeLocationDetail() {
      locationDetailModal.hidden = true;
      if (detailMap) {
        detailMap.remove();
        detailMap = null;
      }
    }

    function exportCsv(rows, selectedOnly = false) {
      if (rows.length === 0) {
        toast(selectedOnly ? '선택한 출석 기록이 없습니다.' : '내보낼 출석 기록이 없습니다.', 'error');
        return;
      }

      const csvRows = [
        ['순서', '학번', '이름', '출석일시', '위치인증'],
        ...rows.map((row, index) => [
          index + 1,
          row.student_no,
          row.name,
          formatDateTimeText(row.attend_datetime || row.created_at || row.attend_date),
          locationStatusText(row.location_status),
        ]),
      ];
      const csv = `\ufeff${csvRows.map((row) => row.map(csvEscape).join(',')).join('\r\n')}`;
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');

      link.href = url;
      link.download = `attendance-${startDateInput.value}-${endDateInput.value}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    }

    function csvEscape(value) {
      const text = String(value ?? '');

      if (/[",\r\n]/.test(text)) {
        return `"${text.replace(/"/g, '""')}"`;
      }

      return text;
    }

    updateSortIndicators();
    loadLocationSettings();
    loadList();
  }

  function initLocation() {
    const form = document.getElementById('locationForm');
    const enabledInput = document.getElementById('locationEnabledInput');
    const enabledState = document.getElementById('locationEnabledState');
    const latitudeInput = document.getElementById('latitudeInput');
    const longitudeInput = document.getElementById('longitudeInput');
    const radiusInput = document.getElementById('radiusInput');
    const timeoutInput = document.getElementById('timeoutInput');
    const useCurrentButton = document.getElementById('useCurrentLocationButton');
    const saveButton = document.getElementById('saveLocationButton');
    const mapElement = document.getElementById('locationSettingsMap');
    let settingsMap = null;
    let centerMarker = null;
    let radiusCircle = null;

    function fillForm(settings = {}) {
      enabledInput.checked = Boolean(settings.enabled);
      latitudeInput.value = settings.latitude ?? '';
      longitudeInput.value = settings.longitude ?? '';
      radiusInput.value = settings.radius_meters ?? '';
      timeoutInput.value = settings.timeout_seconds ?? '';
      updateEnabledState();
      updateSettingsMap();
    }

    function updateEnabledState() {
      if (!enabledState) {
        return;
      }

      enabledState.textContent = enabledInput.checked ? '사용' : '미사용';
      enabledState.className = `badge location-enabled-state ${enabledInput.checked ? 'text-bg-success' : 'text-bg-secondary'}`;
    }

    function readForm() {
      return {
        type: 'save',
        enabled: enabledInput.checked,
        latitude: nullableNumber(latitudeInput.value),
        longitude: nullableNumber(longitudeInput.value),
        radius_meters: nullableNumber(radiusInput.value),
        timeout_seconds: nullableNumber(timeoutInput.value),
      };
    }

    function initSettingsMap() {
      if (settingsMap || !mapElement) {
        return;
      }

      settingsMap = buildMap(mapElement, DEFAULT_MAP_CENTER, 15);

      if (!settingsMap) {
        return;
      }

      settingsMap.on('click', (event) => {
        latitudeInput.value = event.latlng.lat.toFixed(6);
        longitudeInput.value = event.latlng.lng.toFixed(6);
        updateSettingsMap();
      });
    }

    function updateSettingsMap() {
      initSettingsMap();

      if (!settingsMap) {
        return;
      }

      const latitude = nullableNumber(latitudeInput.value);
      const longitude = nullableNumber(longitudeInput.value);
      const radius = nullableNumber(radiusInput.value);

      if (latitude === null || longitude === null) {
        settingsMap.setView(DEFAULT_MAP_CENTER, 13);
        if (centerMarker) {
          centerMarker.remove();
          centerMarker = null;
        }
        if (radiusCircle) {
          radiusCircle.remove();
          radiusCircle = null;
        }
        return;
      }

      const center = [latitude, longitude];

      if (!centerMarker) {
        centerMarker = window.L.marker(center, { draggable: true }).addTo(settingsMap);
        centerMarker.on('dragend', () => {
          const latLng = centerMarker.getLatLng();
          latitudeInput.value = latLng.lat.toFixed(6);
          longitudeInput.value = latLng.lng.toFixed(6);
          updateSettingsMap();
        });
      } else {
        centerMarker.setLatLng(center);
      }

      if (radius !== null) {
        if (!radiusCircle) {
          radiusCircle = window.L.circle(center, {
            radius,
            color: '#198754',
            fillColor: '#198754',
            fillOpacity: 0.12,
          }).addTo(settingsMap);
        } else {
          radiusCircle.setLatLng(center);
          radiusCircle.setRadius(radius);
        }
      } else if (radiusCircle) {
        radiusCircle.remove();
        radiusCircle = null;
      }

      settingsMap.setView(center, 17);
      setTimeout(() => settingsMap.invalidateSize(), 50);
    }

    async function loadLocationSettings() {
      clearAlert();

      try {
        const data = await api('/api/admin-location.php', { type: 'get' });
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '위치 설정을 불러오지 못했습니다.');
          return;
        }

        fillForm(data.result || {});
      } catch (error) {
        showAlert('위치 설정을 불러오는 중 오류가 발생했습니다.');
      }
    }

    [latitudeInput, longitudeInput, radiusInput].forEach((input) => {
      input.addEventListener('input', updateSettingsMap);
    });
    enabledInput.addEventListener('change', updateEnabledState);

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearAlert();
      saveButton.disabled = true;
      saveButton.textContent = '저장 중...';

      try {
        const data = await api('/api/admin-location.php', readForm());
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '위치 설정 저장에 실패했습니다.');
          return;
        }

        fillForm(data.result || {});
        toast('위치 설정이 저장되었습니다.');
      } catch (error) {
        showAlert('위치 설정 저장 중 오류가 발생했습니다.');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = '저장';
      }
    });

    useCurrentButton.addEventListener('click', () => {
      if (!window.isSecureContext) {
        showAlert('현재 접속 환경에서는 위치 권한을 요청할 수 없습니다. HTTPS 주소로 접속해주세요.');
        return;
      }

      if (!navigator.geolocation) {
        showAlert('이 브라우저에서는 위치 기능을 사용할 수 없습니다.');
        return;
      }

      clearAlert();
      useCurrentButton.disabled = true;
      useCurrentButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> 확인 중...';

      navigator.geolocation.getCurrentPosition((position) => {
        latitudeInput.value = position.coords.latitude.toFixed(6);
        longitudeInput.value = position.coords.longitude.toFixed(6);
        updateSettingsMap();
        toast('현재 위치를 입력했습니다.');
        useCurrentButton.disabled = false;
        useCurrentButton.innerHTML = '<i class="bi bi-crosshair me-1"></i> 현재 위치 사용';
      }, () => {
        showAlert('현재 위치를 가져오지 못했습니다. 브라우저 위치 권한을 확인해주세요.');
        useCurrentButton.disabled = false;
        useCurrentButton.innerHTML = '<i class="bi bi-crosshair me-1"></i> 현재 위치 사용';
      }, {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 10000,
      });
    });

    initSettingsMap();
    updateEnabledState();
    loadLocationSettings();
  }

  function initEdit() {
    const params = new URLSearchParams(window.location.search);
    const id = Number(params.get('id') || 0);
    const formCard = document.getElementById('editFormCard');
    const emptyState = document.getElementById('editEmptyState');
    const emptyTitle = document.getElementById('editEmptyTitle');
    const emptyDescription = document.getElementById('editEmptyDescription');
    const form = document.getElementById('editForm');
    const saveButton = document.getElementById('saveEditButton');
    const clearLocationButton = document.getElementById('clearEditLocationButton');
    const useCurrentLocationButton = document.getElementById('useCurrentEditLocationButton');
    const mapElement = document.getElementById('editLocationMap');
    const field = (fieldId) => document.getElementById(fieldId);
    const statusManualSwitch = field('locationStatusManualSwitch');
    const statusModeText = field('locationStatusModeText');
    const statusInput = field('locationStatusEditInput');
    const latitudeInput = field('locationLatitudeInput');
    const longitudeInput = field('locationLongitudeInput');
    const accuracyInput = field('locationAccuracyInput');
    const distanceInput = field('locationDistanceInput');
    const checkedAtInput = field('locationCheckedAtInput');
    const approvedAtInput = field('locationApprovedAtInput');
    const messageTemplateInput = field('locationMessageTemplateInput');
    const messageInput = field('locationMessageInput');
    const messageTemplates = {
      verified: '위치 인증 완료',
      pending_range: '교내 출석 가능 범위 밖으로 확인되어 관리자 승인 이후 정상 출결로 처리됩니다.',
      pending_settings: '위치 설정을 확인할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.',
      approved: '관리자 승인 완료',
      rejected: '위치 인증이 관리자에 의해 반려되었습니다.',
      unchecked: '위치 인증 미사용',
    };
    let editMap = null;
    let editMarker = null;
    let centerMarker = null;
    let radiusCircle = null;
    let locationSettings = {};

    function showEditForm() {
      if (formCard) {
        formCard.hidden = false;
      }

      if (emptyState) {
        emptyState.hidden = true;
      }
    }

    function showEditEmptyState(title, description) {
      showAlert(title);

      if (formCard) {
        formCard.hidden = true;
      } else {
        form.hidden = true;
      }

      if (emptyTitle) {
        emptyTitle.textContent = title;
      }

      if (emptyDescription) {
        emptyDescription.textContent = description;
      }

      if (emptyState) {
        emptyState.hidden = false;
      }
    }

    if (!id) {
      showEditEmptyState('올바른 출석 기록을 선택해주세요.', '목록에서 수정할 출석 기록을 다시 선택해주세요.');
      return;
    }

    function initEditMap() {
      if (editMap || !mapElement) {
        return;
      }

      editMap = buildMap(mapElement, DEFAULT_MAP_CENTER, 13);

      if (!editMap) {
        return;
      }

      editMap.on('click', (event) => {
        setEditLocationCoordinates(event.latlng.lat, event.latlng.lng, { touchCheckedAt: true });
      });
    }

    function setEditLocationCoordinates(latitude, longitude, options = {}) {
      latitudeInput.value = Number(latitude).toFixed(6);
      longitudeInput.value = Number(longitude).toFixed(6);
      if (options.touchCheckedAt) {
        checkedAtInput.value = toDateTimeLocal(formatDateTime(new Date()));
      }
      updateEditMap();
      updateComputedLocationFields();
    }

    function updateEditMap() {
      initEditMap();

      if (!editMap) {
        return;
      }

      const latitude = nullableNumber(latitudeInput.value);
      const longitude = nullableNumber(longitudeInput.value);
      const hasCenter = hasConfiguredCenter();

      renderAttendanceCenter();

      if (latitude === null || longitude === null) {
        if (editMarker) {
          editMarker.remove();
          editMarker = null;
        }
        editMap.setView(hasCenter ? settingsCenter() : DEFAULT_MAP_CENTER, hasCenter ? 17 : 13);
        setTimeout(() => editMap.invalidateSize(), 50);
        return;
      }

      const center = [latitude, longitude];

      if (!editMarker) {
        editMarker = window.L.marker(center, { draggable: true }).addTo(editMap);
        editMarker.on('dragend', () => {
          const latLng = editMarker.getLatLng();
          setEditLocationCoordinates(latLng.lat, latLng.lng, { touchCheckedAt: true });
        });
      } else {
        editMarker.setLatLng(center);
      }

      if (hasCenter) {
        editMap.fitBounds([settingsCenter(), center], { padding: [36, 36], maxZoom: 17 });
      } else {
        editMap.setView(center, 17);
      }
      setTimeout(() => editMap.invalidateSize(), 50);
    }

    function renderAttendanceCenter() {
      if (!editMap) {
        return;
      }

      const hasCenter = hasConfiguredCenter();

      if (!hasCenter) {
        if (centerMarker) {
          centerMarker.remove();
          centerMarker = null;
        }
        if (radiusCircle) {
          radiusCircle.remove();
          radiusCircle = null;
        }
        return;
      }

      const center = settingsCenter();
      const radius = nullableNumber(locationSettings.radius_meters);

      if (!centerMarker) {
        centerMarker = window.L.marker(center).addTo(editMap).bindPopup('출석 가능 중심');
      } else {
        centerMarker.setLatLng(center);
      }

      if (radius !== null) {
        if (!radiusCircle) {
          radiusCircle = window.L.circle(center, {
            radius,
            color: '#198754',
            fillColor: '#198754',
            fillOpacity: 0.12,
          }).addTo(editMap);
        } else {
          radiusCircle.setLatLng(center);
          radiusCircle.setRadius(radius);
        }
      } else if (radiusCircle) {
        radiusCircle.remove();
        radiusCircle = null;
      }
    }

    function hasConfiguredCenter() {
      return locationSettings.latitude !== null && locationSettings.latitude !== undefined
        && locationSettings.longitude !== null && locationSettings.longitude !== undefined;
    }

    function settingsCenter() {
      return [Number(locationSettings.latitude), Number(locationSettings.longitude)];
    }

    function settingsConfiguredForAttendance() {
      return Boolean(locationSettings.configured)
        && hasConfiguredCenter()
        && locationSettings.radius_meters !== null
        && locationSettings.radius_meters !== undefined;
    }

    function calculateDistanceMeters(fromLatitude, fromLongitude, toLatitude, toLongitude) {
      const earthRadiusMeters = 6371000;
      const fromLatRad = fromLatitude * Math.PI / 180;
      const toLatRad = toLatitude * Math.PI / 180;
      const deltaLat = (toLatitude - fromLatitude) * Math.PI / 180;
      const deltaLng = (toLongitude - fromLongitude) * Math.PI / 180;
      const a = Math.sin(deltaLat / 2) ** 2
        + Math.cos(fromLatRad) * Math.cos(toLatRad) * Math.sin(deltaLng / 2) ** 2;

      return earthRadiusMeters * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function computedLocationState() {
      const latitude = nullableNumber(latitudeInput.value);
      const longitude = nullableNumber(longitudeInput.value);

      if (latitude === null || longitude === null) {
        return {
          status: 'unchecked',
          distance: null,
          message: messageTemplates.unchecked,
        };
      }

      if (!settingsConfiguredForAttendance()) {
        return {
          status: 'pending',
          distance: null,
          message: messageTemplates.pending_settings,
        };
      }

      const distance = calculateDistanceMeters(latitude, longitude, Number(locationSettings.latitude), Number(locationSettings.longitude));
      const radius = Number(locationSettings.radius_meters);

      if (distance <= radius) {
        return {
          status: 'verified',
          distance,
          message: messageTemplates.verified,
        };
      }

      return {
        status: 'pending',
        distance,
        message: messageTemplates.pending_range,
      };
    }

    function isManualLocationStatus() {
      return Boolean(statusManualSwitch?.checked);
    }

    function updateLocationStatusModeState() {
      const manual = isManualLocationStatus();
      const state = computedLocationState();

      if (!manual) {
        statusInput.value = state.status;
        messageTemplateInput.value = 'auto';
        messageInput.value = state.message;
        approvedAtInput.value = '';
      }

      statusInput.disabled = !manual;
      distanceInput.disabled = true;
      messageTemplateInput.disabled = !manual;
      messageInput.disabled = !manual;
      approvedAtInput.disabled = !manual;

      if (statusModeText) {
        statusModeText.textContent = manual
          ? '수동 모드에서는 상태, 메시지, 승인/반려 시각을 직접 조정할 수 있습니다.'
          : '자동 모드에서는 상태, 거리, 메시지가 좌표와 위치 설정을 기준으로 계산됩니다.';
      }
    }

    function updateComputedLocationFields() {
      const state = computedLocationState();

      if (!isManualLocationStatus()) {
        statusInput.value = state.status;
      }

      distanceInput.value = state.distance === null ? '' : state.distance.toFixed(1);

      if (messageTemplateInput.value === 'auto') {
        messageInput.value = state.message;
      }

      updateLocationStatusModeState();
      updateMessageEditState();
    }

    function updateMessageEditState(options = {}) {
      const template = messageTemplateInput.value;
      const custom = template === 'custom';
      messageInput.readOnly = !custom;
      messageInput.placeholder = custom ? '내용을 입력해주세요' : '';

      if (custom) {
        if (options.clearCustom) {
          messageInput.value = '';
        }
        return;
      }

      if (template === 'auto') {
        messageInput.value = computedLocationState().message;
        return;
      }

      messageInput.value = messageTemplates[template] || '';
    }

    function matchingMessageTemplate(message) {
      const text = String(message || '').trim();
      const entry = Object.entries(messageTemplates).find(([, value]) => value === text);
      return entry ? entry[0] : (text ? 'custom' : 'auto');
    }

    function fillEditForm(record) {
      showEditForm();
      field('editIdInput').value = record.id;
      field('studentNoInput').value = record.student_no;
      field('nameInput').value = record.name;
      field('createdAtInput').value = toDateTimeLocal(record.attend_datetime || record.created_at);
      field('locationStatusEditInput').value = record.location_status || 'unchecked';
      field('locationLatitudeInput').value = record.location_latitude ?? '';
      field('locationLongitudeInput').value = record.location_longitude ?? '';
      field('locationAccuracyInput').value = record.location_accuracy ?? '';
      field('locationDistanceInput').value = record.location_distance_meters ?? '';
      field('locationMessageInput').value = record.location_message || '';
      field('locationCheckedAtInput').value = toDateTimeLocal(record.location_checked_at);
      approvedAtInput.value = toDateTimeLocal(record.location_approved_at);
      const messageTemplate = matchingMessageTemplate(record.location_message);
      const computed = computedLocationState();
      const savedStatus = record.location_status || 'unchecked';
      const savedMessage = String(record.location_message || '').trim();
      const hasManualValue = savedStatus !== computed.status
        || (savedMessage !== '' && savedMessage !== computed.message)
        || Boolean(record.location_approved_at);

      if (statusManualSwitch) {
        statusManualSwitch.checked = hasManualValue;
      }

      messageTemplateInput.value = hasManualValue ? messageTemplate : 'auto';
      updateEditMap();
      updateComputedLocationFields();
    }

    function readEditPayload(studentNo, name) {
      const manual = isManualLocationStatus();
      const computed = computedLocationState();

      return {
        type: 'update',
        id: Number(field('editIdInput').value),
        student_no: studentNo,
        name,
        created_at: fromDateTimeLocal(field('createdAtInput').value),
        location_latitude: nullableNumber(field('locationLatitudeInput').value),
        location_longitude: nullableNumber(field('locationLongitudeInput').value),
        location_accuracy: nullableNumber(field('locationAccuracyInput').value),
        location_status_mode: manual ? 'manual' : 'auto',
        location_status: manual ? field('locationStatusEditInput').value : computed.status,
        location_message_template: manual ? field('locationMessageTemplateInput').value : 'auto',
        location_message: manual ? field('locationMessageInput').value.trim() : computed.message,
        location_checked_at: fromDateTimeLocal(field('locationCheckedAtInput').value) || null,
        location_approved_at: manual ? (fromDateTimeLocal(approvedAtInput.value) || null) : null,
      };
    }

    async function loadLocationSettings() {
      try {
        const data = await api('/api/admin-location.php', { type: 'get' });
        locationSettings = data?.status === 1 ? (data.result || {}) : {};
      } catch (error) {
        locationSettings = {};
      }
    }

    async function loadEditRecord() {
      try {
        await loadLocationSettings();
        const data = await api('/api/admin-edit.php', { type: 'get', id });
        if (!data) return;

        if (data.status !== 1) {
          showEditEmptyState(
            data.msg || '출석 기록을 불러오지 못했습니다.',
            '이미 삭제되었거나 존재하지 않는 기록일 수 있습니다. 목록에서 다시 선택해주세요.'
          );
          return;
        }

        fillEditForm(data.result);
      } catch (error) {
        showEditEmptyState(
          '출석 기록을 불러오는 중 오류가 발생했습니다.',
          '네트워크 상태를 확인한 뒤 목록에서 다시 시도해주세요.'
        );
      }
    }

    [latitudeInput, longitudeInput].forEach((input) => {
      input.addEventListener('input', () => {
        updateEditMap();
        updateComputedLocationFields();
      });
    });

    if (statusManualSwitch) {
      statusManualSwitch.addEventListener('change', () => {
        updateComputedLocationFields();
      });
    }

    messageTemplateInput.addEventListener('change', () => {
      updateMessageEditState({ clearCustom: messageTemplateInput.value === 'custom' });
    });

    clearLocationButton.addEventListener('click', () => {
      latitudeInput.value = '';
      longitudeInput.value = '';
      accuracyInput.value = '';
      checkedAtInput.value = '';
      updateEditMap();
      updateComputedLocationFields();
    });

    useCurrentLocationButton.addEventListener('click', () => {
      if (!window.isSecureContext) {
        showAlert('현재 접속 환경에서는 위치 권한을 요청할 수 없습니다. HTTPS 주소로 접속해주세요.');
        return;
      }

      if (!navigator.geolocation) {
        showAlert('이 브라우저에서는 위치 기능을 사용할 수 없습니다.');
        return;
      }

      clearAlert();
      useCurrentLocationButton.disabled = true;
      useCurrentLocationButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> 확인 중...';

      navigator.geolocation.getCurrentPosition((position) => {
        setEditLocationCoordinates(position.coords.latitude, position.coords.longitude, { touchCheckedAt: true });
        accuracyInput.value = Number(position.coords.accuracy || 0).toFixed(1);
        toast('현재 위치를 입력했습니다.');
        useCurrentLocationButton.disabled = false;
        useCurrentLocationButton.innerHTML = '<i class="bi bi-crosshair me-1"></i> 현재 위치 가져오기';
      }, () => {
        showAlert('현재 위치를 가져오지 못했습니다. 브라우저 위치 권한을 확인해주세요.');
        useCurrentLocationButton.disabled = false;
        useCurrentLocationButton.innerHTML = '<i class="bi bi-crosshair me-1"></i> 현재 위치 가져오기';
      }, {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 10000,
      });
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearAlert();
      saveButton.disabled = true;
      saveButton.textContent = '저장 중...';
      const studentNo = field('studentNoInput').value.trim();
      const name = field('nameInput').value.trim();

      if (!validateStudentInfo(studentNo, name)) {
        saveButton.disabled = false;
        saveButton.textContent = '저장';
        return;
      }

      try {
        const data = await api('/api/admin-edit.php', readEditPayload(studentNo, name));

        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '저장에 실패했습니다.');
          return;
        }

        toast('저장되었습니다.');
        const date = data.result?.attend_date || field('createdAtInput').value.slice(0, 10) || todayForInput();
        window.location.href = `/admin/list.php?date=${encodeURIComponent(date)}`;
      } catch (error) {
        showAlert('저장 중 오류가 발생했습니다.');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = '저장';
      }
    });

    updateLocationStatusModeState();
    loadEditRecord();
  }

  function initSystem() {
    const resetForm = document.getElementById('systemResetForm');
    const resetPasswordInput = document.getElementById('systemResetPasswordInput');
    const resetButton = document.getElementById('systemResetButton');
    const updateCheckButton = document.getElementById('updateCheckButton');
    const updateInstallForm = document.getElementById('updateInstallForm');
    const updatePasswordInput = document.getElementById('updatePasswordInput');
    const updateInstallButton = document.getElementById('updateInstallButton');
    const repairInstallForm = document.getElementById('repairInstallForm');
    const repairPasswordInput = document.getElementById('repairPasswordInput');
    const repairInstallButton = document.getElementById('repairInstallButton');
    const releaseSelect = document.getElementById('releaseSelect');
    const releaseList = document.getElementById('releaseList');
    const currentVersionText = document.getElementById('currentVersionText');
    const latestVersionText = document.getElementById('latestVersionText');
    const releaseDetailModal = document.getElementById('releaseDetailModal');
    const closeReleaseDetailButton = document.getElementById('closeReleaseDetailButton');
    const releaseDetailTitle = document.getElementById('releaseDetailTitle');
    const releaseDetailMeta = document.getElementById('releaseDetailMeta');
    const releaseDetailBody = document.getElementById('releaseDetailBody');
    const updateProgressModal = document.getElementById('updateProgressModal');
    const updateProgressTitle = document.getElementById('updateProgressTitle');
    const updateProgressFill = document.getElementById('updateProgressFill');
    const updateProgressSteps = document.getElementById('updateProgressSteps');
    const serverInfoList = document.getElementById('serverInfoList');
    const adminSessionList = document.getElementById('adminSessionList');
    const revokeOtherSessionsButton = document.getElementById('revokeOtherSessionsButton');
    const progressStages = ['다운로드 중', '백업 중', '설치 중', '적용 중', '마무리 하는 중'];
    let releasesCache = [];
    let progressTimer = null;
    let progressIndex = 0;
    let serverInfoServerTime = null;
    let serverInfoUptimeSeconds = null;
    let serverInfoClockTimer = null;
    let serverInfoSyncTimer = null;
    let serverInfoSyncIntervalMs = DEFAULT_SYNC_INTERVAL_MS;

    if (!resetForm || !updateCheckButton) {
      return;
    }

    resetForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const scope = new FormData(resetForm).get('reset_scope') || 'attendance';
      const isAll = scope === 'all';
      const confirmed = await confirmAction(
        isAll
          ? '출석 기록, 앱 설정, 관리자 계정과 로그인 세션을 모두 삭제하고 초기 설치 상태로 되돌릴까요?'
          : '모든 출석 기록을 초기화할까요?',
        {
          title: '초기화 확인',
          confirmText: '초기화',
          confirmClass: 'btn-danger',
        }
      );

      if (!confirmed) {
        return;
      }

      if (isAll && !await confirmAction('정말 모든 데이터를 초기화할까요? 이 작업 후에는 설치 마법사를 다시 진행해야 합니다.', {
        title: '최종 확인',
        confirmText: '모두 초기화',
        confirmClass: 'btn-danger',
      })) {
        return;
      }

      resetButton.disabled = true;
      resetButton.textContent = '초기화 중...';

      try {
        const data = await api('/api/admin-system.php', {
          type: 'reset',
          scope,
          password: resetPasswordInput.value,
        });
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '초기화에 실패했습니다.');
          return;
        }

        resetPasswordInput.value = '';
        toast(data.msg || '초기화되었습니다.');
        if (isAll) {
          localStorage.removeItem(TOKEN_KEY);
          window.location.href = '/install.php';
        }
      } catch (error) {
        showAlert('초기화 중 오류가 발생했습니다.');
      } finally {
        resetButton.disabled = false;
        resetButton.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> 초기화 실행';
      }
    });

    if (revokeOtherSessionsButton) {
      revokeOtherSessionsButton.addEventListener('click', async () => {
        if (!await confirmAction('현재 기기를 제외한 모든 로그인 세션을 종료할까요?', {
          title: '다른 세션 모두 종료',
          confirmText: '모두 종료',
          confirmClass: 'btn-danger',
        })) {
          return;
        }

        revokeOtherSessionsButton.disabled = true;

        try {
          const data = await api('/api/admin-sessions.php', { type: 'revoke_others' });
          if (!data) return;

          if (data.status !== 1) {
            showAlert(data.msg || '세션 종료에 실패했습니다.');
            return;
          }

          toast(`${Number(data.result?.revoked_count || 0)}개 세션을 종료했습니다.`);
          loadSessions();
        } catch (error) {
          showAlert('세션 종료 중 오류가 발생했습니다.');
        } finally {
          revokeOtherSessionsButton.disabled = false;
        }
      });
    }

    updateCheckButton.addEventListener('click', () => loadUpdateInfo(true));
    closeReleaseDetailButton.addEventListener('click', closeReleaseDetail);
    releaseDetailModal.addEventListener('click', (event) => {
      if (event.target === releaseDetailModal) {
        closeReleaseDetail();
      }
    });

    updateInstallForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const tag = releaseSelect.value;

      if (!tag) {
        showAlert('설치할 릴리즈를 선택해주세요.');
        return;
      }

      const confirmed = await confirmAction(`${tag} 버전으로 업데이트할까요?`, {
        title: '업데이트 확인',
        confirmText: '업데이트',
        confirmClass: 'btn-success',
        type: 'info',
      });

      if (!confirmed) {
        return;
      }

      updateInstallButton.disabled = true;
      updateInstallButton.textContent = '업데이트 중...';
      openUpdateProgress('업데이트 중');

      try {
        const data = await api('/api/admin-system.php', {
          type: 'update_install',
          tag,
          password: updatePasswordInput.value,
        });
        if (!data) return;

        if (data.status !== 1) {
          closeUpdateProgress();
          showAlert(data.msg || '업데이트에 실패했습니다.');
          return;
        }

        finishUpdateProgress();
        updatePasswordInput.value = '';
        const installedVersion = data.result?.installed_version || tag;
        const backupPath = data.result?.backup_path || '-';
        currentVersionText.textContent = installedVersion;
        await wait(500);
        await openAdminDialog({
          title: '업데이트 완료',
          message: `${installedVersion} 업데이트 완료\n백업경로: ${backupPath}`,
          confirmText: '확인',
          confirmClass: 'btn-success',
          type: 'info',
        });
      } catch (error) {
        closeUpdateProgress();
        showAlert('업데이트 중 오류가 발생했습니다.');
      } finally {
        updateInstallButton.disabled = false;
        updateInstallButton.innerHTML = '<i class="bi bi-download me-1"></i> 업데이트';
      }
    });

    repairInstallForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const currentVersion = currentVersionText.textContent.trim();
      const confirmed = await confirmAction(`${currentVersion} 파일을 다시 설치해 복구할까요? 데이터베이스와 설정 파일은 보존됩니다.`, {
        title: '재설치(복구) 확인',
        confirmText: '재설치',
        confirmClass: 'btn-success',
        type: 'info',
      });

      if (!confirmed) {
        return;
      }

      repairInstallButton.disabled = true;
      repairInstallButton.textContent = '재설치 중...';
      openUpdateProgress('재설치(복구) 중');

      try {
        const data = await api('/api/admin-system.php', {
          type: 'repair_install',
          password: repairPasswordInput.value,
        });
        if (!data) return;

        if (data.status !== 1) {
          closeUpdateProgress();
          showAlert(data.msg || '재설치(복구)에 실패했습니다.');
          return;
        }

        finishUpdateProgress();
        repairPasswordInput.value = '';
        const installedVersion = data.result?.installed_version || currentVersion || '-';
        const backupPath = data.result?.backup_path || '-';
        currentVersionText.textContent = installedVersion;
        await wait(500);
        await openAdminDialog({
          title: '재설치(복구) 완료',
          message: `${installedVersion} 재설치 완료\n백업경로: ${backupPath}`,
          confirmText: '확인',
          confirmClass: 'btn-success',
          type: 'info',
        });
      } catch (error) {
        closeUpdateProgress();
        showAlert('재설치(복구) 중 오류가 발생했습니다.');
      } finally {
        repairInstallButton.disabled = false;
        repairInstallButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> 재설치(복구)';
      }
    });

    async function loadServerInfo() {
      try {
        const data = await api('/api/admin-system.php', { type: 'server_info' });
        if (!data) return;

        if (data.status !== 1) {
          if (!serverInfoList.querySelector('[data-server-info-value]')) {
            serverInfoList.innerHTML = '<p class="text-secondary mb-0">서버 정보를 불러오지 못했습니다.</p>';
          }
          return;
        }

        renderServerInfo(data.result || {});
        syncServerInfoLive(data.result || {});
        scheduleServerInfoSync(data.result || {});

      } catch (error) {
        scheduleServerInfoSync(null);

        if (!serverInfoList.querySelector('[data-server-info-value]')) {
          serverInfoList.innerHTML = '<p class="text-secondary mb-0">서버 정보를 불러오지 못했습니다.</p>';
        }
      }
    }

    async function loadSessions() {
      if (!adminSessionList) {
        return;
      }

      try {
        const data = await api('/api/admin-sessions.php', { type: 'list' });
        if (!data) return;

        if (data.status !== 1) {
          adminSessionList.innerHTML = '<p class="text-secondary mb-0">로그인 세션을 불러오지 못했습니다.</p>';
          return;
        }

        renderSessions(Array.isArray(data.result?.sessions) ? data.result.sessions : []);
      } catch (error) {
        adminSessionList.innerHTML = '<p class="text-secondary mb-0">로그인 세션을 불러오지 못했습니다.</p>';
      }
    }

    function renderSessions(sessions) {
      if (!sessions.length) {
        adminSessionList.innerHTML = '<p class="text-secondary mb-0">활성 로그인 세션이 없습니다.</p>';
        return;
      }

      adminSessionList.innerHTML = sessions.map((session) => `
        <article class="session-item ${session.is_current ? 'is-current' : ''}">
          <div class="session-item-icon"><i class="bi ${session.is_current ? 'bi-laptop-fill' : 'bi-laptop'}"></i></div>
          <div class="session-item-body">
            <div class="session-item-title">
              <strong>${escapeHtml(session.ip_address || '알 수 없음')}</strong>
              ${session.is_current ? '<span class="badge text-bg-success">현재 세션</span>' : ''}
            </div>
            <span class="session-user-agent" title="${escapeHtml(session.user_agent || '')}">${escapeHtml(session.user_agent || '알 수 없음')}</span>
            <dl class="session-meta">
              <div><dt>로그인</dt><dd>${escapeHtml(formatDateTimeText(session.created_at))}</dd></div>
              <div><dt>최근 활동</dt><dd>${escapeHtml(formatDateTimeText(session.last_seen_at))}</dd></div>
              <div><dt>만료</dt><dd>${escapeHtml(formatDateTimeText(session.expired_at))}</dd></div>
            </dl>
          </div>
          <button class="btn btn-sm btn-outline-danger" type="button" data-revoke-session="${Number(session.id)}">
            강제 로그아웃
          </button>
        </article>
      `).join('');

      adminSessionList.querySelectorAll('[data-revoke-session]').forEach((button) => {
        button.addEventListener('click', async () => {
          const sessionId = Number(button.dataset.revokeSession);
          const session = sessions.find((item) => Number(item.id) === sessionId);
          const message = session?.is_current
            ? '현재 세션을 종료하면 즉시 로그인 화면으로 이동합니다. 계속할까요?'
            : '선택한 로그인 세션을 강제로 종료할까요?';

          if (!await confirmAction(message, {
            title: '세션 강제 로그아웃',
            confirmText: '로그아웃',
            confirmClass: 'btn-danger',
          })) {
            return;
          }

          button.disabled = true;

          try {
            const data = await api('/api/admin-sessions.php', {
              type: 'revoke',
              session_id: sessionId,
            });
            if (!data) return;

            if (data.status !== 1) {
              showAlert(data.msg || '세션 종료에 실패했습니다.');
              button.disabled = false;
              return;
            }

            if (data.result?.revoked_current) {
              redirectLogin('session-revoked');
              return;
            }

            toast('선택한 세션을 종료했습니다.');
            loadSessions();
          } catch (error) {
            button.disabled = false;
            showAlert('세션 종료 중 오류가 발생했습니다.');
          }
        });
      });
    }

    async function loadUpdateInfo(showSuccess) {
      updateCheckButton.disabled = true;
      updateCheckButton.textContent = '확인 중...';
      latestVersionText.textContent = '릴리즈 정보를 가져오는 중입니다.';

      try {
        const data = await api('/api/admin-system.php', { type: 'update_check' });
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '릴리즈 정보를 확인할 수 없습니다.');
          return;
        }

        renderUpdateInfo(data.result || {});

        if (showSuccess) {
          toast(data.result?.update_available ? '설치 가능한 업데이트가 있습니다.' : '현재 최신 버전입니다.');
        }
      } catch (error) {
        latestVersionText.textContent = '릴리즈 정보를 확인할 수 없습니다.';
        showAlert('업데이트 확인 중 오류가 발생했습니다.');
      } finally {
        updateCheckButton.disabled = false;
        updateCheckButton.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> 업데이트 확인';
      }
    }

    function renderUpdateInfo(info) {
      releasesCache = Array.isArray(info.releases) ? info.releases : [];
      const latest = info.latest || null;
      const currentVersion = info.current_version || currentVersionText.textContent;
      currentVersionText.textContent = currentVersion;

      if (!latest) {
        latestVersionText.textContent = '릴리즈가 없습니다.';
        updateInstallForm.hidden = true;
        releaseList.innerHTML = '';
        return;
      }

      latestVersionText.textContent = info.update_available
        ? `최신 버전 ${latest.tag_name} 업데이트 가능`
        : `최신 버전 ${latest.tag_name}`;
      updateInstallForm.hidden = !info.update_available;
      const installableReleases = releasesCache.filter((release) => release.tag_name && release.is_newer);
      releaseSelect.innerHTML = installableReleases
        .map((release) => `<option value="${escapeHtml(release.tag_name)}">${escapeHtml(release.tag_name)}${release.prerelease ? ' (pre-release)' : ''}</option>`)
        .join('');

      if (latest.tag_name && installableReleases.some((release) => release.tag_name === latest.tag_name)) {
        releaseSelect.value = latest.tag_name;
      }

      renderReleaseList();
    }

    function renderReleaseList() {
      if (!releasesCache.length) {
        releaseList.innerHTML = '<p class="text-secondary mb-0">표시할 릴리즈가 없습니다.</p>';
        return;
      }

      releaseList.innerHTML = `
        ${releasesCache.map((release, index) => `
          <button class="release-item release-item-button" type="button" data-release-index="${index}">
            <strong>${escapeHtml(release.tag_name)}</strong>
            <span>${escapeHtml(release.name || release.tag_name)}</span>
            <small>${escapeHtml(release.published_at_text || formatReleaseDate(release.published_at) || '태그')}</small>
          </button>
        `).join('')}
      `;

      releaseList.querySelectorAll('[data-release-index]').forEach((button) => {
        button.addEventListener('click', () => {
          const release = releasesCache[Number(button.dataset.releaseIndex)];
          if (release) {
            openReleaseDetail(release);
          }
        });
      });

    }

    function formatReleaseDate(value) {
      const date = new Date(value || '');

      if (Number.isNaN(date.getTime())) {
        return '';
      }

      return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
      ].join('-') + ' ' + [
        String(date.getHours()).padStart(2, '0'),
        String(date.getMinutes()).padStart(2, '0'),
        String(date.getSeconds()).padStart(2, '0'),
      ].join(':');
    }

    function openReleaseDetail(release) {
      releaseDetailTitle.textContent = release.tag_name || '릴리즈 정보';
      releaseDetailMeta.innerHTML = `
        <span>${escapeHtml(release.name || release.tag_name || '-')}</span>
        <span>${escapeHtml(release.published_at_text || formatReleaseDate(release.published_at) || '날짜 없음')}</span>
        ${release.prerelease ? '<span>Pre-release</span>' : ''}
      `;
      releaseDetailBody.textContent = (release.body || '').trim() || '릴리즈 상세 내용이 없습니다.';
      releaseDetailModal.hidden = false;
    }

    function closeReleaseDetail() {
      releaseDetailModal.hidden = true;
    }

    function renderServerInfo(info) {
      const rows = [
        ['서버 시간', info.server_time || '-', 'server_time'],
        ['업타임', info.uptime || '확인 불가', 'uptime'],
        ['PHP', `${info.php_version || '-'} (${info.php_sapi || '-'})`, 'php'],
        ['OS', info.os || '-', 'os'],
        ['시간대', info.timezone || '-', 'timezone'],
        ['메모리 제한', info.memory_limit || '-', 'memory'],
        ['URL fopen', info.allow_url_fopen ? '사용 가능' : '사용 불가', 'url_fopen'],
      ];
      const extensions = Array.isArray(info.extensions) ? info.extensions : [];

      serverInfoList.innerHTML = `
        <dl class="server-info-kv">
          ${rows.map(([label, value, key]) => `<div><dt>${escapeHtml(label)}</dt><dd data-server-info-value="${escapeHtml(key)}">${escapeHtml(value)}</dd></div>`).join('')}
        </dl>
        <div class="extension-grid">
          ${extensions.map((extension) => `
            <span class="extension-pill ${extension.loaded ? 'is-ok' : 'is-missing'}">
              ${escapeHtml(extension.name)}
              <small>${extension.loaded ? '설치됨' : (extension.required ? '필수' : '선택')}</small>
            </span>
          `).join('')}
        </div>
      `;
    }

    function syncServerInfoLive(info) {
      if (info.server_time) {
        serverInfoServerTime = parseServerTime(info.server_time);
      }

      if (Number.isFinite(Number(info.uptime_seconds))) {
        serverInfoUptimeSeconds = Number(info.uptime_seconds);
      }

      updateServerInfoLiveText();

      if (serverInfoClockTimer) {
        return;
      }

      serverInfoClockTimer = setInterval(() => {
        if (serverInfoServerTime) {
          serverInfoServerTime.setSeconds(serverInfoServerTime.getSeconds() + 1);
        }

        if (serverInfoUptimeSeconds !== null) {
          serverInfoUptimeSeconds += 1;
        }

        updateServerInfoLiveText();
      }, 1000);
    }

    function updateServerInfoLiveText() {
      const serverTimeText = serverInfoList.querySelector('[data-server-info-value="server_time"]');
      const uptimeText = serverInfoList.querySelector('[data-server-info-value="uptime"]');

      if (serverTimeText && serverInfoServerTime) {
        serverTimeText.textContent = formatDateTime(serverInfoServerTime);
      }

      if (uptimeText && serverInfoUptimeSeconds !== null) {
        uptimeText.textContent = formatUptimeSeconds(serverInfoUptimeSeconds);
      }
    }

    function scheduleServerInfoSync(info = null) {
      const nextIntervalMs = syncIntervalMs(info);

      if (serverInfoSyncTimer && nextIntervalMs === serverInfoSyncIntervalMs) {
        return;
      }

      serverInfoSyncIntervalMs = nextIntervalMs;

      if (serverInfoSyncTimer) {
        clearInterval(serverInfoSyncTimer);
      }

      serverInfoSyncTimer = setInterval(() => loadServerInfo(), serverInfoSyncIntervalMs);
    }

    function openUpdateProgress(title = '업데이트 중') {
      progressIndex = 0;
      if (updateProgressTitle) {
        updateProgressTitle.textContent = title;
      }
      renderUpdateProgress();
      updateProgressModal.hidden = false;
      progressTimer = setInterval(() => {
        progressIndex = Math.min(progressStages.length - 1, progressIndex + 1);
        renderUpdateProgress();
      }, 1800);
    }

    function renderUpdateProgress() {
      const percent = Math.round(((progressIndex + 1) / progressStages.length) * 100);
      updateProgressFill.style.width = `${percent}%`;
      updateProgressSteps.innerHTML = progressStages.map((stage, index) => `
        <li class="${index < progressIndex ? 'is-done' : (index === progressIndex ? 'is-active' : '')}">
          ${escapeHtml(stage)}
        </li>
      `).join('');
    }

    function finishUpdateProgress() {
      progressIndex = progressStages.length - 1;
      renderUpdateProgress();
      closeUpdateProgress(450);
    }

    function closeUpdateProgress(delay = 0) {
      if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
      }

      setTimeout(() => {
        updateProgressModal.hidden = true;
      }, delay);
    }

    loadUpdateInfo(false);
    loadServerInfo();
    loadSessions();
  }

  function initPassword() {
    const form = document.getElementById('passwordForm');
    const button = document.getElementById('changePasswordButton');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearAlert();
      const newPassword = document.getElementById('newPasswordInput').value;
      const newPasswordConfirm = document.getElementById('newPasswordConfirmInput').value;

      if (!validateLength(newPassword, '새 비밀번호는', inputRange(document.getElementById('newPasswordInput'), 4, 64))) {
        return;
      }

      if (newPassword !== newPasswordConfirm) {
        showAlert('새 비밀번호 확인이 일치하지 않습니다.');
        return;
      }

      button.disabled = true;
      button.textContent = '변경 중...';

      try {
        const data = await api('/api/admin-password.php', {
          old_password: document.getElementById('oldPasswordInput').value,
          new_password: newPassword,
        });

        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '비밀번호 변경에 실패했습니다.');
          return;
        }

        toast('비밀번호가 변경되었습니다. 다시 로그인해주세요.');
        setTimeout(() => redirectLogin('password-change'), 800);
      } catch (error) {
        showAlert('비밀번호 변경 중 오류가 발생했습니다.');
      } finally {
        button.disabled = false;
        button.textContent = '비밀번호 변경';
      }
    });
  }

  const logoutButtons = document.querySelectorAll('.js-logout-button');
  initPasswordToggles(document);
  logoutButtons.forEach((logoutButton) => {
    logoutButton.addEventListener('click', async () => {
      try {
        const data = await api('/api/admin-logout.php');
        if (data === null) return;
        redirectLogin('logout');
      } catch (error) {
        redirectLogin('logout');
      }
    });
  });

  if (path.endsWith('/dash.php')) initDash();
  if (path.endsWith('/list.php')) initList();
  if (path.endsWith('/location.php')) initLocation();
  if (path.endsWith('/edit.php')) initEdit();
  if (path.endsWith('/system.php')) initSystem();
  if (path.endsWith('/password.php')) initPassword();
})();
