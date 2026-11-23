<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
$header_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'LSPU Lost and Found') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/polish.css">
</head>
<body>
<!-- CHANGE: Use shared navigation for the protected PHP pages. -->
<header class="site-header">
  <div class="container">
    <a class="brand" href="index.php">
      <img src="assets/images/logo.jpg" alt="LSPU Lost and Found logo">
      <span>LSPU Lost and Found</span>
    </a>
    <button class="menu-toggle" type="button" aria-label="Menu" onclick="document.querySelector('.nav').classList.toggle('open')">&#9776;</button>
    <nav class="nav" aria-label="Main navigation">
      <a href="index.php">Home</a>
      <a href="browse.php">Browse Items</a>
      <a href="report.php?type=lost">Report Lost</a>
      <a href="report.php?type=found">Post Found</a>
      <?php if ($header_user !== null && $header_user['role'] === 'admin'): ?>
        <a href="admin.php">Admin</a>
      <?php endif; ?>
      <?php if ($header_user !== null): ?>
        <span class="nav-user"><?= e((string) $header_user['school_id']) ?></span>
        <form method="post" action="logout.php">
          <?= csrf_field() ?>
          <button type="submit" class="btn-logout">Log out</button>
        </form>
      <?php else: ?>
        <a class="btn-nav" href="login.php">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
