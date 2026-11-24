<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/items_public.php';
require_once __DIR__ . '/../src/items_member.php';

// CHANGE: Populate the public browse page from approved database reports.
$options = item_form_options();
$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$location_id = $_GET['location_id'] ?? '';
$when = $_GET['when'] ?? '';
$filters = [];
if (is_string($query)) {
    $filters['q'] = trim($query);
}
if (is_string($type) && in_array($type, ['lost', 'found'], true)) {
    $filters['type'] = $type;
}
if (is_string($category_id) && ctype_digit($category_id) && (int) $category_id > 0) {
    $filters['category_id'] = (int) $category_id;
}
if (is_string($location_id) && ctype_digit($location_id) && (int) $location_id > 0) {
    $filters['location_id'] = (int) $location_id;
}
// "When" menu: ready-made choices instead of two date boxes.
// Each choice becomes a start date for the existing date_from filter.
$when_choices = [
    'week' => ['Last 7 days', '-7 days'],
    'month' => ['Last 30 days', '-30 days'],
    'three_months' => ['Last 3 months', '-3 months'],
    'six_months' => ['Last 6 months', '-6 months'],
];
if (!is_string($when) || !isset($when_choices[$when])) {
    $when = '';
}
if ($when !== '') {
    $filters['date_from'] = (new DateTimeImmutable('today'))->modify($when_choices[$when][1])->format('Y-m-d');
}
$raw_page = $_GET['page'] ?? '1';
$page = is_string($raw_page) && ctype_digit($raw_page) ? max(1, (int) $raw_page) : 1;
$per_page = 24;
$result = items_search($filters, $page, $per_page);
$message = flash_get();
$page_title = 'Browse Items';
$page_count = max(1, (int) ceil($result['total'] / $per_page));
$page = min($page, $page_count);
// Page links keep the "When" choice (not the calculated start date).
$link_filters = $filters;
unset($link_filters['date_from']);
if ($when !== '') {
    $link_filters['when'] = $when;
}
$page_url = static function (int $number) use ($link_filters): string {
    return 'browse.php?' . e(http_build_query($link_filters + ['page' => $number]));
};
$format_date = static function (array $item): string {
    $date = $item['type'] === 'lost' ? $item['date_lost'] : $item['date_found'];
    return date('M j, Y', strtotime((string) $date));
};

require __DIR__ . '/../templates/header.php';
?>
<main>
  <div class="page-head"><div class="container"><h1>Browse Items</h1><p>Search approved lost and found reports on campus.</p></div></div>
  <section><div class="container">
    <?php if (($message['message'] ?? '') !== ''): ?>
      <p class="msg show" role="status"><?= e((string) $message['message']) ?></p>
    <?php endif; ?>
    <form class="filters" method="get" action="browse.php">
      <input class="filter-search" type="search" name="q" value="<?= e(is_string($query) ? $query : '') ?>" placeholder="Search keyword..." aria-label="Keyword">
      <select name="type" aria-label="Type">
        <option value="">All types</option>
        <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
        <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
      </select>
      <select name="category_id" aria-label="Category">
        <option value="">All categories</option>
        <?php foreach ($options['categories'] as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $category_id ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="location_id" aria-label="Location">
        <option value="">All locations</option>
        <?php foreach ($options['locations'] as $location): ?>
          <option value="<?= (int) $location['id'] ?>" <?= (string) $location['id'] === $location_id ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="when" aria-label="When it was lost or found">
        <option value="">Any time</option>
        <?php foreach ($when_choices as $choice_key => $choice): ?>
          <option value="<?= e($choice_key) ?>" <?= $when === $choice_key ? 'selected' : '' ?>><?= e($choice[0]) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn">Search</button>
    </form>
    <p class="meta"><?= (int) $result['total'] ?> item(s) found</p>
    <div class="grid">
      <?php if ($result['rows'] === []): ?>
        <p class="empty">No items match your search.</p>
      <?php endif; ?>
      <?php foreach ($result['rows'] as $item): ?>
        <a class="item-card" href="item.php?id=<?= (int) $item['id'] ?>">
          <?php if (!empty($item['photo_path'])): ?>
            <img src="<?= e((string) $item['photo_path']) ?>" alt="<?= e((string) $item['title']) ?>">
          <?php else: ?>
            <div class="item-photo-placeholder" aria-hidden="true"></div>
          <?php endif; ?>
          <div class="body">
            <span class="badge <?= e((string) $item['type']) ?>"><?= e((string) $item['type']) ?></span>
            <h3><?= e((string) $item['title']) ?></h3>
            <div class="meta"><?= e((string) $item['category']) ?> &middot; <?= e((string) $item['location']) ?><br><?= e($format_date($item)) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($page_count > 1): ?>
      <nav aria-label="Browse pages" style="margin-top:24px;display:flex;gap:12px;justify-content:center">
        <?php if ($page > 1): ?><a class="btn small" href="<?= $page_url($page - 1) ?>">Previous</a><?php endif; ?>
        <span class="meta">Page <?= $page ?> of <?= $page_count ?></span>
        <?php if ($page < $page_count): ?><a class="btn small" href="<?= $page_url($page + 1) ?>">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
