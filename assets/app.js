(function () {
  const STORAGE_KEY = 'attendance_student';
  const RESYNC_INTERVAL_MS = 60 * 1000;

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

  function validateStudentInfo(studentNo, name) {
    const studentNoLength = Number(els.studentNoInput.getAttribute('maxlength') || 5);
    const studentNameMaxLength = Number(els.studentNameInput.getAttribute('maxlength') || 5);

    if (textLength(studentNo) !== studentNoLength) {
      toast(`학번은 ${studentNoLength}자로 입력해주세요.`, 'error');
      return false;
    }

    if (textLength(name) > studentNameMaxLength) {
      toast(`이름은 ${studentNameMaxLength}자까지 입력할 수 있습니다.`, 'error');
      return false;
    }

    return true;
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

      if (statusInfo && statusInfo.server_time) {
        serverTime = parseServerTime(statusInfo.server_time);
        startServerClock();
      }

      if (data.status !== 1 && showError) {
        toast(data.msg || '상태 확인에 실패했습니다.', 'error');
      }
    } catch (error) {
      if (showError) {
        toast('서버 상태를 확인할 수 없습니다.', 'error');
      }
    }
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

  function startServerClock() {
    updateServerTimeText();

    if (serverTimeTimer) {
      clearInterval(serverTimeTimer);
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
      return;
    }

    els.serverTime.textContent = `현재시간: ${formatDateTime(serverTime)}`;
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
    const className = kind === 'success' ? 'result-card success' : `result-card ${kind}`;
    const resultTime = result?.attend_datetime || result?.attend_time || result?.attend_date || '';

    els.resultCard.className = className;
    els.resultIcon.textContent = kind === 'success' ? '✓' : '!';
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
      ['서비스명', info.app_name],
      ['버전', info.version],
      ['API 상태', info.api_status === 'ok' ? '정상' : '확인 필요'],
      ['설치 여부', info.installed ? '설치됨' : '설치 필요'],
      ['시간대', info.timezone],
      ['서버시간', formatDateTimeText(info.server_time)],
      ['PHP', `${info.php?.current || '-'} / 필요 ${info.php?.required || '8.5.0'}`],
    ].map(([label, value]) => `<div><dt>${label}</dt><dd>${escapeHtml(String(value ?? '-'))}</dd></div>`).join('');
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
    els.attendButton.textContent = '처리 중...';

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
      els.attendButton.textContent = '출석하기';
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

  loadStatus(false);
  setInterval(() => loadStatus(false), RESYNC_INTERVAL_MS);
  render();
})();
