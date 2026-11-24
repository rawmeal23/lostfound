<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/claims.php';

require_login();
$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$claims = claims_by_user((int) $user['id']);
$page_title = 'My Claims | LSPU Lost and Found';

// Explains what the person should do next, based on the claim and item status.
$next_step = static function (array $claim): string {
    if ($claim['claim_status'] === 'pending') {
        return 'Waiting for the admin to review your proof.';
    }
    if ($claim['claim_status'] === 'rejected') {
        return 'The admin did not approve this claim.';
    }
    if ($claim['item_status'] === 'returned') {
        return 'The item has been returned to you.';
    }
    return 'Approved. Visit the Lost and Found office with your school ID to collect it.';
};

require __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="assets/css/account.css">
<main>
  <!-- EDIT: page title and subtitle -->
  <div class="page-head"><div class="container"><h1>My Claims</h1><p>Track the claims you have submitted.</p></div></div>
  <section><div class="container">
    <?php if ($claims === []): ?>
      <p class="empty">You have not submitted any claims yet.</p>
      <p class="button-row"><a class="btn" href="browse.php">Browse items</a></p>
    <?php else: ?>
      <div class="scroll">
        <table class="data-table">
          <thead>
            <tr><th>Item</th><th>Claim date</th><th>Claim status</th><th>What happens next</th></tr>
          </thead>
          <tbody>
            <?php foreach ($claims as $claim): ?>
              <tr>
                <td>
                  <?php if ($claim['item_status'] === 'approved'): ?>
                    <a href="item.php?id=<?= (int) $claim['item_id'] ?>"><strong><?= e((string) $claim['title']) ?></strong></a>
                  <?php else: ?>
                    <strong><?= e((string) $claim['title']) ?></strong>
                  <?php endif; ?>
                  <span class="meta"><?= e((string) $claim['type']) ?></span>
                </td>
                <td><?= e((new DateTimeImmutable((string) $claim['created_at']))->format('M j, Y')) ?></td>
                <td><span class="badge <?= e((string) $claim['claim_status']) ?>"><?= e((string) $claim['claim_status']) ?></span></td>
                <td><?= e($next_step($claim)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
