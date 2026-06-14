<div class="admin-page-header">
  <div>
    <p class="section-kicker">Edit Attendance</p>
    <h1>출석 기록 수정</h1>
    <p>학생 정보만 수정할 수 있으며 출석일시는 초 단위로 표시됩니다.</p>
  </div>
  <a class="btn btn-outline-secondary" id="backToListLink" href="/admin/list.php">목록으로</a>
</div>

<div id="adminAlert"></div>

<section class="admin-card form-card">
  <form id="editForm">
    <input id="editIdInput" type="hidden">
    <input id="editDateInput" type="hidden">

    <div class="mb-3">
      <label class="form-label" for="studentNoInput">학번</label>
      <input class="form-control form-control-lg" id="studentNoInput" name="student_no" maxlength="<?php echo $h($app->int('student_no_length', 5)); ?>" minlength="<?php echo $h($app->int('student_no_length', 5)); ?>" required>
      <div class="form-text">학번은 <?php echo $h($app->int('student_no_length', 5)); ?>자로 입력해주세요.</div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="nameInput">이름</label>
      <input class="form-control form-control-lg" id="nameInput" name="name" maxlength="<?php echo $h($app->int('student_name_max_length', 5)); ?>" required>
      <div class="form-text">이름은 <?php echo $h($app->int('student_name_max_length', 5)); ?>자까지 입력할 수 있습니다.</div>
    </div>

    <div class="attendance-time-box mb-4">
      <span>출석일시</span>
      <strong id="attendDateTimeText">-</strong>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-success px-4" id="saveEditButton" type="submit">저장</button>
      <a class="btn btn-outline-secondary" href="/admin/list.php">취소</a>
    </div>
  </form>
</section>
