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

// Delete one of my own pending reports (it is archived so the history stays complete).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $raw_id = $_POST['id'] ?? null;
    $item_id = is_string($raw_id) ? filter_var($raw_id, FILTER_VALIDATE_INT) : false;
    $deleted = false;
    // "I already found this item": the owner of a lost report closes it.
    if (($_POST['action'] ?? '') === 'found_by_owner' && $item_id !== false && $item_id !== null) {
        try {
            $closed = item_mark_found_by_owner((int) $item_id, (int) $user['id']);
        } catch (PDOException $exception) {
            flash_set('error', 'The report could not be closed. Ask the developer to import sql/upgrade_edit_delete.sql.');
            redirect('my_posts.php');
        }
        if ($closed) {
            flash_set('success', 'Glad you found it! Your lost report was removed.');
        } else {
            flash_set('error', 'That report could not be closed.');
        }
        redirect('my_posts.php');
    }
    if (($_POST['action'] ?? '') === 'delete' && $item_id !== false && $item_id !== null) {
        try {
            $deleted = item_delete_own((int) $item_id, (int) $user['id']);
        } catch (PDOException $exception) {
            // Most likely sql/upgrade_edit_delete.sql has not been imported yet.
            flash_set('error', 'The report could not be deleted. Ask the developer to import sql/upgrade_edit_delete.sql.');
            redirect('my_posts.php');
        }
    }
    if ($deleted) {
        flash_set('success', 'Your report was deleted.');
    } else {
        flash_set('error', 'That report could not be deleted. Only reports that are waiting for review can be deleted.');
    }
    redirect('my_posts.php');
}

$message = flash_get();
$items = items_by_user((int) $user['id']);
$page_title = 'My Posts | LSPU Lost and Found';

// Short plain-English note shown under each status.
$status_notes = [
    'pending' => 'Waiting for admin review. You can still edit or delete it.',
    'approved' => 'Visible to everyone on the Browse page. If it is your lost item and you found it, press "I already found this item".',
    'rejected' => 'The admin did not approve this report.',
    'claimed' => 'A claim was approved. The office will hand over the item.',
    'returned' => 'This item was returned to its owner.',
    'archived' => 'Closed. This report is no longer active.',
];

require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/account.css">
<main>
  <!-- EDIT: page title and subtitle -->
  <div class="page-head"><div class="container"><h1>My Posts</h1><p>The lost and found reports you have submitted.</p></div></div>
  <section><div class="container">
    <?php if (($message['message'] ?? '') !== ''): ?>
      <p class="msg show<?= ($message['type'] ?? '') === 'error' ? ' is-error' : '' ?>" role="<?= ($message['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= e((string) $message['message']) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
      <p class="empty">You have not posted anything yet.</p>
      <p class="button-row">
        <a class="btn" href="report.php?type=lost">Report a lost item</a>
        <a class="btn ghost" href="report.php?type=found">Post a found item</a>
      </p>
    <?php else: ?>
      <p class="button-row">
        <a class="btn" href="report.php?type=lost">Report a lost item</a>
        <a class="btn ghost" href="report.php?type=found">Post a found item</a>
      </p>
      <div class="scroll">
        <table class="data-table">
          <thead>
            <tr><th>Item</th><th>Type</th><th>Category</th><th>Place</th><th>Date</th><th>Status</th><th>Actions</th></tr>
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
                <td>
                  <?php if ($item['status'] === 'pending'): ?>
                    <div class="row-actions">
                      <a class="btn small" href="edit_post.php?id=<?= (int) $item['id'] ?>">Edit</a>
                      <form method="post" action="my_posts.php" onsubmit="return confirm('Delete this report? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn small danger">Delete</button>
                      </form>
                    </div>
                  <?php elseif ($item['status'] === 'approved' && $item['type'] === 'lost'): ?>
                    <form method="post" action="my_posts.php" onsubmit="return confirm('You found this item? The report will be removed.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="action" value="found_by_owner">
                      <button class="btn small">I already found this item</button>
                    </form>
                  <?php else: ?>
                    <span class="meta">-</span>
                  <?php endif; ?>
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
