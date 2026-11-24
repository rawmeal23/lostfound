<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/items_admin.php';
require_once __DIR__ . '/../src/claims.php';

require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $raw_id = $_POST['id'] ?? null;
    $id = is_string($raw_id) ? filter_var($raw_id, FILTER_VALIDATE_INT) : false;
    $result = ['ok' => false, 'errors' => ['Invalid action.']];

    if ($id !== false && $id !== null && is_string($action)) {
        if ($action === 'item_status' && is_string($_POST['status'] ?? null)) {
            $result = item_set_status((int) $id, $_POST['status'], (int) $admin['id']);
        } elseif ($action === 'claim_status' && is_string($_POST['status'] ?? null)) {
            $result = claim_review((int) $id, (int) $admin['id'], $_POST['status']);
        } elseif ($action === 'item_outcome' && is_string($_POST['outcome'] ?? null)) {
            $result = item_record_outcome((int) $id, (int) $admin['id'], $_POST['outcome']);
        }
    }

    if ($result['ok']) {
        flash_set('success', 'The item or claim status was updated.');
    } else {
        flash_set('error', implode(' ', $result['errors']));
    }
    redirect('admin.php');
}

$items = items_admin_all();

// Items that need the admin's attention are listed first.
$needs_action = static function (array $item): bool {
    if (in_array($item['status'], ['pending', 'claimed'], true) || (bool) $item['outcome_eligible']) {
        return true;
    }
    foreach ($item['claims'] as $claim) {
        if ($claim['status'] === 'pending') {
            return true;
        }
    }
    return false;
};
// PHP's sort keeps the old order inside each group (newest first).
usort($items, static fn (array $a, array $b): int => (int) $needs_action($b) <=> (int) $needs_action($a));
$action_count = count(array_filter($items, $needs_action));
$logs = item_logs_all();
$message = flash_get();
$page_title = 'Admin Dashboard | LSPU Lost and Found';
$format_date = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    return date('M j, Y', strtotime($value));
};

require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/admin.css">
<main>
  <div class="page-head"><div class="container"><h1>Item and Claim Management</h1><p>Review reports, claims, and returned-item status.</p></div></div>
  <section><div class="container">
    <?php if (($message['message'] ?? '') !== ''): ?>
      <p class="msg show" role="<?= ($message['type'] ?? '') === 'error' ? 'alert' : 'status' ?>" style="<?= ($message['type'] ?? '') === 'error' ? 'background:#fde8e7;border-color:#e1aaa5;color:#8a1f17' : '' ?>"><?= e((string) $message['message']) ?></p>
    <?php endif; ?>
    <!-- CHANGE: Show each report once, with its matching date column and every claimant. -->
    <?php if ($action_count > 0): ?>
      <p class="action-summary"><?= $action_count ?> item(s) need your action. They are listed first.</p>
    <?php endif; ?>
    <div class="scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Item ID</th>
            <th>Picture / item</th>
            <th>Date of Lost</th>
            <th>Date of Found</th>
            <th>Item status</th>
            <th>Claimant school IDs / statuses</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items === []): ?>
            <tr><td colspan="7">No item reports have been submitted.</td></tr>
          <?php endif; ?>
          <?php foreach ($items as $item): ?>
            <tr class="<?= $needs_action($item) ? 'needs-action' : '' ?>">
              <td><?= (int) $item['id'] ?></td>
              <td>
                <?php if (!empty($item['photo_path'])): ?>
                  <img src="<?= e((string) $item['photo_path']) ?>" alt="<?= e((string) $item['title']) ?>" width="90">
                <?php endif; ?>
                <strong><?= e((string) $item['title']) ?></strong>
                <span class="meta"><?= e((string) $item['type']) ?></span>
                <?php if ($needs_action($item)): ?><span class="action-flag">Needs action</span><?php endif; ?>
              </td>
              <td><?= e($format_date($item['date_lost'] === null ? null : (string) $item['date_lost'])) ?></td>
              <td><?= e($format_date($item['date_found'] === null ? null : (string) $item['date_found'])) ?></td>
              <td>
                <span class="<?= e($item['status'] === 'archived' ? 'meta' : 'badge ' . (string) $item['status']) ?>"><?= e((string) $item['status']) ?></span>
                <?php if ((bool) $item['outcome_eligible']): ?>
                  <p class="meta">Lost report is at least three months old.</p>
                  <form method="post" action="admin.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="action" value="item_outcome">
                    <button class="btn small" name="outcome" value="found">Record found</button>
                    <button class="btn small danger" name="outcome" value="never_found">Record never found</button>
                  </form>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($item['claims'] === []): ?>
                  <span class="meta">No claims</span>
                <?php endif; ?>
                <?php foreach ($item['claims'] as $claim): ?>
                  <div>
                    <strong><?= e((string) $claim['school_id']) ?></strong>
                    <span class="badge <?= e((string) $claim['status']) ?>"><?= e((string) $claim['status']) ?></span>
                    <details>
                      <summary>Claim proof</summary>
                      <p><?= nl2br(e((string) $claim['proof_details'])) ?></p>
                    </details>
                    <?php if ($claim['status'] === 'pending' && $item['status'] === 'approved'): ?>
                      <form method="post" action="admin.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                        <input type="hidden" name="action" value="claim_status">
                        <button class="btn small" name="status" value="approved">Approve claim</button>
                        <button class="btn small danger" name="status" value="rejected">Reject claim</button>
                      </form>
                    <?php elseif ($claim['status'] === 'pending'): ?>
                      <form method="post" action="admin.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                        <input type="hidden" name="action" value="claim_status">
                        <button class="btn small danger" name="status" value="rejected">Reject claim</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </td>
              <td>
                <?php if ($item['status'] === 'pending'): ?>
                  <form method="post" action="admin.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="action" value="item_status">
                    <button class="btn small" name="status" value="approved">Approve item</button>
                    <button class="btn small danger" name="status" value="rejected">Reject item</button>
                  </form>
                <?php elseif ($item['status'] === 'claimed'): ?>
                  <form method="post" action="admin.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="action" value="item_status">
                    <button class="btn small" name="status" value="returned">Mark returned</button>
                  </form>
                <?php else: ?>
                  <span class="meta">No action available</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- CHANGE: Show the append-only report, claim, outcome, and return history. -->
    <h2 style="margin-top:32px">Item and Claim History</h2>
    <div class="scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Item</th>
            <th>Event</th>
            <th>Claimant school ID</th>
            <th>Claim date</th>
            <th>Recorded by</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($logs === []): ?>
            <tr><td colspan="7">No history has been recorded.</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e(date('M j, Y H:i', strtotime((string) $log['created_at']))) ?></td>
              <td><?= (int) $log['item_id'] ?> - <?= e((string) $log['item_title']) ?></td>
              <td><?= e(str_replace('_', ' ', (string) $log['event_type'])) ?></td>
              <td><?= e((string) ($log['claimant_school_id'] ?? '-')) ?></td>
              <td><?= $log['claim_date'] === null ? '-' : e(date('M j, Y H:i', strtotime((string) $log['claim_date']))) ?></td>
              <td><?= e((string) ($log['actor_school_id'] ?? '-')) ?></td>
              <td><?= e((string) ($log['details'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
