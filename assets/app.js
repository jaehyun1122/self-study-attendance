(function () {
  const STORAGE_KEY = 'attendance_student';
  const DEFAULT_SYNC_INTERVAL_MS = 5000;

  const els = {
    studentFormView: document.getElementById('studentFormView'),
    attendanceView: document.getElementById('attendanceView'),
    resultView: document.getElementById('resultView'),
    studentForm: document.getElementById('studentForm'),
    studentNoInput: document.getElementById('studentNoInput'),
    studentNameInput: document.getElementById('studentNameInput'),
    studentText: document.getElementById('studentText'),
    attendButton: document.getElementById('attendButton'),
    serverTime: document.getElementById('serverTime'),
    changeStudentButton: document.getElementById('changeStudentButton'),
    resultCard: document.getElementById('resultCard'),
    resultIcon: document.getElementById('resultIcon'),
    resultTitle: document.getElementById('resultTitle'),
    resultStudent: document.getElementById('resultStudent'),
    resultTime: document.getElementById('resultTime'),
    resultMessage: document.getElementById('resultMessage'),
    backButton: document.getElementById('backButton'),
    infoButton: document.getElementById('infoButton'),
    infoModal: document.getElementById('infoModal'),
    closeInfoButton: document.getElementById('closeInfoButton'),
    infoList: document.getElementById('infoList'),
  };

  let serverTime = null;
  let serverTimeTimer = null;
  let statusInfo = null;
  let statusSyncTimer = null;
  let statusSyncIntervalMs = DEFAULT_SYNC_INTERVAL_MS;

  function toast(message, type = 'success') {
    if (window.Toastify) {
      window.Toastify({
        text: message,
        duration: 2400,
        gravity: 'top',
        position: 'center',
        stopOnFocus: true,
        style: {
          background: type === 'error' ? '#c2410c' : '#10805f',
          borderRadius: '8px',
          boxShadow: '0 14px 40px rgba(21, 80, 56, 0.18)',
        },
      }).showToast();
      return;
    }

    const root = document.getElementById('toastRoot');
    const item = document.createElement('div');
    item.className = `fallback-toast ${type}`;
    item.textContent = message;
    root.appendChild(item);
    setTimeout(() => item.remove(), 2400);
  }

  function getStudentInfo() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      return saved ? JSON.parse(saved) : null;
    } catch (error) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
  }

  function saveStudentInfo(studentNo, name) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      student_no: studentNo.trim(),
      name: name.trim(),
    }));
  }

  function clearStudentInfo() {
    localStorage.removeItem(STORAGE_KEY);
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
      toast(lengthMessage(subject, range), 'error');
      return false;
    }

    return true;
  }

  function validateStudentInfo(studentNo, name) {
    return validateLength(studentNo, '학번은', inputRange(els.studentNoInput, 5, 5))
      && validateLength(name, '이름은', inputRange(els.studentNameInput, 1, 5));
  }

  function fillStudentForm(student) {
    els.studentNoInput.value = student?.student_no || '';
    els.studentNameInput.value = student?.name || '';
  }

  function showOnly(view) {
    [els.studentFormView, els.attendanceView, els.resultView].forEach((item) => {
      item.hidden = item !== view;
    });
  }

  function render() {
    const student = getStudentInfo();

    if (!student) {
      showOnly(els.studentFormView);
      return;
    }

    els.studentText.textContent = `${student.student_no} ${student.name}`;
    showOnly(els.attendanceView);
  }

  async function api(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
      },
    });

    const data = await response.json().catch(() => null);

    if (!data) {
      throw new Error('서버 응답을 읽을 수 없습니다.');
    }

    return data;
  }

  async function loadStatus(showError = false) {
    try {
      const data = await api('/api/status.php', { method: 'GET' });
      statusInfo = data.result || null;

      if (statusInfo?.server_time) {
        syncServerTime(statusInfo.server_time);
      }

      scheduleStatusSync(statusInfo);

      if (isInfoModalOpen()) {
        renderInfo();
      }

      if (data.status !== 1 && showError) {
        toast(data.msg || '상태 확인에 실패했습니다.', 'error');
      }
    } catch (error) {
      scheduleStatusSync();

      if (showError) {
        toast('서버 상태를 확인할 수 없습니다.', 'error');
      }
    }
  }

  function syncIntervalMs(info = null) {
    const seconds = Number(info?.server_time_sync_interval_seconds || 5);
    return Math.max(1, seconds) * 1000;
  }

  function scheduleStatusSync(info = null) {
    const nextIntervalMs = syncIntervalMs(info);

    if (statusSyncTimer && nextIntervalMs === statusSyncIntervalMs) {
      return;
    }

    statusSyncIntervalMs = nextIntervalMs;

    if (statusSyncTimer) {
      clearInterval(statusSyncTimer);
    }

    statusSyncTimer = setInterval(() => loadStatus(false), statusSyncIntervalMs);
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

  function syncServerTime(value) {
    serverTime = parseServerTime(value);
    updateServerTimeText();
    startServerClock();
  }

  function startServerClock() {
    if (serverTimeTimer) {
      return;
    }

    serverTimeTimer = setInterval(() => {
      if (!serverTime) {
        return;
      }

      serverTime.setSeconds(serverTime.getSeconds() + 1);
      updateServerTimeText();
    }, 1000);
  }

  function updateServerTimeText() {
    if (!serverTime) {
      els.serverTime.textContent = '현재시간: 불러오는 중...';
      updateInfoServerTimeText();
      return;
    }

    els.serverTime.textContent = `현재시간: ${formatDateTime(serverTime)}`;
    updateInfoServerTimeText();
  }

  function currentServerTimeText() {
    if (serverTime) {
      return formatDateTime(serverTime);
    }

    return formatDateTimeText(statusInfo?.server_time || '') || '-';
  }

  function updateInfoServerTimeText() {
    const infoServerTime = els.infoList.querySelector('[data-info-server-time]');

    if (infoServerTime) {
      infoServerTime.textContent = currentServerTimeText();
    }
  }

  function formatDateTime(date) {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    const hh = String(date.getHours()).padStart(2, '0');
    const mi = String(date.getMinutes()).padStart(2, '0');
    const ss = String(date.getSeconds()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
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
    return Number.isNaN(date.getTime()) ? text : formatDateTime(date);
  }

  function showResult(kind, title, message, result) {
    const student = getStudentInfo();
    const resultTime = result?.attend_datetime || result?.attend_time || result?.attend_date || '';
    const icons = {
      success: '✓',
      duplicate: '!',
      error: '!',
    };

    els.resultCard.className = `result-card ${kind}`;
    els.resultIcon.textContent = icons[kind] || '!';
    els.resultTitle.textContent = title;
    els.resultStudent.textContent = student ? `${student.student_no} ${student.name}` : '';
    els.resultTime.textContent = formatDateTimeText(resultTime);
    els.resultMessage.textContent = message;
    showOnly(els.resultView);
  }

  function openInfoModal() {
    const info = statusInfo;

    if (!info) {
      els.infoList.innerHTML = '<div><dt>상태</dt><dd>정보를 불러오는 중입니다.</dd></div>';
      loadStatus(true).then(renderInfo);
    } else {
      renderInfo();
    }

    if (window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(els.infoModal).show();
      return;
    }

    els.infoModal.classList.add('show');
    els.infoModal.style.display = 'block';
    els.infoModal.removeAttribute('aria-hidden');
  }

  function hideInfoModal() {
    els.infoModal.classList.remove('show');
    els.infoModal.style.display = 'none';
    els.infoModal.setAttribute('aria-hidden', 'true');
  }

  function renderInfo() {
    const info = statusInfo;

    if (!info) {
      return;
    }

    els.infoList.innerHTML = [
      { label: '서비스명', value: info.app_name },
      { label: '버전', value: info.version },
      { label: 'GitHub', value: repositoryLink(info.repository_url, info.powered_by) },
      { label: 'API 상태', value: info.api_status === 'ok' ? '정상' : '확인 필요' },
      { label: '설치 여부', value: info.installed ? '설치됨' : '설치 필요' },
      { label: '시간대', value: info.timezone },
      { label: '서버시간', value: currentServerTimeText(), attr: 'data-info-server-time' },
      { label: '보정 주기', value: `${Number(info.server_time_sync_interval_seconds || 5)}초` },
      { label: 'PHP', value: `${info.php?.current || '-'} / 필요 ${info.php?.required || '8.5.0'}` },
    ].map(({ label, value, attr = '' }) => {
      const html = value instanceof SafeHtml ? value.html : escapeHtml(String(value ?? '-'));
      return `<div><dt>${escapeHtml(label)}</dt><dd${attr ? ` ${attr}` : ''}>${html}</dd></div>`;
    }).join('');

    updateInfoServerTimeText();
  }

  function isInfoModalOpen() {
    return els.infoModal.classList.contains('show') || els.infoModal.style.display === 'block';
  }

  class SafeHtml {
    constructor(html) {
      this.html = html;
    }
  }

  function repositoryLink(value, labelValue = '') {
    const url = String(value || '').trim();
    const label = String(labelValue || 'jaehyun1122 / self-study-attendance').trim();

    if (!/^https:\/\/github\.com\/[-A-Za-z0-9_.]+\/[-A-Za-z0-9_.]+\/?$/.test(url)) {
      return '-';
    }

    return new SafeHtml(`<a class="info-link" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`);
  }

  function escapeHtml(value) {
    return value.replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    })[char]);
  }

  els.studentForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const studentNo = els.studentNoInput.value.trim();
    const name = els.studentNameInput.value.trim();

    if (!studentNo || !name) {
      toast('학번과 이름을 입력해주세요.', 'error');
      return;
    }

    if (!validateStudentInfo(studentNo, name)) {
      return;
    }

    saveStudentInfo(studentNo, name);
    fillStudentForm({ student_no: studentNo, name });
    toast('학생 정보가 저장되었습니다.');
    render();
  });

  els.changeStudentButton.addEventListener('click', () => {
    const student = getStudentInfo();
    fillStudentForm(student);
    clearStudentInfo();
    toast('학생 정보를 다시 입력해주세요.');
    showOnly(els.studentFormView);
    els.studentNoInput.focus();
  });

  els.attendButton.addEventListener('click', async () => {
    const student = getStudentInfo();

    if (!student) {
      render();
      return;
    }

    els.attendButton.disabled = true;
    els.attendButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> 처리 중...';

    try {
      const data = await api('/api/attend.php', {
        method: 'POST',
        body: JSON.stringify(student),
      });

      if (data.status === 1) {
        showResult('success', '출석 완료', '정상적으로 출석 처리되었습니다.', data.result);
        return;
      }

      const title = data.msg && data.msg.includes('이미') ? '이미 출석됨' : '출석 실패';
      const kind = title === '이미 출석됨' ? 'duplicate' : 'error';
      showResult(kind, title, data.msg || '잠시 후 다시 시도해주세요.', data.result);
    } catch (error) {
      showResult('error', '출석 실패', '잠시 후 다시 시도해주세요.', null);
      toast('네트워크 오류가 발생했습니다.', 'error');
    } finally {
      els.attendButton.disabled = false;
      els.attendButton.innerHTML = '<i class="bi bi-check2-circle me-1"></i> 출석하기';
    }
  });

  els.backButton.addEventListener('click', render);
  els.infoButton.addEventListener('click', openInfoModal);
  els.closeInfoButton.addEventListener('click', () => {
    if (window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(els.infoModal).hide();
      return;
    }

    hideInfoModal();
  });
  els.infoModal.addEventListener('click', (event) => {
    if (!window.bootstrap && event.target === els.infoModal) {
      hideInfoModal();
    }
  });

  scheduleStatusSync();
  loadStatus(false);
  render();
})();
