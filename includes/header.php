<?php
/**
 * includes/header.php
 * ─────────────────────────────────────────────────────────
 * Shared header for all user-facing pages.
 *
 * USAGE — at the top of any page, set these before including:
 *
 *   $page_title  = "Browse Items";   // <title> tag + page heading
 *   $active_nav  = "browse";         // highlights correct sidebar link
 *                                    // values: dashboard, browse, post-lost,
 *                                    //         post-found, claims, my-posts
 *   require 'includes/header.php';
 *
 * The file assumes session_start() and db.php have already been called.
 */

$page_title = $page_title ?? 'Lost & Found Portal';
$active_nav = $active_nav ?? '';

$_user_name = $_SESSION['user_name'] ?? 'User';
$_user_role = $_SESSION['user_role'] ?? 'user';

// Detect if we're in a subfolder (e.g. admin/) and adjust asset paths
$_depth     = (strpos(__DIR__, DIRECTORY_SEPARATOR . 'admin') !== false) ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title) ?> – Lost & Found Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= $_depth ?>assets/css/style.css"/>
</head>
<body>

<!-- ── SIDEBAR ─────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="logo">Lost<span>&Found</span></div>
  <nav class="nav">
    <a href="<?= $_depth ?>dashboard.php"  class="<?= $active_nav === 'dashboard'  ? 'active' : '' ?>"><span>▪</span> Dashboard</a>
    <a href="<?= $_depth ?>browse.php"     class="<?= $active_nav === 'browse'     ? 'active' : '' ?>"><span>🔍</span> Browse Items</a>
    <a href="<?= $_depth ?>post-lost.php"  class="<?= $active_nav === 'post-lost'  ? 'active' : '' ?>"><span>📋</span> Post Lost Item</a>
    <a href="<?= $_depth ?>post-found.php" class="<?= $active_nav === 'post-found' ? 'active' : '' ?>"><span>📦</span> Post Found Item</a>
    <a href="<?= $_depth ?>claims.php"     class="<?= $active_nav === 'claims'     ? 'active' : '' ?>"><span>✅</span> My Claims</a>
    <a href="<?= $_depth ?>my-posts.php"   class="<?= $active_nav === 'my-posts'   ? 'active' : '' ?>"><span>🗂</span> My Posts</a>
    <?php if ($_user_role === 'admin'): ?>
    <a href="<?= $_depth ?>admin/index.php" class="admin-link"><span>⚙</span> Admin Panel</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($_user_name, 0, 2)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_user_name) ?></div>
        <div class="user-role"><?= ucfirst($_user_role) ?></div>
      </div>
    </div>
    <a href="<?= $_depth ?>logout.php" class="logout-link">← Log out</a>
  </div>
</aside>
<!-- ── END SIDEBAR ─────────────────────────────────────── -->
