<?php
session_start();
require 'includes/db.php';

// Block non-logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── STAT COUNTS ──────────────────────────────────────────
$total_lost    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE type='lost'"))['c'];
$total_found   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE type='found'"))['c'];
$total_reunited= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status='reunited'"))['c'];
$total_claims  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM claims WHERE status='pending'"))['c'];

// ── RECENT ITEMS (latest 6) ───────────────────────────────
$recent = mysqli_query($conn, "
    SELECT items.*, users.name AS poster_name
    FROM items
    JOIN users ON items.user_id = users.id
    ORDER BY items.created_at DESC
    LIMIT 6
");

// ── RECENT ACTIVITY (latest 7 claims + posts combined) ───
$activity = mysqli_query($conn, "
    SELECT 'claim' AS action_type, users.name, items.name AS item_name, claims.created_at
    FROM claims
    JOIN users ON claims.claimant_id = users.id
    JOIN items ON claims.item_id = items.id
    UNION ALL
    SELECT 'post' AS action_type, users.name, items.name AS item_name, items.created_at
    FROM items
    JOIN users ON items.user_id = users.id
    ORDER BY created_at DESC
    LIMIT 7
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard – Lost & Found Portal</title>
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
    .nav-icon { font-size: 15px; width: 18px; text-align: center; }

    .sidebar-bottom {
      padding: 16px 24px 0;
      border-top: 1px solid var(--border);
    }
    .user-chip { display: flex; align-items: center; gap: 10px; }
    .avatar {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b9eff, #00c9a7);
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .user-name { font-size: 13px; font-weight: 500; }
    .user-role { font-size: 11px; color: var(--muted); }

    .logout-link {
      display: block;
      margin-top: 12px;
      font-size: 12px;
      color: var(--danger);
      text-decoration: none;
      padding: 6px 0;
    }
    .logout-link:hover { text-decoration: underline; }
    .nav a.admin-link { color: #9b6cf7; border-top: 1px solid var(--border); padding-top: 14px; margin-top: 4px; }
    .nav a.admin-link:hover { background: rgba(155,108,247,0.07); color: #b98cf9; }

    /* ── MAIN ── */
    .main { flex: 1; padding: 32px 36px; overflow-y: auto; }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
    }
    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 22px;
      font-weight: 700;
    }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }

    .btn {
      padding: 9px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      cursor: pointer;
      border: none;
      text-decoration: none;
      display: inline-block;
      transition: all 0.15s;
    }
    .btn-primary { background: var(--accent); color: #0a0f1e; }
    .btn-primary:hover { background: #33d4b7; }
    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
    }
    .btn-outline:hover { background: var(--surface2); }

    /* ── STAT CARDS ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: linear-gradient(160deg, #1a2a4a 0%, #132038 100%);
      border: 1px solid #2a3f64;
      border-top: 2px solid #2e4a78;
      border-radius: var(--radius);
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0,8,30,0.4);
      transition: border-color 0.2s, transform 0.2s;
    }
    .stat-card:hover {
      border-color: rgba(0,201,167,0.35);
      transform: translateY(-2px);
    }
    .stat-label {
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.6px;
      margin-bottom: 10px;
    }
    .stat-value {
      font-family: 'Syne', sans-serif;
      font-size: 32px;
      font-weight: 700;
      line-height: 1;
    }
    .stat-badge {
      display: inline-block;
      margin-top: 10px;
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      font-weight: 500;
    }
    .badge-red    { background: rgba(224,82,82,0.15);   color: var(--danger);  }
    .badge-green  { background: rgba(0,201,167,0.15);  color: var(--success); }
    .badge-amber  { background: rgba(0,201,167,0.15);  color: var(--accent);  }
    .badge-blue   { background: rgba(59,158,255,0.15);  color: var(--accent2); }

    /* ── BOTTOM GRID ── */
    .bottom-grid {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 20px;
    }

    .card {
      background: linear-gradient(160deg, #1a2a4a 0%, #132038 100%);
      border: 1px solid #2a3f64;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,8,30,0.4);
    }
    .card-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 14px;
      font-weight: 700;
    }
    .view-all {
      font-size: 12px;
      color: var(--accent2);
      text-decoration: none;
    }
    .view-all:hover { text-decoration: underline; }

    /* ── ITEMS TABLE ── */
    .item-table { width: 100%; border-collapse: collapse; }
    .item-table th {
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 10px 20px;
      text-align: left;
      background: rgba(255,255,255,0.02);
    }
    .item-table td {
      padding: 12px 20px;
      font-size: 13.5px;
      border-top: 1px solid var(--border);
      vertical-align: middle;
    }
    .item-table tr:hover td { background: var(--surface2); }

    .item-info { display: flex; align-items: center; gap: 11px; }
    .item-icon {
      width: 36px; height: 36px;
      border-radius: 8px;
      background: var(--surface2);
      display: flex; align-items: center; justify-content: center;
      font-size: 17px; flex-shrink: 0;
    }
    .item-name  { font-weight: 500; font-size: 13.5px; }
    .item-loc   { font-size: 11.5px; color: var(--muted); margin-top: 2px; }

    .status-pill {
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      font-weight: 500;
      display: inline-block;
    }
    .s-lost     { background: rgba(224,82,82,0.15);   color: var(--danger);  }
    .s-found    { background: rgba(0,201,167,0.15);  color: var(--success); }
    .s-reunited { background: rgba(0,201,167,0.15);  color: var(--accent);  }
    .s-claimed  { background: rgba(59,158,255,0.15);  color: var(--accent2); }

    .cat-pill {
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      background: rgba(59,158,255,0.1);
      color: var(--accent2);
    }

    .empty-row td {
      text-align: center;
      color: var(--muted);
      font-size: 13px;
      padding: 32px;
    }

    /* ── ACTIVITY FEED ── */
    .activity-item {
      display: flex;
      align-items: flex-start;
      gap: 11px;
      padding: 13px 20px;
      border-top: 1px solid var(--border);
      font-size: 13px;
    }
    .act-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      margin-top: 5px;
      flex-shrink: 0;
    }
    .dot-green { background: var(--success); }
    .dot-blue  { background: var(--accent2); }
    .act-text  { color: var(--text); line-height: 1.5; }
    .act-text strong { font-weight: 500; }
    .act-time  { font-size: 11px; color: var(--muted); margin-top: 2px; }

    .no-activity {
      padding: 32px 20px;
      text-align: center;
      color: var(--muted);
      font-size: 13px;
    }

    /* ── SEARCH BAR ── */
    .search-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 14px;
      width: 210px;
    }
    .search-wrap input {
      background: transparent;
      border: none;
      outline: none;
      font-size: 13px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      width: 100%;
    }
    .search-wrap input::placeholder { color: var(--muted); }

    /* ── CATEGORY ICONS MAP ── */
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="logo">Lost<span>&Found</span></div>
  <nav class="nav">
    <a href="dashboard.php" class="active"><span class="nav-icon">▪</span> Dashboard</a>
    <a href="browse.php"><span class="nav-icon">🔍</span> Browse Items</a>
    <a href="post-lost.php"><span class="nav-icon">📋</span> Post Lost Item</a>
    <a href="post-found.php"><span class="nav-icon">📦</span> Post Found Item</a>
    <a href="claims.php"><span class="nav-icon">✅</span> My Claims</a>
    <a href="my-posts.php"><span class="nav-icon">🗂</span> My Posts</a>
    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <a href="admin/index.php" class="admin-link"><span class="nav-icon">⚙</span> Admin Panel</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($user_name, 0, 2)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
        <div class="user-role"><?= ucfirst($_SESSION['user_role']) ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-link">← Log out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Welcome back, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> 👋</div>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <div class="search-wrap">
        <span style="color:var(--muted);font-size:14px;">🔍</span>
        <input id="searchInput" placeholder="Search items..." oninput="filterTable()" />
      </div>
      <a href="post-lost.php" class="btn btn-outline">+ Post Lost</a>
      <a href="post-found.php" class="btn btn-primary">+ Post Found</a>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Lost Items</div>
      <div class="stat-value"><?= $total_lost ?></div>
      <span class="stat-badge badge-red">Active reports</span>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Found Items</div>
      <div class="stat-value"><?= $total_found ?></div>
      <span class="stat-badge badge-green">Awaiting claim</span>
    </div>
    <div class="stat-card">
      <div class="stat-label">Reunited</div>
      <div class="stat-value"><?= $total_reunited ?></div>
      <span class="stat-badge badge-amber">Successfully closed</span>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending Claims</div>
      <div class="stat-value"><?= $total_claims ?></div>
      <span class="stat-badge badge-blue">Under review</span>
    </div>
  </div>

  <!-- BOTTOM GRID -->
  <div class="bottom-grid">

    <!-- RECENT ITEMS TABLE -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent Item Reports</div>
        <a href="browse.php" class="view-all">View all →</a>
      </div>
      <table class="item-table" id="itemTable">
        <thead>
          <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Type</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($recent) === 0): ?>
            <tr class="empty-row"><td colspan="5">No items posted yet. Be the first!</td></tr>
          <?php else: ?>
            <?php while ($item = mysqli_fetch_assoc($recent)):
              // Pick emoji by category
              $icons = [
                'electronics' => '📱', 'wallet' => '👜', 'bag' => '🎒',
                'keys' => '🔑', 'id' => '🪪', 'clothing' => '👕', 'other' => '📦'
              ];
              $icon = $icons[strtolower($item['category'])] ?? '📦';

              $status_class = [
                'open'     => 's-' . $item['type'],
                'claimed'  => 's-claimed',
                'reunited' => 's-reunited'
              ][$item['status']] ?? 's-open';

              $status_label = $item['status'] === 'open'
                ? ucfirst($item['type'])
                : ucfirst($item['status']);
            ?>
            <tr>
              <td>
                <div class="item-info">
                  <div class="item-icon"><?= $icon ?></div>
                  <div>
                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="item-loc">📍 <?= htmlspecialchars($item['location']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="cat-pill"><?= htmlspecialchars($item['category']) ?></span></td>
              <td style="font-size:12px;color:var(--muted);text-transform:capitalize;"><?= $item['type'] ?></td>
              <td><span class="status-pill <?= $status_class ?>"><?= $status_label ?></span></td>
              <td>
                <a href="item-detail.php?id=<?= $item['id'] ?>" class="btn btn-outline" style="padding:5px 12px;font-size:12px;">View</a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ACTIVITY FEED -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent Activity</div>
      </div>
      <?php if (mysqli_num_rows($activity) === 0): ?>
        <div class="no-activity">No activity yet.</div>
      <?php else: ?>
        <?php while ($act = mysqli_fetch_assoc($activity)):
          $is_claim = $act['action_type'] === 'claim';
          $dot_class = $is_claim ? 'dot-blue' : 'dot-green';
          $verb      = $is_claim ? 'submitted a claim for' : 'posted a';
          $time      = date('d M, g:i a', strtotime($act['created_at']));
        ?>
        <div class="activity-item">
          <div class="act-dot <?= $dot_class ?>"></div>
          <div>
            <div class="act-text">
              <strong><?= htmlspecialchars($act['name']) ?></strong>
              <?= $verb ?>
              <strong><?= htmlspecialchars($act['item_name']) ?></strong>
            </div>
            <div class="act-time"><?= $time ?></div>
          </div>
        </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  // Live search filter on the recent items table
  function filterTable() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const rows  = document.querySelectorAll('#itemTable tbody tr');
    rows.forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
  }
</script>

</body>
</html>
