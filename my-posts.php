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

$error   = "";
$success = "";

// ── DELETE ────────────────────────────────────────────────
if (isset($_POST['delete_item'])) {
    $del_id = (int)$_POST['item_id'];

    // Verify ownership before deleting
    $check = mysqli_query($conn, "SELECT photo FROM items WHERE id=$del_id AND user_id=$user_id");
    if (mysqli_num_rows($check) === 1) {
        $row = mysqli_fetch_assoc($check);
        // Delete photo file if it exists
        if (!empty($row['photo']) && file_exists('uploads/' . $row['photo'])) {
            unlink('uploads/' . $row['photo']);
        }
        mysqli_query($conn, "DELETE FROM claims WHERE item_id=$del_id");
        mysqli_query($conn, "DELETE FROM items  WHERE id=$del_id AND user_id=$user_id");
        $success = "Item deleted successfully.";
    } else {
        $error = "Item not found or you don't have permission to delete it.";
    }
}

// ── MARK AS REUNITED ──────────────────────────────────────
if (isset($_POST['mark_reunited'])) {
    $r_id = (int)$_POST['item_id'];
    $check = mysqli_query($conn, "SELECT id FROM items WHERE id=$r_id AND user_id=$user_id");
    if (mysqli_num_rows($check) === 1) {
        mysqli_query($conn, "UPDATE items SET status='reunited' WHERE id=$r_id AND user_id=$user_id");
        $success = "Item marked as reunited! 🎉";
    } else {
        $error = "Item not found or permission denied.";
    }
}

// ── REOPEN ITEM ───────────────────────────────────────────
if (isset($_POST['reopen_item'])) {
    $ro_id = (int)$_POST['item_id'];
    $check = mysqli_query($conn, "SELECT id FROM items WHERE id=$ro_id AND user_id=$user_id");
    if (mysqli_num_rows($check) === 1) {
        mysqli_query($conn, "UPDATE items SET status='open' WHERE id=$ro_id AND user_id=$user_id");
        $success = "Item reopened and is now active again.";
    }
}

// ── EDIT / UPDATE ─────────────────────────────────────────
if (isset($_POST['update_item'])) {
    $edit_id     = (int)$_POST['item_id'];
    $name        = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $description = trim(mysqli_real_escape_string($conn, $_POST['description']));
    $category    = trim(mysqli_real_escape_string($conn, $_POST['category']));
    $location    = trim(mysqli_real_escape_string($conn, $_POST['location']));
    $date        = $_POST['date_occurred'];

    // Verify ownership
    $check = mysqli_query($conn, "SELECT photo FROM items WHERE id=$edit_id AND user_id=$user_id");
    if (mysqli_num_rows($check) === 0) {
        $error = "Item not found or permission denied.";
    } elseif (empty($name) || empty($category) || empty($location) || empty($date)) {
        $error = "Please fill in all required fields.";
    } else {
        $old = mysqli_fetch_assoc($check);
        $photo_name = $old['photo']; // keep existing by default

        // Handle new photo upload
        if (!empty($_FILES['photo']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, PNG, GIF, WEBP images are allowed.";
            } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $error = "Image must be under 5MB.";
            } else {
                // Delete old photo
                if (!empty($old['photo']) && file_exists('uploads/' . $old['photo'])) {
                    unlink('uploads/' . $old['photo']);
                }
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                $photo_name = uniqid('item_', true) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $photo_name);
            }
        }

        // Remove photo if checkbox ticked
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            if (!empty($old['photo']) && file_exists('uploads/' . $old['photo'])) {
                unlink('uploads/' . $old['photo']);
            }
            $photo_name = '';
        }

        if (empty($error)) {
            $sql = "UPDATE items SET
                        name='$name',
                        description='$description',
                        category='$category',
                        location='$location',
                        date_occurred='$date',
                        photo='$photo_name'
                    WHERE id=$edit_id AND user_id=$user_id";
            if (mysqli_query($conn, $sql)) {
                $success = "Item updated successfully.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

// ── FETCH USER'S ITEMS ────────────────────────────────────
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_extra = '';
if ($filter === 'lost')     $where_extra = "AND type='lost'";
if ($filter === 'found')    $where_extra = "AND type='found'";
if ($filter === 'open')     $where_extra = "AND status='open'";
if ($filter === 'reunited') $where_extra = "AND status='reunited'";

$items_result = mysqli_query($conn, "
    SELECT items.*,
           (SELECT COUNT(*) FROM claims WHERE claims.item_id = items.id) AS claim_count,
           (SELECT COUNT(*) FROM claims WHERE claims.item_id = items.id AND claims.status = 'pending') AS pending_claims
    FROM items
    WHERE items.user_id = $user_id $where_extra
    ORDER BY items.created_at DESC
");

$total_items = mysqli_num_rows($items_result);

// Stats for mini-header
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(type='lost') AS lost_count,
        SUM(type='found') AS found_count,
        SUM(status='open') AS open_count,
        SUM(status='reunited') AS reunited_count
    FROM items WHERE user_id=$user_id
"));

// Category icons
$icons = [
    'Electronics' => '📱', 'Wallet' => '👜', 'Bag' => '🎒',
    'Keys' => '🔑', 'ID' => '🪪', 'Clothing' => '👕',
    'Jewellery' => '💍', 'Books' => '📚', 'Other' => '📦'
];

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

// Which item is being edited right now?
$editing_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Posts – Lost & Found Portal</title>
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
      color: var(--accent);
      border-left-color: var(--accent);
      background: rgba(0,201,167,0.07);
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
    .main { flex: 1; padding: 32px 40px; overflow-y: auto; }

    /* ── TOPBAR ── */
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 24px;
      gap: 16px;
      flex-wrap: wrap;
    }
    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 22px; font-weight: 700;
    }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }

    /* ── ALERTS ── */
    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 13.5px;
      margin-bottom: 22px;
      font-weight: 500;
    }
    .alert-error   { background: rgba(224,82,82,0.12);  color: var(--danger);  border: 1px solid rgba(224,82,82,0.25); }
    .alert-success { background: rgba(0,201,167,0.12); color: var(--success); border: 1px solid rgba(0,201,167,0.25); }

    /* ── MINI STATS ── */
    .mini-stats {
      display: flex;
      gap: 12px;
      margin-bottom: 22px;
      flex-wrap: wrap;
    }
    .mini-stat {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      text-decoration: none;
      transition: border-color 0.15s, background 0.15s;
    }
    .mini-stat:hover { border-color: var(--accent); }
    .mini-stat.active-filter { border-color: var(--accent); background: rgba(0,201,167,0.06); }
    .ms-val {
      font-family: 'Syne', sans-serif;
      font-size: 20px;
      font-weight: 700;
      color: var(--text);
    }
    .ms-lbl { font-size: 12px; color: var(--muted); }

    /* ── ITEMS LIST ── */
    .items-list { display: flex; flex-direction: column; gap: 16px; }

    /* ── ITEM ROW ── */
    .item-row {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: border-color 0.15s;
    }
    .item-row:hover { border-color: #353a50; }
    .item-row.editing { border-color: var(--accent2); }

    /* row summary */
    .row-summary {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px 20px;
    }

    .row-thumb {
      width: 56px; height: 56px;
      border-radius: 10px;
      background: var(--surface2);
      border: 1px solid var(--border);
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px;
      flex-shrink: 0;
    }
    .row-thumb img { width: 100%; height: 100%; object-fit: cover; }

    .row-info { flex: 1; min-width: 0; }
    .row-name {
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .row-meta {
      display: flex;
      gap: 14px;
      margin-top: 5px;
      flex-wrap: wrap;
    }
    .row-meta span { font-size: 12px; color: var(--muted); }

    /* badges */
    .badge {
      font-size: 10px;
      padding: 3px 9px;
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

    /* claim pill */
    .claim-pill {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 20px;
      background: rgba(59,158,255,0.12);
      color: var(--accent2);
      white-space: nowrap;
      flex-shrink: 0;
      text-decoration: none;
      font-weight: 500;
    }
    .claim-pill.has-pending {
      background: rgba(0,201,167,0.15);
      color: var(--accent);
    }
    .claim-pill:hover { opacity: 0.8; }

    /* row action buttons */
    .row-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

    /* ── BUTTONS ── */
    .btn {
      padding: 7px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      cursor: pointer;
      border: none;
      text-decoration: none;
      display: inline-block;
      transition: all 0.15s;
      white-space: nowrap;
    }
    .btn-sm { padding: 5px 12px; font-size: 12px; }
    .btn-primary { background: var(--accent);  color: #0a0f1e; }
    .btn-primary:hover { background: #33d4b7; }
    .btn-blue    { background: var(--accent2); color: #fff; }
    .btn-blue:hover { background: #62b3ff; }
    .btn-success { background: var(--success); color: #0a0f1e; }
    .btn-success:hover { background: #33d4b7; }
    .btn-danger  { background: rgba(224,82,82,0.15); color: var(--danger); border: 1px solid rgba(224,82,82,0.2); }
    .btn-danger:hover { background: rgba(224,82,82,0.28); }
    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .btn-outline:hover { color: var(--text); background: var(--surface2); }
    .btn-ghost { background: transparent; color: var(--muted); padding: 7px 10px; }
    .btn-ghost:hover { color: var(--text); }

    /* ── EDIT PANEL (inline, slides open) ── */
    .edit-panel {
      display: none;
      border-top: 1px solid var(--border);
      padding: 24px 20px;
      background: var(--surface2);
    }
    .edit-panel.open { display: block; }

    .edit-heading {
      font-family: 'Syne', sans-serif;
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 18px;
      color: var(--accent2);
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }

    label {
      font-size: 11px;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .required { color: var(--danger); }

    input[type="text"],
    input[type="date"],
    select,
    textarea {
      width: 100%;
      padding: 10px 13px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 13.5px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      transition: border-color 0.15s;
      appearance: none;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--accent2); }
    input::placeholder, textarea::placeholder { color: var(--muted); }
    textarea { resize: vertical; min-height: 88px; line-height: 1.6; }
    select option { background: var(--surface); }

    /* photo edit area */
    .photo-edit-row {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
    }
    .current-thumb {
      width: 64px; height: 64px;
      border-radius: 8px;
      object-fit: cover;
      border: 1px solid var(--border);
    }
    .current-thumb-placeholder {
      width: 64px; height: 64px;
      border-radius: 8px;
      background: var(--surface);
      border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: 26px;
    }
    .photo-controls { display: flex; flex-direction: column; gap: 8px; }
    .remove-photo-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: var(--muted);
      cursor: pointer;
    }
    .remove-photo-row input[type="checkbox"] { accent-color: var(--danger); }

    input[type="file"] {
      font-size: 12px;
      color: var(--muted);
      padding: 6px 0;
      background: transparent;
      border: none;
    }

    .edit-actions {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align: center;
      padding: 64px 20px;
      color: var(--muted);
    }
    .empty-icon  { font-size: 52px; margin-bottom: 16px; }
    .empty-title {
      font-family: 'Syne', sans-serif;
      font-size: 18px; font-weight: 700;
      color: var(--text); margin-bottom: 8px;
    }
    .empty-sub { font-size: 13px; margin-bottom: 22px; }

    /* ── DIVIDER ── */
    hr.divider { border: none; border-top: 1px solid var(--border); margin: 4px 0 16px; }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">Lost<span>&Found</span></div>
  <nav class="nav">
    <a href="dashboard.php"><span>▪</span> Dashboard</a>
    <a href="browse.php"><span>🔍</span> Browse Items</a>
    <a href="post-lost.php"><span>📋</span> Post Lost Item</a>
    <a href="post-found.php"><span>📦</span> Post Found Item</a>
    <a href="claims.php"><span>✅</span> My Claims</a>
    <a href="my-posts.php" class="active"><span>🗂</span> My Posts</a>
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

  <div class="topbar">
    <div>
      <div class="page-title">My Posts</div>
      <div class="page-sub">Manage all the items you've reported</div>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="post-lost.php"  class="btn btn-outline">+ Post Lost</a>
      <a href="post-found.php" class="btn btn-primary">+ Post Found</a>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

  <!-- MINI STATS / FILTER TABS -->
  <div class="mini-stats">
    <?php
    $filters = [
      'all'      => ['label' => 'All Items',  'val' => $stats['total']       ?? 0],
      'lost'     => ['label' => 'Lost',       'val' => $stats['lost_count']  ?? 0],
      'found'    => ['label' => 'Found',      'val' => $stats['found_count'] ?? 0],
      'open'     => ['label' => 'Open',       'val' => $stats['open_count']  ?? 0],
      'reunited' => ['label' => 'Reunited',   'val' => $stats['reunited_count'] ?? 0],
    ];
    foreach ($filters as $key => $f):
      $active = ($filter === $key) ? 'active-filter' : '';
    ?>
    <a href="my-posts.php?filter=<?= $key ?>" class="mini-stat <?= $active ?>">
      <div class="ms-val"><?= $f['val'] ?></div>
      <div class="ms-lbl"><?= $f['label'] ?></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ITEMS LIST -->
  <?php if ($total_items === 0): ?>
    <div class="empty-state">
      <div class="empty-icon">🗂️</div>
      <div class="empty-title">
        <?= $filter === 'all' ? "You haven't posted anything yet" : "No $filter items found" ?>
      </div>
      <div class="empty-sub">
        <?= $filter === 'all'
          ? "Start by reporting a lost or found item so the community can help."
          : "Try switching the filter above." ?>
      </div>
      <?php if ($filter === 'all'): ?>
        <a href="post-lost.php" class="btn btn-outline" style="margin-right:8px;">Post Lost Item</a>
        <a href="post-found.php" class="btn btn-primary">Post Found Item</a>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="items-list">

      <?php while ($item = mysqli_fetch_assoc($items_result)):
        $icon        = $icons[$item['category']] ?? '📦';
        $has_photo   = !empty($item['photo']) && file_exists('uploads/' . $item['photo']);
        $is_editing  = ($editing_id === (int)$item['id']);
        $pending     = (int)$item['pending_claims'];
        $total_cl    = (int)$item['claim_count'];
      ?>

      <div class="item-row <?= $is_editing ? 'editing' : '' ?>" id="row-<?= $item['id'] ?>">

        <!-- ── ROW SUMMARY ── -->
        <div class="row-summary">

          <!-- Thumbnail -->
          <div class="row-thumb">
            <?php if ($has_photo): ?>
              <img src="uploads/<?= htmlspecialchars($item['photo']) ?>" alt=""/>
            <?php else: ?>
              <?= $icon ?>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="row-info">
            <div class="row-name"><?= htmlspecialchars($item['name']) ?></div>
            <div class="row-meta">
              <span>📍 <?= htmlspecialchars($item['location']) ?></span>
              <span>📅 <?= date('d M Y', strtotime($item['date_occurred'])) ?></span>
              <span>🕐 <?= time_ago($item['created_at']) ?></span>
            </div>
          </div>

          <!-- Type badge -->
          <span class="badge badge-<?= $item['type'] ?>"><?= $item['type'] ?></span>

          <!-- Status badge -->
          <span class="badge badge-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>

          <!-- Claims pill -->
          <?php if ($total_cl > 0): ?>
            <a href="item-detail.php?id=<?= $item['id'] ?>"
               class="claim-pill <?= $pending > 0 ? 'has-pending' : '' ?>"
               title="<?= $pending ?> pending">
              <?= $pending > 0 ? "⚠ $pending pending" : "✓ $total_cl claim" . ($total_cl > 1 ? 's' : '') ?>
            </a>
          <?php endif; ?>

          <!-- Action buttons -->
          <div class="row-actions">
            <!-- View -->
            <a href="item-detail.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">View</a>

            <!-- Edit toggle -->
            <?php if ($item['status'] !== 'reunited'): ?>
              <a href="my-posts.php?edit=<?= $is_editing ? 0 : $item['id'] ?><?= $filter !== 'all' ? '&filter='.$filter : '' ?>"
                 class="btn btn-sm <?= $is_editing ? 'btn-blue' : 'btn-outline' ?>">
                <?= $is_editing ? '✕ Cancel' : '✏ Edit' ?>
              </a>
            <?php endif; ?>

            <!-- Mark reunited / Reopen -->
            <?php if ($item['status'] === 'open' || $item['status'] === 'claimed'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>
                <button type="submit" name="mark_reunited" class="btn btn-success btn-sm"
                        onclick="return confirm('Mark this item as reunited? This means it has been returned to its owner.')">
                  ✓ Reunited
                </button>
              </form>
            <?php elseif ($item['status'] === 'reunited'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>
                <button type="submit" name="reopen_item" class="btn btn-outline btn-sm"
                        onclick="return confirm('Reopen this item?')">
                  ↺ Reopen
                </button>
              </form>
            <?php endif; ?>

            <!-- Delete -->
            <form method="POST" style="display:inline;">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>
              <button type="submit" name="delete_item" class="btn btn-danger btn-sm"
                      onclick="return confirm('Delete this item? This cannot be undone and will also remove all claims.')">
                🗑
              </button>
            </form>
          </div>

        </div><!-- end .row-summary -->

        <!-- ── EDIT PANEL ── -->
        <div class="edit-panel <?= $is_editing ? 'open' : '' ?>">
          <div class="edit-heading">✏ Editing: <?= htmlspecialchars($item['name']) ?></div>

          <form method="POST" action="my-posts.php<?= $filter !== 'all' ? '?filter='.$filter : '' ?>" enctype="multipart/form-data">
            <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>

            <div class="form-grid">

              <!-- Name -->
              <div class="form-group">
                <label>Item Name <span class="required">*</span></label>
                <input type="text" name="name" required
                       value="<?= htmlspecialchars($item['name']) ?>"/>
              </div>

              <!-- Category -->
              <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" required>
                  <?php foreach (['Electronics','Wallet','Bag','Keys','ID','Clothing','Jewellery','Books','Other'] as $cat): ?>
                    <option value="<?= $cat ?>" <?= $item['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Location -->
              <div class="form-group">
                <label>Location <span class="required">*</span></label>
                <input type="text" name="location" required
                       value="<?= htmlspecialchars($item['location']) ?>"/>
              </div>

              <!-- Date -->
              <div class="form-group">
                <label>Date <span class="required">*</span></label>
                <input type="date" name="date_occurred"
                       max="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($item['date_occurred']) ?>"/>
              </div>

              <!-- Description -->
              <div class="form-group full">
                <label>Description</label>
                <textarea name="description" placeholder="Describe the item..."><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
              </div>

              <!-- Photo -->
              <div class="form-group full">
                <label>Photo</label>
                <div class="photo-edit-row">
                  <?php if ($has_photo): ?>
                    <img src="uploads/<?= htmlspecialchars($item['photo']) ?>" class="current-thumb" alt="Current photo"/>
                  <?php else: ?>
                    <div class="current-thumb-placeholder"><?= $icon ?></div>
                  <?php endif; ?>
                  <div class="photo-controls">
                    <input type="file" name="photo" accept="image/*"/>
                    <?php if ($has_photo): ?>
                      <label class="remove-photo-row">
                        <input type="checkbox" name="remove_photo" value="1"/>
                        Remove current photo
                      </label>
                    <?php endif; ?>
                    <span style="font-size:11.5px;color:var(--muted);">JPG, PNG, WEBP · Max 5MB</span>
                  </div>
                </div>
              </div>

            </div><!-- end .form-grid -->

            <div class="edit-actions">
              <button type="submit" name="update_item" class="btn btn-blue">Save Changes</button>
              <a href="my-posts.php<?= $filter !== 'all' ? '?filter='.$filter : '' ?>" class="btn btn-outline">Cancel</a>
            </div>
          </form>
        </div><!-- end .edit-panel -->

      </div><!-- end .item-row -->

      <?php endwhile; ?>
    </div><!-- end .items-list -->
  <?php endif; ?>

</main>

</body>
</html>
