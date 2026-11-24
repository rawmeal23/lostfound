<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/items_member.php';

require_login();
$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$message = flash_get();
$items = items_by_user((int) $user['id']);
$page_title = 'My Posts | LSPU Lost and Found';

// Short plain-English note shown under each status.
$status_notes = [
    'pending' => 'Waiting for admin review. Only you and the admin can see it.',
    'approved' => 'Visible to everyone on the Browse page.',
    'rejected' => 'The admin did not approve this report.',
    'claimed' => 'A claim was approved. The office will hand over the item.',
    'returned' => 'This item was returned to its owner.',
    'archived' => 'Closed by the admin.',
];

require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/account.css">
<main>
  <!-- EDIT: page title and subtitle -->
  <div class="page-head"><div class="container"><h1>My Posts</h1><p>The lost and found reports you have submitted.</p></div></div>
  <section><div class="container">
    <?php if (($message['message'] ?? '') !== ''): ?>
      <p class="msg show" role="status"><?= e((string) $message['message']) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
      <p class="empty">You have not posted anything yet.</p>
      <p class="button-row">
        <a class="btn" href="report.php?type=lost">Report a lost item</a>
        <a class="btn ghost" href="report.php?type=found">Post a found item</a>
      </p>
    <?php else: ?>
      <div class="scroll">
        <table class="data-table">
          <thead>
            <tr><th>Item</th><th>Type</th><th>Category</th><th>Place</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <?php
              $event_date = $item['type'] === 'lost' ? $item['date_lost'] : $item['date_found'];
              $formatted_date = (new DateTimeImmutable((string) $event_date))->format('M j, Y');
              ?>
              <tr>
                <td>
                  <?php if ($item['status'] === 'approved'): ?>
                    <a href="item.php?id=<?= (int) $item['id'] ?>"><strong><?= e((string) $item['title']) ?></strong></a>
                  <?php else: ?>
                    <strong><?= e((string) $item['title']) ?></strong>
                  <?php endif; ?>
                </td>
                <td><span class="badge <?= e((string) $item['type']) ?>"><?= e((string) $item['type']) ?></span></td>
                <td><?= e((string) $item['category']) ?></td>
                <td><?= e((string) $item['location']) ?></td>
                <td><?= e($formatted_date) ?></td>
                <td>
                  <span class="badge <?= e((string) $item['status']) ?>"><?= e((string) $item['status']) ?></span>
                  <span class="meta"><?= e($status_notes[(string) $item['status']] ?? '') ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
