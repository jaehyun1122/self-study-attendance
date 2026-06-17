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
    studentChip: document.getElementById('studentChip'),
    studentEditModal: document.getElementById('studentEditModal'),
    studentEditVerifyForm: document.getElementById('studentEditVerifyForm'),
    studentEditPasswordInput: document.getElementById('studentEditPasswordInput'),
    cancelStudentEditButton: document.getElementById('cancelStudentEditButton'),
    verifyStudentEditButton: document.getElementById('verifyStudentEditButton'),
    locationConfirmModal: document.getElementById('locationConfirmModal'),
    locationConfirmTitle: document.getElementById('locationConfirmTitle'),
    locationConfirmMessage: document.getElementById('locationConfirmMessage'),
    locationHelpBox: document.getElementById('locationHelpBox'),
    cancelLocationConfirmButton: document.getElementById('cancelLocationConfirmButton'),
    requestLocationAgainButton: document.getElementById('requestLocationAgainButton'),
    confirmLocationOverrideButton: document.getElementById('confirmLocationOverrideButton'),
    studentText: document.getElementById('studentText'),
    attendButton: document.getElementById('attendButton'),
    serverTime: document.getElementById('serverTime'),
    resultCard: document.getElementById('resultCard'),
    resultIcon: document.getElementById('resultIcon'),
    resultTitle: document.getElementById('resultTitle'),
    resultStudent: document.getElementById('resultStudent'),
    resultTime: document.getElementById('resultTime'),
    resultMessage: document.getElementById('resultMessage'),
    backButton: document.getElementById('backButton'),
  };

  let serverTime = null;
  let serverTimeTimer = null;
  let statusInfo = null;
  let statusSyncTimer = null;
  let statusSyncIntervalMs = DEFAULT_SYNC_INTERVAL_MS;
  let studentEditTapCount = 0;
  let studentEditTapTimer = null;
  let isEditingStudent = false;

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
    const text = String(value || '');

    if (window.Intl?.Segmenter) {
      return Array.from(new Intl.Segmenter('ko', { granularity: 'grapheme' }).segment(text)).length;
    }

    return Array.from(text).length;
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
    const studentNoText = String(studentNo || '').trim();
    const range = inputRange(els.studentNoInput, 5, 5);

    if (!/^\d+$/.test(studentNoText)) {
      toast('학번은 숫자만 입력해주세요.', 'error');
      return false;
    }

    if (!validateLength(studentNoText, '학번은', range)) {
      return false;
    }

    return validateLength(name, '이름은', inputRange(els.studentNameInput, 1, 10));
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

    if (!student || isEditingStudent) {
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

  async function collectLocationPayload() {
    if (!statusInfo) {
      await loadStatus(false);
    }

    if (!statusInfo) {
      const action = await openLocationDialog({
        title: '위치 설정 확인 불가',
        message: '서버의 위치 설정을 확인할 수 없습니다. 잠시 후 다시 시도해주세요.',
        help: '네트워크 상태를 확인한 뒤 다시 위치 요청을 눌러주세요.',
        retry: true,
        override: true,
      });

      if (action === 'retry') {
        await loadStatus(true);
        return collectLocationPayload();
      }

      return action === 'override' ? unverifiedLocationPayload('위치 설정을 확인할 수 없어 관리자 승인 대기 상태입니다.') : null;
    }

    const location = statusInfo?.location || {};

    if (!location.enabled) {
      return {};
    }

    if (!window.isSecureContext) {
      const action = await openLocationDialog({
        title: 'HTTPS 접속 필요',
        message: '현재 접속 환경에서는 브라우저 위치 권한을 요청할 수 없습니다.',
        help: '위치 인증은 HTTPS 또는 localhost 같은 안전한 주소에서만 동작합니다. HTTPS 주소로 다시 접속해주세요.',
        retry: false,
        override: true,
      });

      return action === 'override' ? unverifiedLocationPayload('HTTPS가 아니어서 위치 권한을 요청할 수 없습니다.') : null;
    }

    if (!navigator.geolocation) {
      const action = await openLocationDialog({
        title: '위치 기능 미지원',
        message: '이 브라우저에서는 위치 기능을 사용할 수 없습니다.',
        help: browserLocationHelp(),
        retry: false,
        override: true,
      });

      return action === 'override' ? unverifiedLocationPayload('이 브라우저에서 위치 기능을 지원하지 않습니다.') : null;
    }

    let permissionState = await geolocationPermissionState();

    while (true) {
      try {
        const position = await getBestCurrentPosition(Number(location.timeout_seconds || 10));
        return {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
        };
      } catch (error) {
        const detail = locationErrorDetail(error, permissionState);
        const action = await openLocationDialog({
          title: detail.title,
          message: detail.message,
          help: detail.help,
          retry: true,
          override: true,
        });

        if (action === 'retry') {
          permissionState = await geolocationPermissionState();
          continue;
        }

        return action === 'override' ? unverifiedLocationPayload(detail.pendingMessage) : null;
      }
    }
  }

  async function getBestCurrentPosition(timeoutSeconds) {
    try {
      return await getCurrentPosition({
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: Math.max(3, timeoutSeconds) * 1000,
      });
    } catch (error) {
      if (error?.code !== 2 && error?.code !== 3) {
        throw error;
      }

      return getCurrentPosition({
        enableHighAccuracy: false,
        maximumAge: 0,
        timeout: Math.max(5, timeoutSeconds + 5) * 1000,
      });
    }
  }

  function getCurrentPosition(options) {
    return new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
  }

  async function geolocationPermissionState() {
    if (!navigator.permissions?.query) {
      return null;
    }

    try {
      const permission = await navigator.permissions.query({ name: 'geolocation' });
      return permission.state || null;
    } catch (error) {
      return null;
    }
  }

  function locationErrorDetail(error, permissionState = null) {
    const code = Number(error?.code || 0);

    if (code === 1) {
      const deniedMessage = permissionState === 'denied'
        ? '이 사이트의 위치 권한이 이미 차단되어 현재 위치를 가져오지 못했습니다.'
        : '브라우저 또는 기기 설정에서 위치 권한이 거절되어 현재 위치를 가져오지 못했습니다.';

      return {
        title: '위치 권한 사용 불가',
        message: deniedMessage,
        help: browserLocationHelp(),
        pendingMessage: '위치 권한을 사용할 수 없어 관리자 승인 대기 상태입니다.',
      };
    }

    if (code === 2) {
      return {
        title: '현재 위치 확인 불가',
        message: '기기에서 현재 위치를 계산하지 못했습니다.',
        help: 'Wi-Fi 또는 모바일 데이터를 켜고, 실내라면 창가나 신호가 잘 잡히는 곳에서 다시 시도해주세요.',
        pendingMessage: '현재 위치를 확인할 수 없어 관리자 승인 대기 상태입니다.',
      };
    }

    if (code === 3) {
      return {
        title: '위치 확인 시간 초과',
        message: '정해진 시간 안에 현재 위치를 가져오지 못했습니다.',
        help: '잠시 기다린 뒤 다시 위치 요청을 눌러주세요. iPhone에서는 정확한 위치 허용이 꺼져 있으면 시간이 오래 걸릴 수 있습니다.',
        pendingMessage: '위치 확인 시간이 초과되어 관리자 승인 대기 상태입니다.',
      };
    }

    return {
      title: '위치 인증 실패',
      message: '현재 위치를 확인하지 못했습니다.',
      help: browserLocationHelp(),
      pendingMessage: '위치 인증을 완료할 수 없어 관리자 승인 대기 상태입니다.',
    };
  }

  function browserLocationHelp() {
    const isAppleMobile = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isSafari = /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(navigator.userAgent);

    if (isAppleMobile) {
      return 'iPhone 설정 > 개인정보 보호 및 보안 > 위치 서비스가 켜져 있는지 확인해주세요. Safari를 사용 중이면 해당 사이트의 위치 권한을 허용하고, 정확한 위치도 켜주세요.';
    }

    if (isSafari) {
      return 'Safari 설정 > 웹사이트 > 위치에서 이 사이트가 허용 또는 묻기로 되어 있는지 확인해주세요.';
    }

    return '브라우저 주소창의 사이트 설정에서 위치 권한을 허용한 뒤 다시 시도해주세요.';
  }

  function unverifiedLocationPayload(message) {
    return {
      location_unavailable: true,
      location_message: message,
      location_override_confirmed: true,
    };
  }

  function openLocationDialog(options) {
    return new Promise((resolve) => {
      const {
        title,
        message,
        help = '',
        retry = false,
        override = false,
      } = options;

      els.locationConfirmTitle.textContent = title;
      els.locationConfirmMessage.textContent = message;
      els.locationHelpBox.textContent = help;
      els.locationHelpBox.hidden = help.trim() === '';
      els.requestLocationAgainButton.hidden = !retry;
      els.confirmLocationOverrideButton.hidden = !override;
      els.locationConfirmModal.hidden = false;

      const close = (result) => {
        els.locationConfirmModal.hidden = true;
        els.cancelLocationConfirmButton.onclick = null;
        els.requestLocationAgainButton.onclick = null;
        els.confirmLocationOverrideButton.onclick = null;
        els.locationConfirmModal.onclick = null;
        resolve(result);
      };

      els.cancelLocationConfirmButton.onclick = () => close('cancel');
      els.requestLocationAgainButton.onclick = () => close('retry');
      els.confirmLocationOverrideButton.onclick = () => close('override');
      els.locationConfirmModal.onclick = (event) => {
        if (event.target === els.locationConfirmModal) {
          close('cancel');
        }
      };
    });
  }

  function unlockStudentEdit() {
    openStudentEditModal();
  }

  function openStudentEditModal() {
    const student = getStudentInfo();

    if (!student) {
      return;
    }

    els.studentEditPasswordInput.value = '';
    els.studentEditModal.hidden = false;
    els.studentEditPasswordInput.focus();
  }

  function closeStudentEditModal() {
    els.studentEditModal.hidden = true;
  }

  function handleStudentChipTap() {
    studentEditTapCount += 1;

    if (studentEditTapTimer) {
      clearTimeout(studentEditTapTimer);
    }

    if (studentEditTapCount >= 5) {
      studentEditTapCount = 0;
      unlockStudentEdit();
      return;
    }

    studentEditTapTimer = setTimeout(() => {
      studentEditTapCount = 0;
    }, 2500);
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
    isEditingStudent = false;
    toast('학생 정보가 저장되었습니다.');
    render();
  });

  els.studentEditVerifyForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    els.verifyStudentEditButton.disabled = true;
    els.verifyStudentEditButton.textContent = '확인 중...';

    try {
      const data = await api('/api/admin-verify.php', {
        method: 'POST',
        body: JSON.stringify({ password: els.studentEditPasswordInput.value }),
      });

      if (data.status !== 1) {
        toast(data.msg || '관리자 비밀번호가 올바르지 않습니다.', 'error');
        return;
      }

      const student = getStudentInfo();
      fillStudentForm(student);
      isEditingStudent = true;
      closeStudentEditModal();
      toast('학생 정보를 다시 입력해주세요.');
      render();
      els.studentNoInput.focus();
    } catch (error) {
      toast('관리자 비밀번호 확인 중 오류가 발생했습니다.', 'error');
    } finally {
      els.verifyStudentEditButton.disabled = false;
      els.verifyStudentEditButton.textContent = '확인';
    }
  });

  els.cancelStudentEditButton.addEventListener('click', closeStudentEditModal);
  els.studentEditModal.addEventListener('click', (event) => {
    if (event.target === els.studentEditModal) {
      closeStudentEditModal();
    }
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
      let locationPayload = await collectLocationPayload();

      if (locationPayload === null) {
        return;
      }

      let data = null;

      while (true) {
        const requestBody = {
          ...student,
          ...locationPayload,
        };

        data = await api('/api/attend.php', {
          method: 'POST',
          body: JSON.stringify(requestBody),
        });

        if (!(data.status !== 1 && data.result?.requires_location_confirmation)) {
          break;
        }

        const distance = Number(data.result?.distance_meters || 0).toFixed(1);
        const radius = Number(data.result?.radius_meters || 0).toFixed(0);
        const action = await openLocationDialog({
          title: '교내 범위 밖',
          message: `현재 위치가 출석 가능 반경을 벗어났습니다. 거리 ${distance}m / 허용 반경 ${radius}m`,
          help: '위치가 부정확할 수 있으면 다시 위치 요청을 눌러주세요. 실제로 교내에 있다면 관리자 승인 요청으로 출석을 기록할 수 있습니다.',
          retry: true,
          override: true,
        });

        if (action === 'retry') {
          locationPayload = await collectLocationPayload();

          if (locationPayload === null) {
            return;
          }

          continue;
        }

        if (action !== 'override') {
          return;
        }

        data = await api('/api/attend.php', {
          method: 'POST',
          body: JSON.stringify({
            ...requestBody,
            location_override_confirmed: true,
          }),
        });
        break;
      }

      if (data.status === 1) {
        showResult('success', '출석 완료', data.msg || '정상적으로 출석 처리되었습니다.', data.result);
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
  els.studentChip.addEventListener('click', handleStudentChipTap);

  scheduleStatusSync();
  loadStatus(false);
  render();
})();
