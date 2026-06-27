(function () {
  const REDIRECT_DELAY_MS = 900;
  const installForm = document.getElementById('installForm');
  const installButton = document.getElementById('installButton');
  const installPasswordInput = document.getElementById('installPasswordInput');
  const adminPasswordInput = document.getElementById('adminPasswordInput');
  const adminPasswordConfirmInput = document.getElementById('adminPasswordConfirmInput');
  const { api, initPasswordToggles, inputRange, toast, validateLength } = window.PublicUtils;
  initPasswordToggles(document);

  function redirectHome() {
    setTimeout(() => {
      window.location.href = '/';
    }, REDIRECT_DELAY_MS);
  }

  async function checkInstalled() {
    try {
      const data = await api('/api/status.php', { method: 'GET' });

      if (data.result?.installed) {
        toast('이미 설치되어 있습니다. 출석 페이지로 이동합니다.');
        redirectHome();
      }
    } catch (error) {
      toast('서버 상태를 확인할 수 없습니다.', 'error');
    }
  }

  installForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    let shouldRestoreButton = true;
    const password = adminPasswordInput.value.trim();
    const passwordConfirm = adminPasswordConfirmInput.value.trim();

    if (!installPasswordInput.value.trim()) {
      toast('설치 승인 비밀번호를 입력해주세요.', 'error');
      return;
    }

    if (!validateLength(password, '관리자 비밀번호는', inputRange(adminPasswordInput, 8, 64))) {
      return;
    }

    if (password !== passwordConfirm) {
      toast('새 관리자 비밀번호 확인이 일치하지 않습니다.', 'error');
      return;
    }

    installButton.disabled = true;
    installButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> 설치 중...';

    try {
      const data = await api('/api/install.php', {
        method: 'POST',
        body: JSON.stringify({
          install_password: installPasswordInput.value,
          password,
        }),
      });

      if (data.status !== 1) {
        toast(data.msg || '설치에 실패했습니다.', 'error');
        return;
      }

      toast('설치가 완료되었습니다. 출석 페이지로 이동합니다.');
      shouldRestoreButton = false;
      redirectHome();
    } catch (error) {
      toast('설치 중 오류가 발생했습니다.', 'error');
    } finally {
      if (shouldRestoreButton) {
        installButton.disabled = false;
        installButton.innerHTML = '<i class="bi bi-magic me-1"></i> 설치 시작';
      }
    }
  });

  checkInstalled();
})();
