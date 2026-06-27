<div class="admin-page-header">
  <div>
    <p class="section-kicker">Security</p>
    <h1>비밀번호 변경</h1>
    <p>변경 후 모든 관리자 로그인 세션이 만료됩니다.</p>
  </div>
</div>

<section class="admin-card form-card">
  <?php $passwordRange = $app->lengthRange('password_length', 4, 32); ?>
  <form id="passwordForm">
    <div class="mb-3">
      <label class="form-label" for="oldPasswordInput">기존 비밀번호</label>
      <div class="password-field">
        <input class="form-control form-control-lg" id="oldPasswordInput" name="old_password" type="password" autocomplete="current-password" required>
        <button class="password-toggle-button" type="button" data-password-toggle="oldPasswordInput" aria-label="비밀번호 표시">
          <i class="bi bi-eye"></i>
        </button>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="newPasswordInput">새 비밀번호</label>
      <div class="password-field">
        <input class="form-control form-control-lg" id="newPasswordInput" name="new_password" type="password" autocomplete="new-password" minlength="<?php echo $h($passwordRange['min']); ?>" maxlength="<?php echo $h($passwordRange['max']); ?>" required>
        <button class="password-toggle-button" type="button" data-password-toggle="newPasswordInput" aria-label="비밀번호 표시">
          <i class="bi bi-eye"></i>
        </button>
      </div>
      <div class="form-text"><?php echo $h($app->lengthRequirementText('새 비밀번호는', 'password_length', 4, 32)); ?></div>
    </div>
    <div class="mb-4">
      <label class="form-label" for="newPasswordConfirmInput">새 비밀번호 확인</label>
      <div class="password-field">
        <input class="form-control form-control-lg" id="newPasswordConfirmInput" type="password" autocomplete="new-password" minlength="<?php echo $h($passwordRange['min']); ?>" maxlength="<?php echo $h($passwordRange['max']); ?>" required>
        <button class="password-toggle-button" type="button" data-password-toggle="newPasswordConfirmInput" aria-label="비밀번호 표시">
          <i class="bi bi-eye"></i>
        </button>
      </div>
    </div>
    <button class="btn btn-success btn-lg w-100" id="changePasswordButton" type="submit">비밀번호 변경</button>
  </form>
</section>
