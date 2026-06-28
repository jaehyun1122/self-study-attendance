(function () {
  const THEME_STORAGE_KEY = 'attendance_color_theme';
  const THEME_ORDER = ['system', 'light', 'dark'];
  const THEME_MEDIA_QUERY = window.matchMedia?.('(prefers-color-scheme: dark)');
  const THEME_META = {
    system: { label: '시스템', icon: 'bi bi-circle-half' },
    light: { label: '밝게', icon: 'bi bi-sun' },
    dark: { label: '다크', icon: 'bi bi-moon-stars' },
  };

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
    if (!root) return;

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

  function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach((toggleButton) => {
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
  }

  function applyTheme(theme) {
    const normalizedTheme = THEME_ORDER.includes(theme) ? theme : 'system';
    const resolvedTheme = normalizedTheme === 'system'
      ? (THEME_MEDIA_QUERY?.matches ? 'dark' : 'light')
      : normalizedTheme;
    const nextTheme = THEME_ORDER[(THEME_ORDER.indexOf(normalizedTheme) + 1) % THEME_ORDER.length];

    document.documentElement.dataset.theme = resolvedTheme;
    document.documentElement.dataset.themePreference = normalizedTheme;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      const icon = button.querySelector('i');
      const label = `현재 테마: ${THEME_META[normalizedTheme].label}. ${THEME_META[nextTheme].label} 모드로 전환`;

      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
      if (icon) {
        icon.className = THEME_META[normalizedTheme].icon;
      }
    });

    document.dispatchEvent(new CustomEvent('attendance:theme-change', {
      detail: { preference: normalizedTheme, resolved: resolvedTheme },
    }));
  }

  function initThemeToggles(root = document) {
    let savedTheme = '';

    try {
      savedTheme = localStorage.getItem(THEME_STORAGE_KEY) || '';
    } catch (error) {
      savedTheme = '';
    }

    const preferredTheme = THEME_ORDER.includes(savedTheme) ? savedTheme : 'system';
    applyTheme(preferredTheme);

    root.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const currentTheme = document.documentElement.dataset.themePreference || 'system';
        const nextTheme = THEME_ORDER[(THEME_ORDER.indexOf(currentTheme) + 1) % THEME_ORDER.length];

        try {
          localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        } catch (error) {
          // 저장할 수 없는 환경에서도 현재 페이지의 테마 변경은 유지합니다.
        }

        applyTheme(nextTheme);
      });
    });

    const syncSystemTheme = () => {
      if (document.documentElement.dataset.themePreference === 'system') {
        applyTheme('system');
      }
    };

    if (THEME_MEDIA_QUERY?.addEventListener) {
      THEME_MEDIA_QUERY.addEventListener('change', syncSystemTheme);
    } else {
      THEME_MEDIA_QUERY?.addListener(syncSystemTheme);
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

  function toDateTimeLocal(value) {
    const text = formatDateTimeText(value);
    return text ? text.replace(' ', 'T') : '';
  }

  function fromDateTimeLocal(value) {
    const text = String(value || '').trim().replace('T', ' ');
    return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(text) ? `${text}:00` : text;
  }

  function nullableNumber(value) {
    const text = String(value ?? '').trim();
    const number = Number(text);
    return text === '' || !Number.isFinite(number) ? null : number;
  }

  function valueOrDash(value, suffix = '') {
    if (value === null || value === undefined || value === '') {
      return '-';
    }

    return `${value}${suffix}`;
  }

  function meterText(value) {
    return value === null || value === undefined || value === '' ? '-' : `${Number(value).toFixed(1)}m`;
  }

  function formatUptimeSeconds(totalSeconds) {
    let seconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    const days = Math.floor(seconds / 86400);
    seconds %= 86400;
    const hours = Math.floor(seconds / 3600);
    seconds %= 3600;
    const minutes = Math.floor(seconds / 60);
    seconds %= 60;

    const parts = [];

    if (days > 0) parts.push(`${days}일`);
    if (hours > 0) parts.push(`${hours}시간`);
    if (minutes > 0) parts.push(`${minutes}분`);
    if (seconds > 0 || parts.length === 0) parts.push(`${seconds}초`);

    return parts.join(' ');
  }

  window.PublicUtils = {
    api,
    formatDateTime,
    formatDateTimeText,
    formatUptimeSeconds,
    fromDateTimeLocal,
    initPasswordToggles,
    inputRange,
    meterText,
    nullableNumber,
    parseServerTime,
    toast,
    toDateTimeLocal,
    valueOrDash,
    validateLength,
  };

  initThemeToggles(document);
})();
