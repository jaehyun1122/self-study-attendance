<div class="admin-page-header">
  <div>
    <p class="section-kicker">Edit Attendance</p>
    <h1>출석 기록 수정</h1>
    <p>운영상 필요한 학생 정보, 출석일시, 위치 인증 상태만 수정합니다.</p>
  </div>
</div>

<div id="adminAlert"></div>

<section class="admin-card form-card edit-form-card">
  <?php
    $studentNoRange = $app->lengthRange('student_no_length', 5, 5);
    $studentNoPattern = $studentNoRange['min'] === $studentNoRange['max']
      ? '\d{' . $studentNoRange['min'] . '}'
      : '\d{' . $studentNoRange['min'] . ',' . $studentNoRange['max'] . '}';
    $studentNameRange = $app->lengthRange('student_name_length', 1, 10);
  ?>
  <form id="editForm">
    <input id="editIdInput" type="hidden">
    <input id="editDateInput" type="hidden">

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

      <div class="col-md-6">
        <label class="form-label" for="createdAtInput">출석일시</label>
        <input class="form-control form-control-lg" id="createdAtInput" type="datetime-local" step="1" required>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="locationStatusEditInput">위치 인증 상태</label>
        <select class="form-select form-select-lg" id="locationStatusEditInput">
          <option value="unchecked">위치 인증 미사용</option>
          <option value="verified">위치 인증 완료</option>
          <option value="pending">관리자 승인 대기</option>
          <option value="approved">관리자 승인 완료</option>
          <option value="rejected">위치 인증 반려</option>
        </select>
      </div>

      <div class="col-12">
        <div class="edit-readonly-note">
          위치 좌표, 정확도, 거리, 위치 메시지와 인증 시각은 출석 당시 기록이므로 이 화면에서 직접 수정할 수 없습니다.
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-success px-4" id="saveEditButton" type="submit">저장</button>
      <a class="btn btn-outline-secondary" href="/admin/list.php">취소</a>
    </div>
  </form>
</section>
