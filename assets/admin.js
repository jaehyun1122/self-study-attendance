(function () {
  const TOKEN_KEY = 'admin_token';
  const DEFAULT_SYNC_INTERVAL_MS = 5000;
  const token = window.ADMIN_TOKEN || localStorage.getItem(TOKEN_KEY);
  const path = window.location.pathname;

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

  function showAlert(message, type = 'danger') {
    const box = document.getElementById('adminAlert');
    if (!box) {
      toast(message, type === 'danger' ? 'error' : 'success');
      return;
    }

    box.innerHTML = `<div class="alert alert-${type}" role="alert">${escapeHtml(message)}</div>`;
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

  function formatDateTime(date) {
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

  function formatDateTimeText(value) {
    const text = String(value || '').trim();

    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(text)) {
      return text;
    }

    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(text)) {
      return `${text}:00`;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
      return `${text} 00:00:00`;
    }

    const date = new Date(text);
    return Number.isNaN(date.getTime()) ? text || '-' : formatDateTime(date);
  }

  function parseServerTime(value) {
    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);

    if (!match) {
      return new Date();
    }

    return new Date(
      Number(match[1]),
      Number(match[2]) - 1,
      Number(match[3]),
      Number(match[4]),
      Number(match[5]),
      Number(match[6])
    );
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

  function textLength(value) {
    return Array.from(String(value || '')).length;
  }

  function inputRange(input, fallbackMin, fallbackMax) {
    const min = Number(input?.getAttribute('minlength') || fallbackMin);
    const max = Number(input?.getAttribute('maxlength') || fallbackMax);
    return {
      min: Number.isFinite(min) ? min : fallbackMin,
      max: Number.isFinite(max) ? max : fallbackMax,
    };
  }

  function lengthMessage(subject, range) {
    if (range.min === range.max) {
      return `${subject} ${range.min}자로 입력해주세요.`;
    }

    if (range.min < 1) {
      return `${subject} ${range.max}자까지 입력할 수 있습니다.`;
    }

    return `${subject} ${range.min}자 이상 ${range.max}자까지 입력할 수 있습니다.`;
  }

  function validateLength(value, subject, range) {
    const length = textLength(value);

    if (length < range.min || length > range.max) {
      showAlert(lengthMessage(subject, range));
      return false;
    }

    return true;
  }

  function validateStudentInfo(studentNo, name) {
    const studentNoInput = document.getElementById('studentNoInput');
    const nameInput = document.getElementById('nameInput');

    return validateLength(studentNo, '학번은', inputRange(studentNoInput, 5, 5))
      && validateLength(name, '이름은', inputRange(nameInput, 1, 5));
  }

  function initDash() {
    const todayCount = document.getElementById('todayCount');
    const totalCount = document.getElementById('totalCount');
    const serverTime = document.getElementById('summaryServerTime');

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
    const dateInput = document.getElementById('dateInput');
    const form = document.getElementById('attendanceFilter');
    const button = document.getElementById('loadListButton');
    const exportButton = document.getElementById('exportListButton');
    let currentRows = [];

    dateInput.value = params.get('date') || todayForInput();

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const url = new URL(window.location.href);
      url.searchParams.set('date', dateInput.value);
      window.history.replaceState(null, '', url);
      loadList();
    });

    exportButton.addEventListener('click', () => {
      exportCsv(currentRows);
    });

    async function loadList() {
      clearAlert();
      currentRows = [];
      renderLoading();
      button.disabled = true;
      exportButton.disabled = true;
      button.textContent = '조회 중...';

      try {
        const data = await api('/api/admin-list.php', { date: dateInput.value });
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

    function renderLoading() {
      document.getElementById('listTitle').textContent = `${dateInput.value} 출석 기록`;
      document.getElementById('attendanceCount').textContent = '조회 중';
      document.getElementById('attendanceTableBody').innerHTML = `
        <tr>
          <td class="text-center py-5" colspan="5">
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
          <td class="text-center py-5" colspan="5">
            <div class="empty-table-state">
              <strong>${escapeHtml(title)}</strong>
              <p class="text-secondary mb-0">${escapeHtml(description)}</p>
            </div>
          </td>
        </tr>
      `;
    }

    function renderList(rows) {
      currentRows = rows;
      document.getElementById('listTitle').textContent = `${dateInput.value} 출석 기록`;
      document.getElementById('attendanceCount').textContent = `${rows.length}건`;
      const body = document.getElementById('attendanceTableBody');

      if (rows.length === 0) {
        body.innerHTML = `
          <tr>
            <td class="text-center py-5" colspan="5">
              <div class="empty-table-state">
                <strong>출석 기록이 없습니다.</strong>
                <p class="text-secondary mb-0">선택한 날짜에 등록된 출석이 없어요.</p>
              </div>
            </td>
          </tr>
        `;
        return;
      }

      body.innerHTML = rows.map((row, index) => `
        <tr>
          <td><span class="text-secondary">${index + 1}</span></td>
          <td>${escapeHtml(row.student_no)}</td>
          <td class="fw-semibold">${escapeHtml(row.name)}</td>
          <td class="text-nowrap">${escapeHtml(formatDateTimeText(row.attend_datetime || row.created_at || row.attend_date))}</td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-2">
              <a class="btn btn-sm btn-outline-success" href="/admin/edit.php?id=${encodeURIComponent(row.id)}">수정</a>
              <button class="btn btn-sm btn-outline-danger" type="button" data-delete-id="${escapeHtml(row.id)}">삭제</button>
            </div>
          </td>
        </tr>
      `).join('');

      body.querySelectorAll('[data-delete-id]').forEach((deleteButton) => {
        deleteButton.addEventListener('click', async () => {
          if (!window.confirm('이 출석 기록을 삭제할까요?')) return;

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
    }

    function exportCsv(rows) {
      if (rows.length === 0) {
        toast('내보낼 출석 기록이 없습니다.', 'error');
        return;
      }

      const csvRows = [
        ['순서', '학번', '이름', '출석일시'],
        ...rows.map((row, index) => [
          index + 1,
          row.student_no,
          row.name,
          formatDateTimeText(row.attend_datetime || row.created_at || row.attend_date),
        ]),
      ];
      const csv = `\ufeff${csvRows.map((row) => row.map(csvEscape).join(',')).join('\r\n')}`;
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');

      link.href = url;
      link.download = `attendance-${dateInput.value}.csv`;
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

    loadList();
  }

  function initEdit() {
    const params = new URLSearchParams(window.location.search);
    const id = Number(params.get('id') || 0);
    const form = document.getElementById('editForm');
    const saveButton = document.getElementById('saveEditButton');

    if (!id) {
      showAlert('올바른 출석 기록을 선택해주세요.');
      form.hidden = true;
      return;
    }

    api('/api/admin-edit.php', { type: 'get', id })
      .then((data) => {
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '출석 기록을 불러오지 못했습니다.');
          form.hidden = true;
          return;
        }

        const record = data.result;
        document.getElementById('editIdInput').value = record.id;
        document.getElementById('editDateInput').value = record.attend_date;
        document.getElementById('studentNoInput').value = record.student_no;
        document.getElementById('nameInput').value = record.name;
        document.getElementById('attendDateTimeText').textContent = formatDateTimeText(record.attend_datetime || record.created_at);
      })
      .catch(() => {
        showAlert('출석 기록을 불러오는 중 오류가 발생했습니다.');
        form.hidden = true;
      });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearAlert();
      saveButton.disabled = true;
      saveButton.textContent = '저장 중...';
      const studentNo = document.getElementById('studentNoInput').value.trim();
      const name = document.getElementById('nameInput').value.trim();

      if (!validateStudentInfo(studentNo, name)) {
        saveButton.disabled = false;
        saveButton.textContent = '저장';
        return;
      }

      try {
        const data = await api('/api/admin-edit.php', {
          type: 'update',
          id: Number(document.getElementById('editIdInput').value),
          student_no: studentNo,
          name,
        });

        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '저장에 실패했습니다.');
          return;
        }

        toast('저장되었습니다.');
        const date = document.getElementById('editDateInput').value;
        window.location.href = `/admin/list.php?date=${encodeURIComponent(date)}`;
      } catch (error) {
        showAlert('저장 중 오류가 발생했습니다.');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = '저장';
      }
    });
  }

  function initPassword() {
    const form = document.getElementById('passwordForm');
    const button = document.getElementById('changePasswordButton');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearAlert();
      button.disabled = true;
      button.textContent = '변경 중...';

      try {
        const data = await api('/api/admin-password.php', {
          old_password: document.getElementById('oldPasswordInput').value,
          new_password: document.getElementById('newPasswordInput').value,
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
  if (path.endsWith('/edit.php')) initEdit();
  if (path.endsWith('/password.php')) initPassword();
})();
