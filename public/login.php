<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

// CHANGE: Authenticate admin and campus accounts using the database session.
$error_message = '';
$page_title = 'Login | LSPU Lost and Found';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identifier = $_POST['id_or_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (is_string($identifier) && is_string($password) && login_user(trim($identifier), $password)) {
        $user = current_user();
        redirect($user !== null && $user['role'] === 'admin' ? 'admin.php' : 'browse.php');
    }

    $error_message = 'The school ID/email or password was not recognized.';
}

require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/login.css">
<main class="login-page">
  <div class="page-head"><div class="container"><h1>Campus Login</h1><p>Use your campus account to continue.</p></div></div>
  <section><div class="container" style="max-width:520px">
    <div class="panel">
      <?php if ($error_message !== ''): ?>
        <p class="msg show" role="alert" style="background:#fde8e7;border-color:#e1aaa5;color:#8a1f17"><?= e($error_message) ?></p>
      <?php endif; ?>
      <form method="post" action="login.php">
        <?= csrf_field() ?>
        <div class="field">
          <label for="id_or_email">School ID or email</label>
          <input id="id_or_email" name="id_or_email" required autocomplete="username">
        </div>
        <div class="field" style="margin-top:12px">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn" style="margin-top:16px">Login</button>
      </form>
    </div>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
