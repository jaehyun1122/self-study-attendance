(function () {
  const LOGIN_REDIRECT_DELAY_MS = 1000;
  const form = document.getElementById('adminLoginForm');
  const passwordInput = document.getElementById('adminPasswordInput');
  const loginButton = document.getElementById('loginButton');
  const installNotice = document.getElementById('installNotice');
  let isRedirecting = false;

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

  document.querySelectorAll('[data-password-toggle]').forEach((toggleButton) => {
    toggleButton.addEventListener('click', () => {
      const input = document.getElementById(toggleButton.dataset.passwordToggle || '');
      const icon = toggleButton.querySelector('i');

      if (!input) {
        return;
      }

      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      toggleButton.setAttribute('aria-label', visible ? '비밀번호 표시' : '비밀번호 숨기기');

      if (icon) {
        icon.className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
      }
    });
  });

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
        installNotice.innerHTML = '아직 설치되지 않았습니다. <a href="/install.php">설치 페이지</a>를 먼저 진행해주세요.';
      }
    } catch (error) {
      installNotice.textContent = '서버 상태를 확인할 수 없습니다.';
    }
  }

  const params = new URLSearchParams(window.location.search);
  const reason = params.get('reason') || '';

  if (reason) {
    localStorage.removeItem('admin_token');
  }

  const reasonMessages = {
    'login-required': ['로그인이 필요합니다.', 'error'],
    'session-expired': ['로그인 시간이 만료되었습니다. 다시 로그인해주세요.', 'error'],
    'logout': ['로그아웃되었습니다.', 'success'],
    'password-change': ['비밀번호가 변경되었습니다. 다시 로그인해주세요.', 'success'],
    'etc': ['알 수 없는 이유로 로그아웃되었습니다. 다시 로그인해주세요.', 'error'],
  };
  const legacyReasonAliases = {
    required: 'login-required',
    expired: 'session-expired',
    password: 'password-change',
    changed: 'password-change',
  };
  const normalizedReason = legacyReasonAliases[reason] || reason;

  if (normalizedReason && reasonMessages[normalizedReason]) {
    const [message, type] = reasonMessages[normalizedReason];
    toast(message, type);
  } else if (reason) {
    toast(`다시 로그인해주세요. (${reason})`, reasonMessages.etc[1]);
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (loginButton.disabled) {
      return;
    }

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
      toast('로그인되었습니다.');
      loginButton.textContent = '이동 중...';
      isRedirecting = true;
      setTimeout(() => {
        window.location.href = '/admin/dash.php';
      }, LOGIN_REDIRECT_DELAY_MS);
    } catch (error) {
      toast('로그인 요청 중 오류가 발생했습니다.', 'error');
    } finally {
      if (!isRedirecting) {
        loginButton.disabled = false;
        loginButton.textContent = '로그인';
      }
    }
  });

  loadStatus();
})();
