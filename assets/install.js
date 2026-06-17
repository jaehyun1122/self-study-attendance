(function () {
  const REDIRECT_DELAY_MS = 900;
  const installButton = document.getElementById('installButton');
  const { api, toast } = window.PublicUtils;

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

  installButton?.addEventListener('click', async () => {
    let shouldRestoreButton = true;

    installButton.disabled = true;
    installButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> 설치 중...';

    try {
      const data = await api('/api/install.php', {
        method: 'POST',
        body: JSON.stringify({}),
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
