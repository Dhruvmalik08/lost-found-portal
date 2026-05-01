<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// ── VALIDATE ID ───────────────────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse.php");
    exit();
}

$item_id = (int)$_GET['id'];

// ── FETCH ITEM + POSTER INFO ──────────────────────────────
$item_result = mysqli_query($conn, "
    SELECT items.*, users.name AS poster_name, users.email AS poster_email, users.phone AS poster_phone
    FROM items
    JOIN users ON items.user_id = users.id
    WHERE items.id = $item_id
");

if (mysqli_num_rows($item_result) === 0) {
    header("Location: browse.php");
    exit();
}

$item = mysqli_fetch_assoc($item_result);

// ── CHECK IF CURRENT USER ALREADY CLAIMED THIS ITEM ───────
$already_claimed = false;
$claim_check = mysqli_query($conn, "
    SELECT id FROM claims WHERE item_id = $item_id AND claimant_id = $user_id
");
if (mysqli_num_rows($claim_check) > 0) {
    $already_claimed = true;
}

// ── FETCH ALL CLAIMS (only visible to item poster) ────────
$claims = null;
if ($item['user_id'] == $user_id || $user_role === 'admin') {
    $claims = mysqli_query($conn, "
        SELECT claims.*, users.name AS claimant_name, users.email AS claimant_email, users.phone AS claimant_phone
        FROM claims
        JOIN users ON claims.claimant_id = users.id
        WHERE claims.item_id = $item_id
        ORDER BY claims.created_at DESC
    ");
}

$error   = "";
$success = "";

// ── HANDLE CLAIM SUBMISSION ───────────────────────────────
if (isset($_POST['submit_claim'])) {
    if ($item['user_id'] == $user_id) {
        $error = "You cannot claim your own item.";
    } elseif ($already_claimed) {
        $error = "You have already submitted a claim for this item.";
    } elseif ($item['status'] === 'reunited') {
        $error = "This item has already been reunited.";
    } else {
        $message = trim(mysqli_real_escape_string($conn, $_POST['message']));
        if (empty($message)) {
            $error = "Please write a message to support your claim.";
        } else {
            $sql = "INSERT INTO claims (item_id, claimant_id, message) VALUES ($item_id, $user_id, '$message')";
            if (mysqli_query($conn, $sql)) {
                // Optionally update item status to 'claimed'
                mysqli_query($conn, "UPDATE items SET status='claimed' WHERE id=$item_id AND status='open'");
                $success = "Your claim has been submitted! The poster will review it and contact you.";
                $already_claimed = true;
                // Refresh item data
                $item_result = mysqli_query($conn, "
                    SELECT items.*, users.name AS poster_name, users.email AS poster_email, users.phone AS poster_phone
                    FROM items JOIN users ON items.user_id = users.id WHERE items.id = $item_id
                ");
                $item = mysqli_fetch_assoc($item_result);
                // Refresh claims list
                if ($item['user_id'] == $user_id || $user_role === 'admin') {
                    $claims = mysqli_query($conn, "
                        SELECT claims.*, users.name AS claimant_name, users.email AS claimant_email, users.phone AS claimant_phone
                        FROM claims JOIN users ON claims.claimant_id = users.id
                        WHERE claims.item_id = $item_id ORDER BY claims.created_at DESC
                    ");
                }
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

// ── HANDLE CLAIM ACCEPT / REJECT (poster only) ────────────
if (isset($_POST['update_claim']) && $item['user_id'] == $user_id) {
    $claim_id     = (int)$_POST['claim_id'];
    $claim_action = $_POST['claim_action'];

    if (in_array($claim_action, ['accepted', 'rejected'])) {
        mysqli_query($conn, "UPDATE claims SET status='$claim_action' WHERE id=$claim_id AND item_id=$item_id");

        if ($claim_action === 'accepted') {
            mysqli_query($conn, "UPDATE items SET status='reunited' WHERE id=$item_id");
            $success = "Claim accepted! The item is now marked as reunited. 🎉";
        } else {
            $success = "Claim rejected.";
        }

        // Refresh
        $item_result = mysqli_query($conn, "
            SELECT items.*, users.name AS poster_name, users.email AS poster_email, users.phone AS poster_phone
            FROM items JOIN users ON items.user_id = users.id WHERE items.id = $item_id
        ");
        $item = mysqli_fetch_assoc($item_result);
        $claims = mysqli_query($conn, "
            SELECT claims.*, users.name AS claimant_name, users.email AS claimant_email, users.phone AS claimant_phone
            FROM claims JOIN users ON claims.claimant_id = users.id
            WHERE claims.item_id = $item_id ORDER BY claims.created_at DESC
        ");
    }
}

// ── HELPERS ───────────────────────────────────────────────
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'Just now';
    if ($diff < 3600)     return floor($diff / 60) . 'm ago';
    if ($diff < 86400)    return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000)  return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

$icons = [
    'Electronics' => '📱', 'Wallet' => '👜', 'Bag' => '🎒',
    'Keys' => '🔑', 'ID' => '🪪', 'Clothing' => '👕',
    'Jewellery' => '💍', 'Books' => '📚', 'Other' => '📦'
];
$icon       = $icons[$item['category']] ?? '📦';
$is_owner   = ($item['user_id'] == $user_id);
$is_lost    = ($item['type'] === 'lost');
$has_photo  = !empty($item['photo']) && file_exists('uploads/' . $item['photo']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($item['name']) ?> – Lost & Found Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
            --bg:       #0a0f1e;
      --surface:  #0f1729;
      --surface2: #162040;
      --border:   #1e2d4a;
      --accent:   #00c9a7;
      --accent2:  #3b9eff;
      --danger:   #e05252;
      --success:  #00c9a7;
      --text:     #e4eaf6;
      --muted:    #6b7fa8;
      --radius:   14px;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: 220px;
      min-height: 100vh;
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 28px 0;
      flex-shrink: 0;
      position: sticky;
      top: 0;
      height: 100vh;
    }
    .logo {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 18px;
      color: var(--accent);
      padding: 0 24px 24px;
      border-bottom: 1px solid var(--border);
    }
    .logo span { color: var(--text); }
    .nav { margin-top: 16px; flex: 1; }
    .nav a {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 11px 24px;
      font-size: 14px;
      color: var(--muted);
      text-decoration: none;
      border-left: 3px solid transparent;
      transition: all 0.15s;
    }
    .nav a:hover { color: var(--text); background: var(--surface2); }
    .nav a.active {
      color: var(--accent2);
      border-left-color: var(--accent2);
      background: rgba(59,158,255,0.07);
      font-weight: 500;
    }
    .sidebar-bottom {
      padding: 16px 24px 0;
      border-top: 1px solid var(--border);
    }
    .user-chip { display: flex; align-items: center; gap: 10px; }
    .avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: linear-gradient(135deg, #3b9eff, #00c9a7);
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .user-name { font-size: 13px; font-weight: 500; }
    .user-role { font-size: 11px; color: var(--muted); }
    .logout-link {
      display: block; margin-top: 12px;
      font-size: 12px; color: var(--danger);
      text-decoration: none; padding: 6px 0;
    }
    .logout-link:hover { text-decoration: underline; }

    /* ── MAIN ── */
    .main { flex: 1; padding: 32px 40px; max-width: 960px; overflow-y: auto; }

    /* ── BREADCRUMB ── */
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 24px;
    }
    .breadcrumb a { color: var(--muted); text-decoration: none; }
    .breadcrumb a:hover { color: var(--text); }
    .breadcrumb .sep { opacity: 0.4; }
    .breadcrumb .current { color: var(--text); }

    /* ── ALERTS ── */
    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 13.5px;
      margin-bottom: 24px;
      font-weight: 500;
    }
    .alert-error   { background: rgba(224,82,82,0.12);  color: var(--danger);  border: 1px solid rgba(224,82,82,0.25); }
    .alert-success { background: rgba(0,201,167,0.12); color: var(--success); border: 1px solid rgba(0,201,167,0.25); }

    /* ── TOP LAYOUT ── */
    .detail-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 24px;
      margin-bottom: 28px;
    }

    /* ── PHOTO ── */
    .photo-box {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      aspect-ratio: 4/3;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .photo-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .photo-placeholder {
      font-size: 72px;
      opacity: 0.4;
    }

    /* ── INFO PANEL ── */
    .info-panel {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .item-title-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
    }
    .item-title {
      font-family: 'Syne', sans-serif;
      font-size: 24px;
      font-weight: 800;
      line-height: 1.25;
    }

    /* badges */
    .badge {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 20px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .badge-lost    { background: rgba(224,82,82,0.15);  color: var(--danger);  }
    .badge-found   { background: rgba(0,201,167,0.15); color: var(--success); }
    .badge-open    { background: rgba(0,201,167,0.15); color: var(--accent);  }
    .badge-claimed { background: rgba(59,158,255,0.15); color: var(--accent2); }
    .badge-reunited{ background: rgba(0,201,167,0.15); color: var(--success); }

    /* meta list */
    .meta-list {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .meta-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      font-size: 13.5px;
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
    .meta-label { color: var(--muted); font-size: 12px; min-width: 80px; }
    .meta-value { color: var(--text); font-weight: 500; }

    /* description */
    .desc-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
    }
    .card-heading {
      font-family: 'Syne', sans-serif;
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: var(--muted);
      margin-bottom: 12px;
    }
    .desc-text {
      font-size: 14px;
      line-height: 1.8;
      color: var(--text);
    }
    .desc-empty { color: var(--muted); font-style: italic; font-size: 13.5px; }

    /* poster contact */
    .poster-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
    }
    .poster-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }
    .poster-avatar {
      width: 42px; height: 42px; border-radius: 50%;
      background: linear-gradient(135deg, #3b9eff, #00c9a7);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .poster-name { font-size: 15px; font-weight: 600; }
    .poster-since { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .contact-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: var(--muted);
      padding: 5px 0;
    }
    .contact-item a { color: var(--accent2); text-decoration: none; }
    .contact-item a:hover { text-decoration: underline; }

    /* ── SECTION DIVIDER ── */
    .section-divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 8px 0 24px;
    }

    /* ── CLAIM FORM ── */
    .claim-section {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
      margin-bottom: 28px;
    }
    .claim-title {
      font-family: 'Syne', sans-serif;
      font-size: 17px;
      font-weight: 700;
      margin-bottom: 6px;
    }
    .claim-sub { font-size: 13px; color: var(--muted); margin-bottom: 20px; }

    textarea {
      width: 100%;
      padding: 13px 16px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      resize: vertical;
      min-height: 110px;
      line-height: 1.7;
      transition: border-color 0.15s;
      margin-bottom: 14px;
    }
    textarea:focus { border-color: var(--accent2); }
    textarea::placeholder { color: var(--muted); }

    /* ── BUTTONS ── */
    .btn {
      padding: 10px 22px;
      border-radius: 8px;
      font-size: 13.5px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      cursor: pointer;
      border: none;
      text-decoration: none;
      display: inline-block;
      transition: all 0.15s;
    }
    .btn-primary { background: var(--accent);  color: #0a0f1e; }
    .btn-primary:hover { background: #33d4b7; }
    .btn-blue    { background: var(--accent2); color: #fff; }
    .btn-blue:hover { background: #62b3ff; }
    .btn-success { background: var(--success); color: #0a0f1e; }
    .btn-success:hover { background: #33d4b7; }
    .btn-danger  { background: var(--danger);  color: #fff; }
    .btn-danger:hover { background: #f06060; }
    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
    }
    .btn-outline:hover { background: var(--surface2); }
    .btn-sm { padding: 6px 14px; font-size: 12px; }

    /* ── ALREADY CLAIMED BOX ── */
    .claimed-box {
      background: rgba(59,158,255,0.08);
      border: 1px solid rgba(59,158,255,0.25);
      border-radius: var(--radius);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 28px;
    }
    .claimed-icon { font-size: 28px; flex-shrink: 0; }
    .claimed-text { font-size: 14px; color: var(--accent2); font-weight: 500; }
    .claimed-sub  { font-size: 12.5px; color: var(--muted); margin-top: 3px; }

    /* ── REUNITED BOX ── */
    .reunited-box {
      background: rgba(0,201,167,0.08);
      border: 1px solid rgba(0,201,167,0.25);
      border-radius: var(--radius);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 28px;
    }
    .reunited-icon { font-size: 28px; flex-shrink: 0; }
    .reunited-text { font-size: 14px; color: var(--success); font-weight: 500; }
    .reunited-sub  { font-size: 12.5px; color: var(--muted); margin-top: 3px; }

    /* ── CLAIMS TABLE (poster view) ── */
    .claims-section {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 28px;
    }
    .claims-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .claims-title {
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
    }
    .claim-count {
      font-size: 12px;
      background: rgba(59,158,255,0.15);
      color: var(--accent2);
      padding: 3px 10px;
      border-radius: 20px;
    }

    .claim-card {
      padding: 20px;
      border-top: 1px solid var(--border);
    }
    .claim-card:first-of-type { border-top: none; }

    .claim-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 10px;
      gap: 12px;
    }
    .claim-user {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .claim-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: linear-gradient(135deg, #3b9eff, #6b7fa8);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .claim-username { font-size: 14px; font-weight: 600; }
    .claim-time     { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .claim-status-badge {
      font-size: 11px; padding: 3px 9px; border-radius: 20px;
      font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;
      flex-shrink: 0;
    }
    .cs-pending  { background: rgba(0,201,167,0.15); color: var(--accent); }
    .cs-accepted { background: rgba(0,201,167,0.15); color: var(--success); }
    .cs-rejected { background: rgba(224,82,82,0.15);  color: var(--danger); }

    .claim-message {
      font-size: 13.5px;
      color: var(--text);
      line-height: 1.7;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 12px 14px;
      margin: 10px 0;
    }

    .claim-contact {
      font-size: 12px;
      color: var(--muted);
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }
    .claim-contact a { color: var(--accent2); text-decoration: none; }
    .claim-contact a:hover { text-decoration: underline; }

    .claim-actions { display: flex; gap: 8px; }

    .no-claims {
      padding: 32px;
      text-align: center;
      color: var(--muted);
      font-size: 13px;
    }
    .no-claims .nc-icon { font-size: 32px; margin-bottom: 10px; }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">Lost<span>&Found</span></div>
  <nav class="nav">
    <a href="dashboard.php"><span>▪</span> Dashboard</a>
    <a href="browse.php" class="active"><span>🔍</span> Browse Items</a>
    <a href="post-lost.php"><span>📋</span> Post Lost Item</a>
    <a href="post-found.php"><span>📦</span> Post Found Item</a>
    <a href="claims.php"><span>✅</span> My Claims</a>
    <a href="my-posts.php"><span>🗂</span> My Posts</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($user_name, 0, 2)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
        <div class="user-role"><?= ucfirst($user_role) ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-link">← Log out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <!-- BREADCRUMB -->
  <div class="breadcrumb">
    <a href="browse.php">Browse Items</a>
    <span class="sep">›</span>
    <span class="current"><?= htmlspecialchars($item['name']) ?></span>
  </div>

  <!-- ALERTS -->
  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

  <!-- DETAIL LAYOUT -->
  <div class="detail-layout">

    <!-- LEFT: Photo -->
    <div class="photo-box">
      <?php if ($has_photo): ?>
        <img src="uploads/<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"/>
      <?php else: ?>
        <div class="photo-placeholder"><?= $icon ?></div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: Info panel -->
    <div class="info-panel">

      <!-- Title + badges -->
      <div>
        <div class="item-title-row">
          <div class="item-title"><?= htmlspecialchars($item['name']) ?></div>
          <span class="badge badge-<?= $item['type'] ?>"><?= $item['type'] ?></span>
        </div>
        <div style="margin-top:8px;">
          <span class="badge badge-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
        </div>
      </div>

      <!-- Meta list -->
      <div class="meta-list">
        <div class="meta-row">
          <span class="meta-icon">📁</span>
          <span class="meta-label">Category</span>
          <span class="meta-value"><?= $icon ?> <?= htmlspecialchars($item['category']) ?></span>
        </div>
        <div class="meta-row">
          <span class="meta-icon">📍</span>
          <span class="meta-label"><?= $is_lost ? 'Last Seen' : 'Found At' ?></span>
          <span class="meta-value"><?= htmlspecialchars($item['location']) ?></span>
        </div>
        <div class="meta-row">
          <span class="meta-icon">📅</span>
          <span class="meta-label">Date</span>
          <span class="meta-value"><?= date('d M Y', strtotime($item['date_occurred'])) ?></span>
        </div>
        <div class="meta-row">
          <span class="meta-icon">🕐</span>
          <span class="meta-label">Posted</span>
          <span class="meta-value"><?= time_ago($item['created_at']) ?></span>
        </div>
      </div>

      <!-- Poster info (shown to non-owners for found items, or always) -->
      <div class="poster-card">
        <div class="card-heading">Posted By</div>
        <div class="poster-row">
          <div class="poster-avatar"><?= strtoupper(substr($item['poster_name'], 0, 2)) ?></div>
          <div>
            <div class="poster-name"><?= htmlspecialchars($item['poster_name']) ?></div>
            <div class="poster-since">Item #<?= $item['id'] ?></div>
          </div>
        </div>
        <?php if ($is_owner || $item['status'] === 'reunited'): ?>
          <!-- Full contact always visible to owner or after reunion -->
          <div class="contact-item">📧 <a href="mailto:<?= htmlspecialchars($item['poster_email']) ?>"><?= htmlspecialchars($item['poster_email']) ?></a></div>
          <?php if (!empty($item['poster_phone'])): ?>
          <div class="contact-item">📞 <a href="tel:<?= htmlspecialchars($item['poster_phone']) ?>"><?= htmlspecialchars($item['poster_phone']) ?></a></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="contact-item" style="color:var(--muted);font-size:12.5px;">
            📧 Contact visible after your claim is accepted
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- DESCRIPTION -->
  <div class="desc-card" style="margin-bottom:28px;">
    <div class="card-heading">Description</div>
    <?php if (!empty($item['description'])): ?>
      <div class="desc-text"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
    <?php else: ?>
      <div class="desc-empty">No description provided.</div>
    <?php endif; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- SECTION: CLAIM — shown to non-owners only              -->
  <!-- ═══════════════════════════════════════════════════════ -->

  <?php if (!$is_owner): ?>

    <?php if ($item['status'] === 'reunited'): ?>
      <!-- Item already reunited -->
      <div class="reunited-box">
        <div class="reunited-icon">🎉</div>
        <div>
          <div class="reunited-text">This item has been reunited with its owner!</div>
          <div class="reunited-sub">No further claims are being accepted.</div>
        </div>
      </div>

    <?php elseif ($already_claimed): ?>
      <!-- Already submitted a claim -->
      <div class="claimed-box">
        <div class="claimed-icon">✅</div>
        <div>
          <div class="claimed-text">You've already submitted a claim for this item.</div>
          <div class="claimed-sub">The poster will review your claim and contact you if accepted. Check <a href="claims.php" style="color:var(--accent2);">My Claims</a> for status updates.</div>
        </div>
      </div>

    <?php else: ?>
      <!-- Claim form -->
      <div class="claim-section">
        <div class="claim-title">
          <?= $is_lost ? '🙋 I Know About This Item' : '🙋 This Is Mine' ?>
        </div>
        <div class="claim-sub">
          <?= $is_lost
            ? 'If you found this item or know its whereabouts, submit a claim and describe what you know.'
            : 'If this is your lost item, submit a claim. Describe it in detail so the poster can verify.' ?>
        </div>
        <form method="POST" action="">
          <textarea name="message" placeholder="Describe how you know this is yours — colour, brand, contents, where you lost it, etc. Be as specific as possible." required></textarea>
          <button type="submit" name="submit_claim" class="btn btn-blue">Submit Claim</button>
        </form>
      </div>

    <?php endif; ?>

  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════ -->
  <!-- SECTION: CLAIMS LIST — shown to owner / admin          -->
  <!-- ═══════════════════════════════════════════════════════ -->

  <?php if ($claims !== null): ?>
    <?php $claim_count = mysqli_num_rows($claims); ?>
    <div class="claims-section">
      <div class="claims-header">
        <div class="claims-title">Claims Received</div>
        <span class="claim-count"><?= $claim_count ?> claim<?= $claim_count !== 1 ? 's' : '' ?></span>
      </div>

      <?php if ($claim_count === 0): ?>
        <div class="no-claims">
          <div class="nc-icon">📭</div>
          No claims have been submitted yet.
        </div>

      <?php else: ?>
        <?php while ($claim = mysqli_fetch_assoc($claims)): ?>
        <div class="claim-card">

          <div class="claim-top">
            <div class="claim-user">
              <div class="claim-avatar"><?= strtoupper(substr($claim['claimant_name'], 0, 2)) ?></div>
              <div>
                <div class="claim-username"><?= htmlspecialchars($claim['claimant_name']) ?></div>
                <div class="claim-time"><?= time_ago($claim['created_at']) ?></div>
              </div>
            </div>
            <span class="claim-status-badge cs-<?= $claim['status'] ?>"><?= ucfirst($claim['status']) ?></span>
          </div>

          <div class="claim-message"><?= nl2br(htmlspecialchars($claim['message'])) ?></div>

          <!-- Contact details of claimant -->
          <div class="claim-contact">
            <span>📧 <a href="mailto:<?= htmlspecialchars($claim['claimant_email']) ?>"><?= htmlspecialchars($claim['claimant_email']) ?></a></span>
            <?php if (!empty($claim['claimant_phone'])): ?>
            <span>📞 <a href="tel:<?= htmlspecialchars($claim['claimant_phone']) ?>"><?= htmlspecialchars($claim['claimant_phone']) ?></a></span>
            <?php endif; ?>
          </div>

          <!-- Accept / Reject buttons (only if claim is pending and item not yet reunited) -->
          <?php if ($claim['status'] === 'pending' && $item['status'] !== 'reunited' && $is_owner): ?>
          <div class="claim-actions">
            <form method="POST" action="" style="display:inline;">
              <input type="hidden" name="claim_id"     value="<?= $claim['id'] ?>"/>
              <input type="hidden" name="claim_action" value="accepted"/>
              <button type="submit" name="update_claim" class="btn btn-success btn-sm"
                      onclick="return confirm('Accept this claim? The item will be marked as Reunited.')">
                ✓ Accept
              </button>
            </form>
            <form method="POST" action="" style="display:inline;">
              <input type="hidden" name="claim_id"     value="<?= $claim['id'] ?>"/>
              <input type="hidden" name="claim_action" value="rejected"/>
              <button type="submit" name="update_claim" class="btn btn-danger btn-sm"
                      onclick="return confirm('Reject this claim?')">
                ✗ Reject
              </button>
            </form>
          </div>
          <?php endif; ?>

        </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- BACK BUTTON -->
  <a href="browse.php" class="btn btn-outline">← Back to Browse</a>

</main>

</body>
</html>
