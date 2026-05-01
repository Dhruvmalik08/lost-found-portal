<?php
session_start();
require '../includes/db.php';

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_name = $_SESSION['user_name'];

// ── SITE-WIDE STATS ───────────────────────────────────────
$total_users    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_items    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items"))['c'];
$total_lost     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE type='lost'"))['c'];
$total_found    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE type='found'"))['c'];
$total_reunited = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status='reunited'"))['c'];
$total_claims   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM claims"))['c'];
$pending_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM claims WHERE status='pending'"))['c'];
$open_items     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE status='open'"))['c'];

// ── RECENT USERS ──────────────────────────────────────────
$recent_users = mysqli_query($conn, "
    SELECT id, name, email, role, created_at FROM users
    ORDER BY created_at DESC LIMIT 5
");

// ── RECENT ITEMS ──────────────────────────────────────────
$recent_items = mysqli_query($conn, "
    SELECT items.id, items.name, items.type, items.status, items.category,
           items.created_at, users.name AS poster_name
    FROM items
    JOIN users ON items.user_id = users.id
    ORDER BY items.created_at DESC LIMIT 6
");

// ── RECENT CLAIMS ─────────────────────────────────────────
$recent_claims = mysqli_query($conn, "
    SELECT claims.id, claims.status, claims.created_at,
           items.name AS item_name,
           u1.name AS claimant_name,
           u2.name AS poster_name
    FROM claims
    JOIN items ON claims.item_id = items.id
    JOIN users u1 ON claims.claimant_id = u1.id
    JOIN users u2 ON items.user_id = u2.id
    ORDER BY claims.created_at DESC LIMIT 5
");

function time_ago($d) {
    $diff = time() - strtotime($d);
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff/60).'m ago';
    if ($diff < 86400)   return floor($diff/3600).'h ago';
    if ($diff < 2592000) return floor($diff/86400).'d ago';
    return date('d M Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard – Lost & Found Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:#0a0f1e; --surface:#0f1729; --surface2:#162040; --border:#1e2d4a;
      --accent:#00c9a7; --accent2:#3b9eff; --danger:#e05252; --success:#00c9a7;
      --text:#e4eaf6; --muted:#6b7fa8; --radius:14px;
      --purple:#9b6cf7;
    }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

    /* SIDEBAR */
    .sidebar {
      width:230px; min-height:100vh; background:var(--surface);
      border-right:1px solid var(--border); display:flex; flex-direction:column;
      padding:28px 0; flex-shrink:0; position:sticky; top:0; height:100vh;
    }
    .logo { font-family:'Syne',sans-serif; font-weight:800; font-size:17px; color:var(--accent); padding:0 24px 6px; }
    .logo span { color:var(--text); }
    .admin-chip {
      margin:0 24px 20px; padding:6px 12px;
      background:rgba(155,108,247,0.12); border:1px solid rgba(155,108,247,0.25);
      border-radius:20px; font-size:11px; color:var(--purple); font-weight:600;
      text-transform:uppercase; letter-spacing:0.5px; text-align:center;
    }
    .nav-section {
      padding:0 24px 8px;
      font-size:10px; text-transform:uppercase; letter-spacing:0.8px;
      color:var(--muted); font-weight:600; margin-top:8px;
    }
    .nav { flex:1; }
    .nav a {
      display:flex; align-items:center; gap:11px; padding:10px 24px;
      font-size:13.5px; color:var(--muted); text-decoration:none;
      border-left:3px solid transparent; transition:all 0.15s;
    }
    .nav a:hover { color:var(--text); background:var(--surface2); }
    .nav a.active { color:var(--purple); border-left-color:var(--purple); background:rgba(155,108,247,0.07); font-weight:500; }
    .nav-divider { border:none; border-top:1px solid var(--border); margin:10px 0; }
    .nav a.user-link { color:var(--muted); font-size:13px; }
    .nav a.user-link:hover { color:var(--accent2); }

    .sidebar-bottom { padding:16px 24px 0; border-top:1px solid var(--border); }
    .user-chip { display:flex; align-items:center; gap:10px; }
    .avatar {
      width:34px; height:34px; border-radius:50%;
      background:linear-gradient(135deg, #9b6cf7, #00c9a7);
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; color:#fff; flex-shrink:0;
    }
    .user-name { font-size:13px; font-weight:500; }
    .user-role { font-size:11px; color:var(--purple); }
    .logout-link { display:block; margin-top:12px; font-size:12px; color:var(--danger); text-decoration:none; }
    .logout-link:hover { text-decoration:underline; }

    /* MAIN */
    .main { flex:1; padding:32px 36px; overflow-y:auto; }

    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; gap:16px; flex-wrap:wrap; }
    .page-title { font-family:'Syne',sans-serif; font-size:22px; font-weight:700; }
    .page-sub { font-size:13px; color:var(--muted); margin-top:3px; }

    /* STAT GRID */
    .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
    .stat-card {
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); padding:18px 20px;
    }
    .stat-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px; }
    .stat-val { font-family:'Syne',sans-serif; font-size:30px; font-weight:700; line-height:1; }
    .stat-tag {
      display:inline-block; margin-top:10px; font-size:11px;
      padding:3px 9px; border-radius:20px; font-weight:500;
    }
    .tag-purple  { background:rgba(155,108,247,0.15); color:var(--purple); }
    .tag-red     { background:rgba(224,82,82,0.15);   color:var(--danger); }
    .tag-green   { background:rgba(0,201,167,0.15);  color:var(--success); }
    .tag-amber   { background:rgba(0,201,167,0.15);  color:var(--accent); }
    .tag-blue    { background:rgba(59,158,255,0.15);  color:var(--accent2); }

    /* BOTTOM GRID */
    .bottom-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; }
    .full-row { margin-bottom:18px; }

    /* CARDS */
    .card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .card-header {
      padding:14px 20px; border-bottom:1px solid var(--border);
      display:flex; justify-content:space-between; align-items:center;
    }
    .card-title { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; }
    .view-all { font-size:12px; color:var(--accent2); text-decoration:none; }
    .view-all:hover { text-decoration:underline; }

    /* TABLE */
    table { width:100%; border-collapse:collapse; }
    th {
      font-size:11px; color:var(--muted); text-transform:uppercase;
      letter-spacing:0.4px; padding:10px 20px; text-align:left;
      border-bottom:1px solid var(--border); font-weight:500;
    }
    td { padding:12px 20px; font-size:13px; border-bottom:1px solid var(--border); }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(255,255,255,0.02); }

    .td-name { font-weight:500; }
    .td-muted { color:var(--muted); font-size:12px; }

    /* BADGES */
    .badge {
      font-size:10px; padding:3px 8px; border-radius:20px;
      font-weight:600; text-transform:uppercase; letter-spacing:0.3px;
    }
    .b-lost     { background:rgba(224,82,82,0.15);  color:var(--danger);  }
    .b-found    { background:rgba(0,201,167,0.15); color:var(--success); }
    .b-open     { background:rgba(0,201,167,0.15); color:var(--accent);  }
    .b-claimed  { background:rgba(59,158,255,0.15); color:var(--accent2); }
    .b-reunited { background:rgba(0,201,167,0.15); color:var(--success); }
    .b-pending  { background:rgba(0,201,167,0.15); color:var(--accent);  }
    .b-accepted { background:rgba(0,201,167,0.15); color:var(--success); }
    .b-rejected { background:rgba(224,82,82,0.15);  color:var(--danger);  }
    .b-admin    { background:rgba(155,108,247,0.15); color:var(--purple); }
    .b-user     { background:rgba(59,158,255,0.12);  color:var(--accent2); }

    .btn {
      padding:5px 13px; border-radius:7px; font-size:12px;
      font-family:'DM Sans',sans-serif; font-weight:500; cursor:pointer;
      border:none; text-decoration:none; display:inline-block; transition:all 0.15s;
    }
    .btn-outline { background:transparent; border:1px solid var(--border); color:var(--muted); }
    .btn-outline:hover { color:var(--text); background:var(--surface2); }

    /* EMPTY */
    .empty-row td { text-align:center; color:var(--muted); padding:28px; }

    /* PROGRESS BAR */
    .progress-row { padding:16px 20px; border-bottom:1px solid var(--border); }
    .progress-row:last-child { border-bottom:none; }
    .progress-label { display:flex; justify-content:space-between; font-size:13px; margin-bottom:8px; }
    .progress-label span:last-child { color:var(--muted); font-size:12px; }
    .progress-bar { height:6px; background:var(--surface2); border-radius:99px; overflow:hidden; }
    .progress-fill { height:100%; border-radius:99px; transition:width 0.4s; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="logo" style="padding-bottom:8px;">Lost<span>&Found</span></div>
  <div class="admin-chip">⚙ Admin Panel</div>
  <nav class="nav">
    <div class="nav-section">Admin</div>
    <a href="index.php" class="active">📊 Dashboard</a>
    <a href="users.php">👥 Manage Users</a>
    <a href="manage-items.php">📦 Manage Items</a>
    <hr class="nav-divider"/>
    <div class="nav-section">User Area</div>
    <a href="../dashboard.php" class="user-link">↗ User Dashboard</a>
    <a href="../browse.php" class="user-link">↗ Browse Items</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($admin_name, 0, 2)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </div>
    <a href="../logout.php" class="logout-link">← Log out</a>
  </div>
</aside>

<main class="main">

  <div class="topbar">
    <div>
      <div class="page-title">Admin Dashboard</div>
      <div class="page-sub">Site-wide overview — <?= date('l, d M Y') ?></div>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Users</div>
      <div class="stat-val"><?= $total_users ?></div>
      <span class="stat-tag tag-purple">Registered accounts</span>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Items</div>
      <div class="stat-val"><?= $total_items ?></div>
      <span class="stat-tag tag-blue"><?= $open_items ?> still open</span>
    </div>
    <div class="stat-card">
      <div class="stat-label">Reunited</div>
      <div class="stat-val"><?= $total_reunited ?></div>
      <span class="stat-tag tag-green">Successfully closed</span>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending Claims</div>
      <div class="stat-val"><?= $pending_claims ?></div>
      <span class="stat-tag tag-amber">Awaiting review</span>
    </div>
  </div>

  <!-- OVERVIEW BARS + RECENT CLAIMS -->
  <div class="bottom-grid">

    <!-- Overview breakdown -->
    <div class="card">
      <div class="card-header"><div class="card-title">Item Breakdown</div></div>
      <?php
      $pct = fn($n) => $total_items > 0 ? round(($n / $total_items) * 100) : 0;
      $bars = [
        ['Lost Items',    $total_lost,     '#e05252', $pct($total_lost)],
        ['Found Items',   $total_found,    '#00c9a7', $pct($total_found)],
        ['Reunited',      $total_reunited, '#00c9a7', $pct($total_reunited)],
        ['Total Claims',  $total_claims,   '#3b9eff', $total_claims > 0 ? 100 : 0],
      ];
      foreach ($bars as [$label, $val, $color, $pct_val]):
      ?>
      <div class="progress-row">
        <div class="progress-label">
          <span><?= $label ?></span>
          <span><?= $val ?> &nbsp;(<?= $pct_val ?>%)</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $pct_val ?>%;background:<?= $color ?>;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Recent claims -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent Claims</div>
        <a href="manage-items.php" class="view-all">View items →</a>
      </div>
      <?php if (mysqli_num_rows($recent_claims) === 0): ?>
        <div class="empty-row"><table><tr><td>No claims yet.</td></tr></table></div>
      <?php else: ?>
      <table>
        <thead><tr><th>Claimant</th><th>Item</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
        <?php while ($c = mysqli_fetch_assoc($recent_claims)): ?>
          <tr>
            <td class="td-name"><?= htmlspecialchars($c['claimant_name']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($c['item_name']) ?></td>
            <td><span class="badge b-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
            <td class="td-muted"><?= time_ago($c['created_at']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div>

  <!-- RECENT USERS -->
  <div class="card full-row">
    <div class="card-header">
      <div class="card-title">Recent Registrations</div>
      <a href="users.php" class="view-all">Manage all users →</a>
    </div>
    <?php if (mysqli_num_rows($recent_users) === 0): ?>
      <table><tr class="empty-row"><td colspan="4">No users yet.</td></tr></table>
    <?php else: ?>
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
      <tbody>
      <?php while ($u = mysqli_fetch_assoc($recent_users)): ?>
        <tr>
          <td class="td-name"><?= htmlspecialchars($u['name']) ?></td>
          <td class="td-muted"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge b-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
          <td class="td-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- RECENT ITEMS -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Recent Item Reports</div>
      <a href="manage-items.php" class="view-all">Manage all items →</a>
    </div>
    <?php if (mysqli_num_rows($recent_items) === 0): ?>
      <table><tr class="empty-row"><td colspan="5">No items posted yet.</td></tr></table>
    <?php else: ?>
    <table>
      <thead><tr><th>Item</th><th>Posted By</th><th>Type</th><th>Status</th><th>When</th></tr></thead>
      <tbody>
      <?php while ($it = mysqli_fetch_assoc($recent_items)): ?>
        <tr>
          <td class="td-name"><?= htmlspecialchars($it['name']) ?></td>
          <td class="td-muted"><?= htmlspecialchars($it['poster_name']) ?></td>
          <td><span class="badge b-<?= $it['type'] ?>"><?= ucfirst($it['type']) ?></span></td>
          <td><span class="badge b-<?= $it['status'] ?>"><?= ucfirst($it['status']) ?></span></td>
          <td class="td-muted"><?= time_ago($it['created_at']) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
