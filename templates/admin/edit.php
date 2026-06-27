<div class="admin-page-header">
  <div>
    <p class="section-kicker">Edit Attendance</p>
    <h1>출석 기록 수정</h1>
    <p>학생 정보, 출석일시, 위치 인증 기록을 한 화면에서 수정합니다.</p>
  </div>
</div>

<section class="admin-card form-card edit-form-card" id="editFormCard">
  <?php
    $studentNoRange = $app->lengthRange('student_no_length', 5, 5);
    $studentNoPattern = $studentNoRange['min'] === $studentNoRange['max']
      ? '\d{' . $studentNoRange['min'] . '}'
      : '\d{' . $studentNoRange['min'] . ',' . $studentNoRange['max'] . '}';
    $studentNameRange = $app->lengthRange('student_name_length', 1, 10);
  ?>
  <form id="editForm">
    <input id="editIdInput" type="hidden">

    <fieldset class="edit-section">
      <legend class="edit-section-title"><i class="bi bi-person-vcard"></i> 기본 정보</legend>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="studentNoInput">학번</label>
          <input class="form-control form-control-lg" id="studentNoInput" name="student_no" inputmode="numeric" pattern="<?php echo $h($studentNoPattern); ?>" maxlength="<?php echo $h($studentNoRange['max']); ?>" minlength="<?php echo $h($studentNoRange['min']); ?>" required>
          <div class="form-text"><?php echo $h($app->lengthRequirementText('학번은', 'student_no_length', 5, 5)); ?></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="nameInput">이름</label>
          <input class="form-control form-control-lg" id="nameInput" name="name" maxlength="<?php echo $h($studentNameRange['max']); ?>" minlength="<?php echo $h($studentNameRange['min']); ?>" required>
          <div class="form-text"><?php echo $h($app->lengthRequirementText('이름은', 'student_name_length', 1, 10)); ?></div>
        </div>
      </div>
    </fieldset>

    <fieldset class="edit-section">
      <legend class="edit-section-title"><i class="bi bi-calendar-check"></i> 출석 정보</legend>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="createdAtInput">출석일시</label>
          <input class="form-control form-control-lg" id="createdAtInput" type="datetime-local" step="1" required>
        </div>
      </div>
    </fieldset>

    <fieldset class="edit-section">
      <legend class="edit-section-title edit-location-title">
        <span><i class="bi bi-geo-alt"></i> 위치 인증</span>
        <span class="form-check form-switch edit-status-mode-switch">
          <input class="form-check-input" id="locationStatusManualSwitch" type="checkbox" role="switch">
          <label class="form-check-label" for="locationStatusManualSwitch">수동</label>
        </span>
      </legend>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="locationStatusEditInput">위치 인증 상태</label>
          <select class="form-select form-select-lg" id="locationStatusEditInput" disabled>
            <option value="unchecked">위치 인증 미사용</option>
            <option value="verified">위치 인증 완료</option>
            <option value="pending">관리자 승인 대기</option>
            <option value="approved">관리자 승인 완료</option>
            <option value="rejected">위치 인증 반려</option>
          </select>
          <div class="form-text" id="locationStatusModeText">자동 모드에서는 좌표와 위치 설정을 기준으로 계산됩니다.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="locationCheckedAtInput">위치 확인 시각</label>
          <input class="form-control form-control-lg" id="locationCheckedAtInput" type="datetime-local" step="1">
        </div>

        <div class="col-md-6">
          <label class="form-label" for="locationLatitudeInput">위도</label>
          <input class="form-control form-control-lg" id="locationLatitudeInput" type="number" inputmode="decimal" step="0.000001" min="-90" max="90" placeholder="37.123456">
        </div>

        <div class="col-md-6">
          <label class="form-label" for="locationLongitudeInput">경도</label>
          <input class="form-control form-control-lg" id="locationLongitudeInput" type="number" inputmode="decimal" step="0.000001" min="-180" max="180" placeholder="127.123456">
        </div>

        <div class="col-md-6">
          <label class="form-label" for="locationAccuracyInput">정확도</label>
          <div class="input-group input-group-lg">
            <input class="form-control" id="locationAccuracyInput" type="number" inputmode="decimal" min="0" step="0.1">
            <span class="input-group-text">m</span>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="locationDistanceInput">중심과 거리</label>
          <div class="input-group input-group-lg">
            <input class="form-control" id="locationDistanceInput" type="number" inputmode="decimal" min="0" step="0.1" readonly disabled>
            <span class="input-group-text">m</span>
          </div>
          <div class="form-text">출석 가능 중심 좌표가 설정되어 있으면 자동 계산됩니다.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="locationApprovedAtInput">승인/반려 시각</label>
          <input class="form-control form-control-lg" id="locationApprovedAtInput" type="datetime-local" step="1">
        </div>

        <div class="col-12">
          <label class="form-label" for="locationMessageTemplateInput">위치 메시지</label>
          <select class="form-select form-select-lg mb-2" id="locationMessageTemplateInput">
            <option value="auto">자동 메시지</option>
            <option value="verified">위치 인증 완료</option>
            <option value="pending_range">교내 출석 가능 범위 밖으로 확인되어 관리자 승인 이후 정상 출결로 처리됩니다.</option>
            <option value="pending_settings">위치 설정을 확인할 수 없어 관리자 승인 이후 정상 출결로 처리됩니다.</option>
            <option value="approved">관리자 승인 완료</option>
            <option value="rejected">위치 인증이 관리자에 의해 반려되었습니다.</option>
            <option value="unchecked">위치 인증 미사용</option>
            <option value="custom">기타</option>
          </select>
          <textarea class="form-control" id="locationMessageInput" rows="3" placeholder="내용을 입력해주세요" readonly></textarea>
        </div>
      </div>

      <div class="edit-map-panel">
        <div class="location-map-heading">
          <strong>출석 위치</strong>
          <span>지도를 클릭하거나 마커를 드래그하면 좌표가 바뀝니다.</span>
        </div>
        <div class="location-map edit-location-map" id="editLocationMap" aria-label="출석 위치 좌표 지도"></div>
        <div class="edit-coordinate-actions">
          <button class="btn btn-sm btn-outline-secondary" id="clearEditLocationButton" type="button">
            <i class="bi bi-x-circle me-1"></i> 좌표 비우기
          </button>
          <button class="btn btn-sm btn-success" id="useCurrentEditLocationButton" type="button">
            <i class="bi bi-crosshair me-1"></i> 현재 위치 가져오기
          </button>
        </div>
      </div>
    </fieldset>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-success px-4" id="saveEditButton" type="submit">저장</button>
      <a class="btn btn-outline-secondary" href="/admin/list.php">취소</a>
    </div>
  </form>
</section>

<section class="admin-card form-card edit-empty-state" id="editEmptyState" hidden>
  <div class="empty-table-state">
    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
    <strong id="editEmptyTitle">출석 기록을 찾을 수 없습니다.</strong>
    <p class="text-secondary mb-0" id="editEmptyDescription">목록에서 다시 수정할 기록을 선택해주세요.</p>
    <a class="btn btn-outline-success mt-2" href="/admin/list.php">출석 목록으로 돌아가기</a>
  </div>
</section>
