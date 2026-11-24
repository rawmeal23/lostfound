<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/items_member.php';
require_once __DIR__ . '/../src/items_public.php';

$options = item_form_options();
$recent_items = array_merge(
    items_recent('lost', 4),
    items_recent('found', 4)
);
usort($recent_items, static function (array $left, array $right): int {
    $date_order = strcmp((string) $right['created_at'], (string) $left['created_at']);
    return $date_order !== 0 ? $date_order : ((int) $right['id'] <=> (int) $left['id']);
});
$recent_items = array_slice($recent_items, 0, 4);
$steps = [
    ['Report or post', 'Report what you lost, or post what you found.'],
    ['Search and match', 'Browse listings or let the system suggest a match.'],
    ['Claim', 'Answer a few questions only the real owner would know.'],
    ['Pick up', 'Get your item at the office after the admin verifies you.'],
];
$page_title = 'Home | LSPU Lost and Found';
$body_class = 'page-home'; // the campus photo becomes the page background (see theme.css)

require __DIR__ . '/../templates/header.php';
?>
<main>
  <div class="hero" style="--hero-img: url('assets/images/hero.jpg')">
    <div class="container">
      <h1>Lost something on campus? Found something?</h1>
      <p>A central place for students, teachers, and school personnel to report lost belongings, post found items, and search for missing possessions.</p>
      <div class="hero-actions">
        <a class="btn alt" href="report.php?type=lost">I lost something</a>
        <a class="btn ghost" style="color:#fff;border-color:#fff" href="report.php?type=found">I found something</a>
      </div>
      <form class="searchbar" method="get" action="browse.php">
        <input type="search" name="q" aria-label="Search items" placeholder="Search for an ID, wallet, phone, keys...">
        <button class="btn">Search</button>
      </form>
    </div>
  </div>

  <section><div class="container">
    <div class="section-title"><h2>How it works</h2><p>Four simple steps to get your item back.</p></div>
    <div class="steps">
      <?php foreach ($steps as $index => $step): ?>
        <div class="step">
          <div class="num"><?= $index + 1 ?></div>
          <h3><?= e($step[0]) ?></h3>
          <p class="meta"><?= e($step[1]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div></section>

  <section style="padding-top:0"><div class="container">
    <div class="section-title"><h2>Browse by category</h2></div>
    <div class="cats">
      <?php foreach ($options['categories'] as $category): ?>
        <a class="chip" href="browse.php?category_id=<?= (int) $category['id'] ?>"><?= e((string) $category['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div></section>

  <section style="padding-top:0"><div class="container">
    <div class="section-title">
      <h2>Recently posted</h2>
      <p><a href="browse.php">See all items</a></p>
    </div>
    <div class="grid">
      <?php if ($recent_items === []): ?>
        <p class="empty">No approved items have been posted yet.</p>
      <?php endif; ?>
      <?php foreach ($recent_items as $item): ?>
        <?php
        $event_date = $item['type'] === 'lost' ? $item['date_lost'] : $item['date_found'];
        $formatted_date = (new DateTimeImmutable((string) $event_date))->format('M j, Y');
        ?>
        <a class="item-card" href="item.php?id=<?= (int) $item['id'] ?>">
          <?php if (!empty($item['photo_path'])): ?>
            <img src="<?= e((string) $item['photo_path']) ?>" alt="<?= e((string) $item['title']) ?>">
          <?php else: ?>
            <div class="item-photo-placeholder" aria-hidden="true"></div>
          <?php endif; ?>
          <div class="body">
            <span class="badge <?= e((string) $item['type']) ?>"><?= e((string) $item['type']) ?></span>
            <h3><?= e((string) $item['title']) ?></h3>
            <div class="meta"><?= e((string) $item['category']) ?> &middot; <?= e((string) $item['location']) ?><br><?= e($formatted_date) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div></section>

  <!-- EDIT: this strip explains the colors of the seal. Delete the whole section if you do not want it. -->
  <section class="seal-note"><div class="container">
    <div class="section-title"><h2>Inspired by our university seal</h2><p>The navy blue and white of this site follow the meaning of the seal.</p></div>
    <div class="seal-grid">
      <div class="seal-card"><span class="swatch swatch-blue" aria-hidden="true"></span><h3>Dark blue</h3><p>Peace, unity, harmony, understanding, and enlightenment.</p></div>
      <div class="seal-card"><span class="swatch swatch-white" aria-hidden="true"></span><h3>White</h3><p>Purity, dignity, and honor, shown in the white letters.</p></div>
    </div>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
