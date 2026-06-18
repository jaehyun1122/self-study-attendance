(function () {
  const APPROVAL_GUIDE = '출석을 계속하면 관리자 승인 이후 정상 출결로 처리됩니다.';

  function createLocationManager({ els, getStatusInfo, loadStatus }) {
    async function collectLocationPayload() {
      if (!getStatusInfo()) {
        await loadStatus(false);
      }

      const statusInfo = getStatusInfo();

      if (!statusInfo) {
        const action = await openLocationDialog({
          variant: 'warning',
          title: '위치 설정 확인 불가',
          message: '서버의 위치 설정을 확인할 수 없습니다.',
          help: `다시 위치 요청을 눌러주세요.\n${APPROVAL_GUIDE}`,
          retry: true,
          override: true,
        });

        if (action === 'retry') {
          await loadStatus(true);
          return collectLocationPayload();
        }

        return action === 'override' ? unverifiedLocationPayload('settings_unavailable') : null;
      }

      const location = statusInfo?.location || {};

      if (!location.enabled) {
        return {};
      }

      if (!window.isSecureContext) {
        const action = await openLocationDialog({
          variant: 'danger',
          title: 'HTTPS 접속 필요',
          message: '현재 주소에서는 위치 권한을 요청할 수 없습니다.',
          help: `HTTPS 주소에서 다시 접속해주세요.\n${APPROVAL_GUIDE}`,
          retry: false,
          override: true,
        });

        return action === 'override' ? unverifiedLocationPayload('insecure_context') : null;
      }

      if (!navigator.geolocation) {
        const action = await openLocationDialog({
          variant: 'danger',
          title: '위치 기능 미지원',
          message: '이 브라우저에서는 위치 기능을 사용할 수 없습니다.',
          help: `다른 브라우저나 기기에서 다시 시도해주세요.\n${APPROVAL_GUIDE}`,
          retry: false,
          override: true,
        });

        return action === 'override' ? unverifiedLocationPayload('unsupported') : null;
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
            variant: detail.variant,
            title: detail.title,
            message: detail.message,
            help: detail.help,
            retry: !detail.refresh,
            refresh: Boolean(detail.refresh),
            override: true,
          });

          if (action === 'retry') {
            permissionState = await geolocationPermissionState();
            continue;
          }

          if (action === 'refresh') {
            window.location.reload();
            return null;
          }

          return action === 'override' ? unverifiedLocationPayload(detail.pendingReason) : null;
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
          variant: 'danger',
          title: '위치 권한 사용 불가',
          message: deniedMessage,
          help: `${browserLocationHelp()}\n권한을 허용한 뒤 새로고침하고 다시 출석해주세요.\n${APPROVAL_GUIDE}`,
          refresh: true,
          pendingReason: 'permission_denied',
        };
      }

      if (code === 2) {
        return {
          variant: 'warning',
          title: '현재 위치 확인 불가',
          message: '현재 위치를 가져오지 못했습니다.',
          help: `신호가 좋은 곳에서 다시 위치 요청을 눌러주세요.\n${APPROVAL_GUIDE}`,
          pendingReason: 'position_unavailable',
        };
      }

      if (code === 3) {
        return {
          variant: 'warning',
          title: '위치 확인 시간 초과',
          message: '정해진 시간 안에 위치를 가져오지 못했습니다.',
          help: `잠시 후 다시 위치 요청을 눌러주세요.\n${APPROVAL_GUIDE}`,
          pendingReason: 'timeout',
        };
      }

      return {
        variant: 'danger',
        title: '위치 인증 실패',
        message: '현재 위치를 확인하지 못했습니다.',
        help: `다시 위치 요청을 눌러주세요.\n${APPROVAL_GUIDE}`,
        pendingReason: 'unknown',
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

    function unverifiedLocationPayload(reason) {
      return {
        location_unavailable: true,
        location_unavailable_reason: reason,
      };
    }

    function openLocationDialog(options) {
      return new Promise((resolve) => {
        const {
          title,
          message,
          help = '',
          retry = false,
          refresh = false,
          override = false,
          variant = 'info',
        } = options;
        const dialog = els.locationConfirmModal.querySelector('.location-confirm-dialog');

        dialog?.classList.remove('location-dialog-info', 'location-dialog-warning', 'location-dialog-danger');
        dialog?.classList.add(`location-dialog-${variant}`);
        els.locationConfirmTitle.textContent = title;
        els.locationConfirmMessage.textContent = message;
        els.locationHelpBox.textContent = help;
        els.locationHelpBox.hidden = help.trim() === '';
        els.requestLocationAgainButton.hidden = !retry && !refresh;
        els.requestLocationAgainButton.textContent = refresh ? '새로고침' : '다시 위치 요청';
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
        els.requestLocationAgainButton.onclick = () => close(refresh ? 'refresh' : 'retry');
        els.confirmLocationOverrideButton.onclick = () => close('override');
        els.locationConfirmModal.onclick = (event) => {
          if (event.target === els.locationConfirmModal) {
            close('cancel');
          }
        };
      });
    }

    return {
      collectLocationPayload,
      openLocationDialog,
    };
  }

  window.AttendanceLocation = {
    createLocationManager,
  };
})();
