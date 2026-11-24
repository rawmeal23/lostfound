<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/users.php';

// People who are already logged in do not need this page.
if (current_user() !== null) {
    redirect('my_posts.php');
}

$page_title = 'Register | LSPU Lost and Found';
$errors = [];
$old = ['school_id' => '', 'full_name' => '', 'email' => '', 'contact_no' => '', 'role' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Read every text field safely (ignore anything that is not plain text).
    foreach (array_keys($old) as $field) {
        $value = $_POST[$field] ?? '';
        $old[$field] = is_string($value) ? trim($value) : '';
    }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $password_confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    $result = register_user($old + ['password' => $password, 'password_confirm' => $password_confirm]);

    if ($result['ok']) {
        // Log the new member in right away and send them to their posts page.
        login_user($old['school_id'], $password);
        flash_set('success', 'Welcome! Your account was created. You can now report or claim items.');
        redirect('my_posts.php');
    }

    $errors = $result['errors'];
}

require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/account.css">
<main>
  <!-- EDIT: page title and subtitle -->
  <div class="page-head"><div class="container"><h1>Create an Account</h1><p>Register with your school ID to report and claim items.</p></div></div>
  <section><div class="container" style="max-width:680px">
    <div class="panel">
      <form method="post" action="register.php" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field">
            <label for="school_id">School ID</label>
            <input id="school_id" name="school_id" value="<?= e($old['school_id']) ?>" required autocomplete="username">
            <span class="error"><?= e($errors['school_id'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="role">I am a</label>
            <select id="role" name="role" required>
              <?php foreach (['student' => 'Student', 'teacher' => 'Teacher', 'staff' => 'Staff'] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $old['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="error"><?= e($errors['role'] ?? '') ?></span>
          </div>
          <div class="field full">
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required autocomplete="name">
            <span class="error"><?= e($errors['full_name'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="email">School email</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email">
            <span class="error"><?= e($errors['email'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="contact_no">Contact number (optional)</label>
            <input id="contact_no" name="contact_no" value="<?= e($old['contact_no']) ?>" autocomplete="tel">
            <span class="error"><?= e($errors['contact_no'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
            <span class="error"><?= e($errors['password'] ?? '') ?></span>
          </div>
          <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
            <span class="error"><?= e($errors['password_confirm'] ?? '') ?></span>
          </div>
          <div class="field full">
            <button class="btn">Create account</button><!-- EDIT: button text -->
          </div>
        </div>
      </form>
      <p class="meta" style="margin:16px 0 0;text-align:center">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
