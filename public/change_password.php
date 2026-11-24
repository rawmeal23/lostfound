<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/users.php';

require_login();
$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Read every field safely (ignore anything that is not plain text).
    $current_password = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new_password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm_password = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    $result = user_change_password((int) $user['id'], $current_password, $new_password, $confirm_password);

    if ($result['ok']) {
        // A new session id after a password change is safer.
        session_regenerate_id(true);
        flash_set('success', 'Your password was changed.');
        redirect('change_password.php');
    }

    $errors = $result['errors'];
}

$message = flash_get();
$page_title = 'Change Password | LSPU Lost and Found';
require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/account.css">
<main>
  <div class="page-head"><div class="container"><h1>Change Password</h1><p>Use a password that only you know.</p></div></div>
  <section><div class="container" style="max-width:520px">
    <div class="panel">
      <?php if (($message['message'] ?? '') !== ''): ?>
        <p class="msg show" role="status"><?= e((string) $message['message']) ?></p>
      <?php endif; ?>
      <form method="post" action="change_password.php" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid" style="grid-template-columns:1fr">
          <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            <span class="error"><?= e($errors['current_password'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="new_password">New password (8 to 72 characters)</label>
            <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
            <span class="error"><?= e($errors['new_password'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            <span class="error"><?= e($errors['confirm_password'] ?? '') ?></span>
          </div>
          <div class="field"><button class="btn">Save new password</button></div>
        </div>
      </form>
    </div>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
