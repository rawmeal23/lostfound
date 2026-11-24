<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';
require_once __DIR__ . '/../src/items_admin.php';
$header_user = current_user();
$unread_count = $header_user !== null ? notifications_unread_count((int) $header_user['id']) : 0;

// Admins see a red number on the Admin link when items need their action.
$header_is_admin = $header_user !== null && $header_user['role'] === 'admin';
$admin_action_total = $header_is_admin ? admin_action_count() : 0;

// Which menu link is the current section? Pages that belong to a section
// (item.php under Browse, report.php under My Posts) darken that link.
// EDIT: add a page to this list to make it belong to a section.
$nav_sections = [
    'index.php' => 'home',
    'browse.php' => 'browse', 'item.php' => 'browse',
    'my_posts.php' => 'posts', 'report.php' => 'posts',
    'my_claims.php' => 'claims',
    'notifications.php' => 'notifications',
    'admin.php' => 'admin',
    'change_password.php' => 'password',
    'register.php' => 'register',
];
$nav_current = $nav_sections[basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))] ?? '';
// Returns the class and aria-current attributes for the link of a section.
$nav_attr = static fn (string $section): string => $section === $nav_current ? ' class="is-current" aria-current="page"' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'LSPU Lost and Found') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/polish.css">
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/theme.css">
  <script src="assets/js/nav.js" defer></script>
</head>
<body<?= isset($body_class) ? ' class="' . e((string) $body_class) . '"' : '' ?>>
<!-- CHANGE: Use shared navigation for the protected PHP pages. -->
<header class="site-header">
  <div class="container">
    <a class="brand" href="index.php">
      <img src="assets/images/logo.jpg" alt="LSPU Lost and Found logo">
      <span>LSPU Lost and Found</span>
    </a>
    <button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false">
      &#9776;<?php if ($unread_count > 0 || $admin_action_total > 0): ?><span class="menu-dot" aria-hidden="true"></span><?php endif; ?>
    </button>
    <div class="nav-backdrop"></div>
    <nav class="nav" aria-label="Main navigation">
      <button class="nav-close" type="button" aria-label="Close menu">&times;</button>
      <a href="index.php"<?= $nav_attr('home') ?>>Home</a>
      <a href="browse.php"<?= $nav_attr('browse') ?>>Browse Items</a>
      <?php if ($header_user !== null): ?>
        <a href="my_posts.php"<?= $nav_attr('posts') ?>>My Posts</a>
        <a href="my_claims.php"<?= $nav_attr('claims') ?>>My Claims</a>
        <a href="notifications.php"<?= $nav_attr('notifications') ?>>Notifications<?php if ($unread_count > 0): ?><span class="nav-count"><?= $unread_count > 99 ? '99+' : $unread_count ?></span><?php endif; ?></a>
        <a href="change_password.php"<?= $nav_attr('password') ?>>Password</a>
      <?php endif; ?>
      <?php if ($header_is_admin): ?>
        <a href="admin.php"<?= $nav_attr('admin') ?>>Admin<?php if ($admin_action_total > 0): ?><span class="nav-count" title="Items that need your action"><?= $admin_action_total > 99 ? '99+' : $admin_action_total ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if ($header_user !== null): ?>
        <span class="nav-user"><?= e((string) $header_user['school_id']) ?></span>
        <form method="post" action="logout.php">
          <?= csrf_field() ?>
          <button type="submit" class="btn-logout">Log out</button>
        </form>
      <?php else: ?>
        <a href="register.php"<?= $nav_attr('register') ?>>Register</a>
        <a class="btn-nav" href="login.php">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
