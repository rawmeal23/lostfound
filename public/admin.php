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
    // Go back to the same filter the admin was looking at.
    $back = $_SESSION['admin_back'] ?? '';
    redirect('admin.php' . (is_string($back) && $back !== '' ? '?' . $back : ''));
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

// ---- Filter tabs: choose which items to show ----
$has_pending_claim = static function (array $item): bool {
    foreach ($item['claims'] as $claim) {
        if ($claim['status'] === 'pending') {
            return true;
        }
    }
    return false;
};
$has_status = static fn (string $status): callable => static fn (array $item): bool => $item['status'] === $status;
// EDIT: each filter is 'key' => ['Label on the tab', function that says if an item belongs to it]
$admin_filters = [
    'action' => ['Needs action', $needs_action],
    'pending' => ['Pending reports', $has_status('pending')],
    'claims' => ['Pending claims', $has_pending_claim],
    'claimed' => ['To be returned', $has_status('claimed')],
    'outcome' => ['Three-month outcome', static fn (array $item): bool => (bool) $item['outcome_eligible']],
    'approved' => ['Approved', $has_status('approved')],
    'returned' => ['Returned', $has_status('returned')],
    'rejected' => ['Rejected', $has_status('rejected')],
    'archived' => ['Archived', $has_status('archived')],
    'all' => ['All items', static fn (array $item): bool => true],
];
$filter_counts = [];
foreach ($admin_filters as $filter_key => $filter_definition) {
    $filter_counts[$filter_key] = count(array_filter($items, $filter_definition[1]));
}
$raw_filter = $_GET['filter'] ?? '';
$filter = is_string($raw_filter) && isset($admin_filters[$raw_filter])
    ? $raw_filter
    : ($filter_counts['action'] > 0 ? 'action' : 'all');
$raw_type = $_GET['type'] ?? '';
$type_filter = is_string($raw_type) && in_array($raw_type, ['lost', 'found'], true) ? $raw_type : '';
// Remember the view, so the page returns to it after an approve or reject click.
$_SESSION['admin_back'] = http_build_query(['filter' => $filter] + ($type_filter !== '' ? ['type' => $type_filter] : []));
$show_item = $admin_filters[$filter][1];
$shown_items = array_values(array_filter(
    $items,
    static fn (array $item): bool => $show_item($item) && ($type_filter === '' || $item['type'] === $type_filter)
));
$filter_url = static fn (string $key, string $type): string => 'admin.php?'
    . e(http_build_query(['filter' => $key] + ($type !== '' ? ['type' => $type] : [])));
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
      <p class="action-summary"><?= $action_count ?> item(s) need your action. Use the "Needs action" tab to see them.</p>
    <?php endif; ?>

    <!-- Filter the list by what needs doing or by status, and optionally by type. -->
    <nav class="filter-bar" aria-label="Filter items">
      <?php foreach ($admin_filters as $filter_key => $filter_definition): ?>
        <a class="filter-tab<?= $filter_key === $filter ? ' is-selected' : '' ?><?= $filter_counts[$filter_key] > 0 && in_array($filter_key, ['action', 'pending', 'claims', 'claimed', 'outcome'], true) ? ' has-action' : '' ?>" href="<?= $filter_url($filter_key, $type_filter) ?>"<?= $filter_key === $filter ? ' aria-current="page"' : '' ?>><?= e($filter_definition[0]) ?> <span class="count"><?= (int) $filter_counts[$filter_key] ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="filter-bar filter-types">
      <span class="meta">Type:</span>
      <?php foreach (['' => 'All types', 'lost' => 'Lost', 'found' => 'Found'] as $type_key => $type_label): ?>
        <a class="filter-tab small<?= $type_key === $type_filter ? ' is-selected' : '' ?>" href="<?= $filter_url($filter, $type_key) ?>"><?= e($type_label) ?></a>
      <?php endforeach; ?>
      <span class="meta"><?= count($shown_items) ?> item(s) shown</span>
    </div>
    <div class="scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Item ID</th>
            <th>Picture / item</th>
            <th>Date</th>
            <th>Item status</th>
            <th>Claimant school IDs / statuses</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($shown_items === []): ?>
            <tr><td colspan="6">No items in this view.</td></tr>
          <?php endif; ?>
          <?php foreach ($shown_items as $item): ?>
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
              <td>
                <span class="meta"><?= $item['type'] === 'lost' ? 'Lost on' : 'Found on' ?></span>
                <?= e($format_date((string) ($item['type'] === 'lost' ? $item['date_lost'] : $item['date_found']))) ?>
              </td>
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
