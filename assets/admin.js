(function () {
  const TOKEN_KEY = 'admin_token';
  const token = window.ADMIN_TOKEN || localStorage.getItem(TOKEN_KEY);
  const path = window.location.pathname;

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

  function redirectLogin(reason = '') {
    localStorage.removeItem(TOKEN_KEY);
    const query = reason ? `?${reason}` : '';
    window.location.href = `/admin/${query}`;
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
      redirectLogin('login=expired');
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

    if (Number.isNaN(date.getTime())) {
      return text || '-';
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

  function textLength(value) {
    return Array.from(String(value || '')).length;
  }

  function validateStudentInfo(studentNo, name) {
    const studentNoInput = document.getElementById('studentNoInput');
    const nameInput = document.getElementById('nameInput');
    const studentNoLength = Number(studentNoInput?.getAttribute('maxlength') || 5);
    const studentNameMaxLength = Number(nameInput?.getAttribute('maxlength') || 5);

    if (textLength(studentNo) !== studentNoLength) {
      showAlert(`학번은 ${studentNoLength}자로 입력해주세요.`);
      return false;
    }

    if (textLength(name) > studentNameMaxLength) {
      showAlert(`이름은 ${studentNameMaxLength}자까지 입력할 수 있습니다.`);
      return false;
    }

    return true;
  }

  function initDash() {
    const todayCount = document.getElementById('todayCount');
    const totalCount = document.getElementById('totalCount');
    const serverTime = document.getElementById('summaryServerTime');

    api('/api/admin-summary.php')
      .then((data) => {
        if (!data) return;
        if (data.status !== 1) {
          toast(data.msg || '대시보드 정보를 불러오지 못했습니다.', 'error');
          return;
        }

        const result = data.result || {};
        todayCount.textContent = `${Number(result.today || 0)}건`;
        totalCount.textContent = `${Number(result.total || 0)}건`;
        serverTime.textContent = formatDateTimeText(result.server_time);
      })
      .catch(() => toast('대시보드 정보를 불러오는 중 오류가 발생했습니다.', 'error'));
  }

  function initList() {
    const params = new URLSearchParams(window.location.search);
    const dateInput = document.getElementById('dateInput');
    const form = document.getElementById('attendanceFilter');
    const button = document.getElementById('loadListButton');

    dateInput.value = params.get('date') || todayForInput();

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const url = new URL(window.location.href);
      url.searchParams.set('date', dateInput.value);
      window.history.replaceState(null, '', url);
      loadList();
    });

    async function loadList() {
      clearAlert();
      button.disabled = true;
      button.textContent = '조회 중...';

      try {
        const data = await api('/api/admin-list.php', { date: dateInput.value });
        if (!data) return;

        if (data.status !== 1) {
          showAlert(data.msg || '출석 목록을 불러오지 못했습니다.');
          return;
        }

        renderList(Array.isArray(data.result) ? data.result : []);
      } catch (error) {
        showAlert('출석 목록을 불러오는 중 오류가 발생했습니다.');
      } finally {
        button.disabled = false;
        button.textContent = '조회';
      }
    }

    function renderList(rows) {
      document.getElementById('listTitle').textContent = `${dateInput.value} 출석 기록`;
      document.getElementById('attendanceCount').textContent = `${rows.length}건`;
      const body = document.getElementById('attendanceTableBody');

      if (rows.length === 0) {
        body.innerHTML = `
          <tr>
            <td class="text-center py-5" colspan="5">
              <div class="empty-table-state">
                <div class="empty-table-icon">0</div>
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
          <td><span class="badge rounded-pill text-bg-light border">${escapeHtml(row.student_no)}</span></td>
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
        document.getElementById('backToListLink').href = `/admin/list.php?date=${encodeURIComponent(record.attend_date || '')}`;
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
        setTimeout(() => redirectLogin('password=changed'), 800);
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
        redirectLogin('logout=1');
      } catch (error) {
        redirectLogin('logout=1');
      }
    });
  });

  if (path.endsWith('/dash.php')) initDash();
  if (path.endsWith('/list.php')) initList();
  if (path.endsWith('/edit.php')) initEdit();
  if (path.endsWith('/password.php')) initPassword();
})();
