(function () {
  const form = document.getElementById('adminLoginForm');
  const passwordInput = document.getElementById('adminPasswordInput');
  const loginButton = document.getElementById('loginButton');
  const installNotice = document.getElementById('installNotice');

  function toast(message, type = 'success') {
    if (window.Toastify) {
      window.Toastify({
        text: message,
        duration: 2400,
        gravity: 'top',
        position: 'center',
        style: {
          background: type === 'error' ? '#c2410c' : '#10805f',
          borderRadius: '8px',
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

  async function loadStatus() {
    try {
      const data = await api('/api/status.php');
      const status = data.result;

      if (!status?.installed) {
        installNotice.textContent = '아직 설치되지 않았습니다. POST /api/install.php로 초기 관리자 비밀번호를 설정해주세요.';
      }

      if (status?.php && !status.php.ok) {
        installNotice.textContent = `PHP ${status.php.required} 이상이 필요합니다. 현재 버전: ${status.php.current}`;
      }
    } catch (error) {
      installNotice.textContent = '서버 상태를 확인할 수 없습니다.';
    }
  }

  const params = new URLSearchParams(window.location.search);

  if (params.has('logout') || params.has('password') || params.has('login')) {
    localStorage.removeItem('admin_token');
  }

  if (params.get('login') === 'required') {
    toast('로그인이 필요합니다.', 'error');
  }

  if (params.get('login') === 'expired') {
    toast('로그인 시간이 만료되었습니다. 다시 로그인해주세요.', 'error');
  }

  if (params.get('logout') === '1') {
    toast('로그아웃되었습니다.');
  }

  if (params.get('password') === 'changed') {
    toast('비밀번호가 변경되었습니다. 다시 로그인해주세요.');
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const password = passwordInput.value;

    if (!password) {
      toast('비밀번호를 입력해주세요.', 'error');
      return;
    }

    loginButton.disabled = true;
    loginButton.textContent = '로그인 중...';

    try {
      const data = await api('/api/admin-login.php', {
        method: 'POST',
        body: JSON.stringify({ password }),
      });

      if (data.status !== 1) {
        toast(data.msg || '로그인에 실패했습니다.', 'error');
        return;
      }

      localStorage.setItem('admin_token', data.result.token);
      window.location.href = '/admin/dash.php';
    } catch (error) {
      toast('로그인 요청 중 오류가 발생했습니다.', 'error');
    } finally {
      loginButton.disabled = false;
      loginButton.textContent = '로그인';
    }
  });

  loadStatus();
})();
