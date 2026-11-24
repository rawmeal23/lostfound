<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/items_public.php';
require_once __DIR__ . '/../src/claims.php';
require_once __DIR__ . '/../src/items_member.php';

// CHANGE: Load database item details and submit member claims to SQL.
$raw_id = $_GET['id'] ?? '';
$item_id = is_string($raw_id) ? filter_var($raw_id, FILTER_VALIDATE_INT) : false;
$item = $item_id !== false && $item_id !== null ? item_get((int) $item_id) : null;
if ($item === null) {
    http_response_code(404);
}

$user = current_user();
$errors = [];
$message = flash_get();
if ($item !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();
    // Owner of a lost report: "I already found this item" closes the report.
    if (($_POST['action'] ?? '') === 'found_by_owner') {
        $closed = false;
        try {
            $closed = item_mark_found_by_owner((int) $item['id'], (int) $user['id']);
        } catch (PDOException $exception) {
            // Most likely sql/upgrade_edit_delete.sql has not been imported yet.
            flash_set('error', 'The report could not be closed. Ask the developer to import sql/upgrade_edit_delete.sql.');
            redirect('item.php?id=' . (int) $item['id']);
        }
        if ($closed) {
            flash_set('success', 'Glad you found it! Your lost report was removed.');
            redirect('my_posts.php');
        }
        flash_set('error', 'That report could not be closed.');
        redirect('item.php?id=' . (int) $item['id']);
    }
    $proof = $_POST['proof'] ?? '';
    if (!is_string($proof)) {
        $errors[] = 'Enter claim details.';
    } else {
        $result = claim_create((int) $item['id'], (int) $user['id'], $proof);
        if ($result['ok']) {
            flash_set('success', 'Your claim was saved and is waiting for admin review.');
            redirect('item.php?id=' . (int) $item['id']);
        }
        $errors = $result['errors'];
    }
}

$page_title = $item === null ? 'Item Not Found' : (string) $item['title'];
$event_date = $item === null ? null : ($item['type'] === 'lost' ? $item['date_lost'] : $item['date_found']);
require __DIR__ . '/../templates/header.php';
?>
<main>
  <section><div class="container">
    <p><a href="browse.php">&larr; Back to browse</a></p>
    <?php if ($item === null): ?>
      <p class="empty">Item not found or no longer available.</p>
    <?php else: ?>
      <div class="detail">
        <div>
          <?php if (!empty($item['photo_path'])): ?>
            <img src="<?= e((string) $item['photo_path']) ?>" alt="<?= e((string) $item['title']) ?>">
          <?php else: ?>
            <div class="empty">No photo</div>
          <?php endif; ?>
        </div>
        <div>
          <span class="badge <?= e((string) $item['type']) ?>"><?= e((string) $item['type']) ?></span>
          <h1><?= e((string) $item['title']) ?></h1>
          <dl>
            <dt>Category</dt><dd><?= e((string) $item['category']) ?></dd>
            <dt>Location</dt><dd><?= e((string) $item['location']) ?></dd>
            <dt>Date</dt><dd><?= e(date('M j, Y', strtotime((string) $event_date))) ?></dd>
            <?php if ($item['location_note'] !== null && $item['location_note'] !== ''): ?>
              <dt>Details</dt><dd><?= e((string) $item['location_note']) ?></dd>
            <?php endif; ?>
            <?php if ($item['kept_at'] !== null): ?>
              <dt>Kept at</dt><dd><?= $item['kept_at'] === 'at_office' ? 'Office' : 'With finder' ?></dd>
            <?php endif; ?>
          </dl>
          <?php if ($user !== null): ?>
            <p><?= nl2br(e((string) $item['description'])) ?></p>
          <?php endif; ?>
          <div class="panel">
            <?php $is_owner = $user !== null && (int) $user['id'] === (int) $item['user_id']; ?>
            <h2><?= $item['type'] === 'found' ? 'Is this yours?' : ($is_owner ? 'Your lost item' : 'Did you find this?') ?></h2>
            <?php if (($message['message'] ?? '') !== ''): ?>
              <p class="msg show<?= ($message['type'] ?? '') === 'error' ? ' is-error' : '' ?>" role="status"><?= e((string) $message['message']) ?></p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
              <div class="msg show" role="alert" style="background:#fde8e7;border-color:#e1aaa5;color:#8a1f17">
                <?php foreach ($errors as $error): ?><p><?= e((string) $error) ?></p><?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($item['type'] === 'lost' && $is_owner): ?>
              <p>Did you already find this item yourself? Press the button and the report is removed. You do not need to post anything else.</p>
              <form method="post" action="item.php?id=<?= (int) $item['id'] ?>" onsubmit="return confirm('You found this item? The report will be removed.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="found_by_owner">
                <button class="btn">I already found this item</button>
              </form>
            <?php elseif ($item['type'] === 'lost'): ?>
              <p>This item was reported lost. If you found it, post it as a found item or bring it to the Lost and Found office.</p>
              <p><a class="btn" href="report.php?type=found">Post a found item</a></p>
            <?php elseif ($user === null): ?>
              <p><a href="login.php">Log in</a> to submit a claim.</p>
            <?php elseif ((int) $user['id'] === (int) $item['user_id']): ?>
              <p class="meta">You cannot claim your own report.</p>
            <?php else: ?>
              <form method="post" action="item.php?id=<?= (int) $item['id'] ?>">
                <?= csrf_field() ?>
                <div class="field">
                  <label for="proof">Claim details and proof</label>
                  <textarea id="proof" name="proof" rows="4" required></textarea>
                </div>
                <button class="btn" style="margin-top:12px">Submit claim</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
