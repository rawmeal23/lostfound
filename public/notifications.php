<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

require_login();
$user = current_user();
if ($user === null) {
    redirect('login.php');
}

// Read the list first so we can still show which ones were new,
// then mark them all as read.
$notifications = notifications_by_user((int) $user['id']);
notifications_mark_all_read((int) $user['id']);

$page_title = 'Notifications | LSPU Lost and Found';
require __DIR__ . '/../templates/header.php';
?>
<main>
  <!-- EDIT: page title and subtitle -->
  <div class="page-head"><div class="container"><h1>Notifications</h1><p>Updates about your reports and claims.</p></div></div>
  <section><div class="container" style="max-width:760px">
    <?php if ($notifications === []): ?>
      <p class="empty">You have no notifications yet.</p>
    <?php else: ?>
      <ul class="notice-list">
        <?php foreach ($notifications as $notification): ?>
          <li class="<?= (int) $notification['is_read'] === 0 ? 'is-new' : '' ?>">
            <?php if ((int) $notification['is_read'] === 0): ?><span class="new-tag">New</span><?php endif; ?>
            <p><?= e((string) $notification['message']) ?></p>
            <span class="meta"><?= e((new DateTimeImmutable((string) $notification['created_at']))->format('M j, Y g:i A')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
