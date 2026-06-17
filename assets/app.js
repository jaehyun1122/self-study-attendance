(function () {
  const STORAGE_KEY = 'attendance_student';
  const DEFAULT_SYNC_INTERVAL_MS = 5000;
  const {
    api,
    formatDateTime,
    formatDateTimeText,
    initPasswordToggles,
    inputRange,
    parseServerTime,
    toast,
    validateLength,
  } = window.PublicUtils;

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
  const locationManager = window.AttendanceLocation.createLocationManager({
    els,
    getStatusInfo: () => statusInfo,
    loadStatus,
  });

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
    if (statusInfo?.installed === false) {
      window.location.href = '/install.php';
      return;
    }

    const student = getStudentInfo();

    if (!student || isEditingStudent) {
      showOnly(els.studentFormView);
      return;
    }

    els.studentText.textContent = `${student.student_no} ${student.name}`;
    showOnly(els.attendanceView);
  }

  async function loadStatus(showError = false) {
    try {
      const data = await api('/api/status.php', { method: 'GET' });
      statusInfo = data.result || null;

      if (statusInfo?.installed === false) {
        window.location.href = '/install.php';
        return;
      }

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
      let locationPayload = await locationManager.collectLocationPayload();

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
        const action = await locationManager.openLocationDialog({
          title: '교내 범위 밖',
          message: `현재 위치가 출석 가능 반경을 벗어났습니다. 거리 ${distance}m / 허용 반경 ${radius}m`,
          help: '위치가 부정확할 수 있으면 다시 위치 요청을 눌러주세요. 실제로 교내에 있다면 관리자 승인 요청으로 출석을 기록할 수 있습니다.',
          retry: true,
          override: true,
        });

        if (action === 'retry') {
          locationPayload = await locationManager.collectLocationPayload();

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

  initPasswordToggles(document);
  scheduleStatusSync();
  loadStatus(false);
  render();
})();
