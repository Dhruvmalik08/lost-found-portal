<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_name = $_SESSION['user_name'];
$error      = "";
$success    = "";

// ── DELETE ITEM ───────────────────────────────────────────
if (isset($_POST['delete_item'])) {
    $del_id = (int)$_POST['item_id'];
    $photo  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT photo FROM items WHERE id=$del_id"));
    if ($photo && !empty($photo['photo'])) {
        $path = '../uploads/' . $photo['photo'];
        if (file_exists($path)) unlink($path);
    }
    mysqli_query($conn, "DELETE FROM claims WHERE item_id=$del_id");
    mysqli_query($conn, "DELETE FROM items  WHERE id=$del_id");
    $success = "Item deleted successfully.";
}

// ── CHANGE STATUS ─────────────────────────────────────────
if (isset($_POST['change_status'])) {
    $sid        = (int)$_POST['item_id'];
    $new_status = $_POST['new_status'];
    if (in_array($new_status, ['open', 'claimed', 'reunited'])) {
        mysqli_query($conn, "UPDATE items SET status='$new_status' WHERE id=$sid");
        $success = "Item status updated to " . ucfirst($new_status) . ".";
    }
}

// ── FILTERS ───────────────────────────────────────────────
$search      = isset($_GET['search'])   ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';
$f_type      = isset($_GET['type'])     ? $_GET['type']     : 'all';
$f_status    = isset($_GET['status'])   ? $_GET['status']   : 'all';
$f_category  = isset($_GET['category']) ? $_GET['category'] : 'all';

$where = [];
if (!empty($search))    $where[] = "(items.name LIKE '%$search%' OR items.location LIKE '%$search%' OR users.name LIKE '%$search%')";
if ($f_type !== 'all')     $where[] = "items.type = '" . mysqli_real_escape_string($conn, $f_type) . "'";
if ($f_status !== 'all')   $where[] = "items.status = '" . mysqli_real_escape_string($conn, $f_status) . "'";
if ($f_category !== 'all') $where[] = "items.category = '" . mysqli_real_escape_string($conn, $f_category) . "'";
$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$items_result = mysqli_query($conn, "
    SELECT items.*,
           users.name  AS poster_name,
           users.email AS poster_email,
           (SELECT COUNT(*) FROM claims WHERE claims.item_id = items.id) AS claim_count
    FROM items
    JOIN users ON items.user_id = users.id
    $where_sql
    ORDER BY items.created_at DESC
");
$total = mysqli_num_rows($items_result);

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total,
           SUM(type='lost') AS lost,
           SUM(type='found') AS found,
           SUM(status='open') AS open_c,
           SUM(status='reunited') AS reunited
    FROM items
"));

$icons = [
    'Electronics'=>'📱','Wallet'=>'👜','Bag'=>'🎒','Keys'=>'🔑',
    'ID'=>'🪪','Clothing'=>'👕','Jewellery'=>'💍','Books'=>'📚','Other'=>'📦'
];

function time_ago($d) {
    $diff = time()-strtotime($d);
    if ($diff<60)      return 'Just now';
    if ($diff<3600)    return floor($diff/60).'m ago';
    if ($diff<86400)   return floor($diff/3600).'h ago';
    if ($diff<2592000) return floor($diff/86400).'d ago';
    return date('d M Y',strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Items – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root {
      --bg:#0a0f1e; --surface:#0f1729; --surface2:#162040; --border:#1e2d4a;
      --accent:#00c9a7; --accent2:#3b9eff; --danger:#e05252; --success:#00c9a7;
      --text:#e4eaf6; --muted:#6b7fa8; --radius:14px; --purple:#9b6cf7;
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
    .nav-section { padding:0 24px 8px; font-size:10px; text-transform:uppercase; letter-spacing:0.8px; color:var(--muted); font-weight:600; margin-top:8px; }
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
    .topbar { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
    .page-title { font-family:'Syne',sans-serif; font-size:22px; font-weight:700; }
    .page-sub { font-size:13px; color:var(--muted); margin-top:3px; }

    .alert { padding:12px 16px; border-radius:10px; font-size:13.5px; margin-bottom:20px; font-weight:500; }
    .alert-error   { background:rgba(224,82,82,0.12);  color:var(--danger);  border:1px solid rgba(224,82,82,0.25); }
    .alert-success { background:rgba(0,201,167,0.12); color:var(--success); border:1px solid rgba(0,201,167,0.25); }

    /* MINI STATS */
    .mini-stats { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
    .mini-stat { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:12px 20px; display:flex; align-items:center; gap:10px; }
    .ms-val { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; }
    .ms-lbl { font-size:12px; color:var(--muted); }

    /* FILTER BAR */
    .filter-bar {
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); padding:14px 18px;
      display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;
      margin-bottom:20px;
    }
    .filter-group { display:flex; flex-direction:column; gap:5px; }
    .filter-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; font-weight:500; }
    input[type="text"], select {
      padding:9px 12px; background:var(--surface2); border:1px solid var(--border);
      border-radius:8px; color:var(--text); font-size:13px;
      font-family:'DM Sans',sans-serif; outline:none; transition:border-color 0.15s;
      appearance:none; min-width:140px;
    }
    input:focus, select:focus { border-color:var(--accent2); }
    input::placeholder { color:var(--muted); }
    select option { background:var(--surface2); }

    /* TABLE */
    .table-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .table-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
    .table-title { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; }
    .result-count { font-size:12px; color:var(--muted); }

    table { width:100%; border-collapse:collapse; }
    th { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.4px; padding:10px 16px; text-align:left; border-bottom:1px solid var(--border); font-weight:500; }
    td { padding:12px 16px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(255,255,255,0.015); }

    /* item name cell */
    .item-cell { display:flex; align-items:center; gap:10px; }
    .item-thumb {
      width:38px; height:38px; border-radius:8px;
      background:var(--surface2); border:1px solid var(--border);
      display:flex; align-items:center; justify-content:center;
      font-size:16px; overflow:hidden; flex-shrink:0;
    }
    .item-thumb img { width:100%; height:100%; object-fit:cover; }
    .item-name-txt { font-weight:500; font-size:13.5px; }
    .item-loc { font-size:11.5px; color:var(--muted); margin-top:1px; }

    /* badges */
    .badge { font-size:10px; padding:3px 8px; border-radius:20px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; white-space:nowrap; }
    .b-lost     { background:rgba(224,82,82,0.15);  color:var(--danger);  }
    .b-found    { background:rgba(0,201,167,0.15); color:var(--success); }
    .b-open     { background:rgba(0,201,167,0.15); color:var(--accent);  }
    .b-claimed  { background:rgba(59,158,255,0.15); color:var(--accent2); }
    .b-reunited { background:rgba(0,201,167,0.15); color:var(--success); }

    /* status select inline */
    .status-form { display:flex; gap:6px; align-items:center; }
    .status-select {
      padding:5px 10px; background:var(--surface2); border:1px solid var(--border);
      border-radius:7px; color:var(--text); font-size:12px;
      font-family:'DM Sans',sans-serif; outline:none; appearance:none; cursor:pointer;
      min-width:100px;
    }
    .status-select:focus { border-color:var(--accent2); }
    .status-select option { background:var(--surface2); }

    /* buttons */
    .btn {
      padding:5px 13px; border-radius:7px; font-size:12px;
      font-family:'DM Sans',sans-serif; font-weight:500; cursor:pointer;
      border:none; text-decoration:none; display:inline-block; transition:all 0.15s; white-space:nowrap;
    }
    .btn-primary { background:var(--accent); color:#0a0f1e; }
    .btn-primary:hover { background:#33d4b7; }
    .btn-outline { background:transparent; border:1px solid var(--border); color:var(--muted); }
    .btn-outline:hover { color:var(--text); background:var(--surface2); }
    .btn-blue { background:rgba(59,158,255,0.15); color:var(--accent2); border:1px solid rgba(59,158,255,0.2); }
    .btn-blue:hover { background:rgba(59,158,255,0.28); }
    .btn-danger { background:rgba(224,82,82,0.1); color:var(--danger); border:1px solid rgba(224,82,82,0.2); }
    .btn-danger:hover { background:rgba(224,82,82,0.22); }
    .btn-save { background:rgba(0,201,167,0.15); color:var(--success); border:1px solid rgba(0,201,167,0.2); padding:4px 10px; }
    .btn-save:hover { background:rgba(0,201,167,0.28); }

    .actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .empty-row td { text-align:center; color:var(--muted); padding:40px; font-size:13px; }
    .td-muted { color:var(--muted); font-size:12px; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="logo" style="padding-bottom:8px;">Lost<span>&Found</span></div>
  <div class="admin-chip">⚙ Admin Panel</div>
  <nav class="nav">
    <div class="nav-section">Admin</div>
    <a href="index.php">📊 Dashboard</a>
    <a href="users.php">👥 Manage Users</a>
    <a href="manage-items.php" class="active">📦 Manage Items</a>
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
      <div class="page-title">Manage Items</div>
      <div class="page-sub">View, moderate, and delete all reported items</div>
    </div>
  </div>

  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

  <!-- MINI STATS -->
  <div class="mini-stats">
    <div class="mini-stat"><div class="ms-val"><?= $stats['total'] ?></div><div class="ms-lbl">Total Items</div></div>
    <div class="mini-stat"><div class="ms-val"><?= $stats['lost'] ?></div><div class="ms-lbl">Lost</div></div>
    <div class="mini-stat"><div class="ms-val"><?= $stats['found'] ?></div><div class="ms-lbl">Found</div></div>
    <div class="mini-stat"><div class="ms-val"><?= $stats['open_c'] ?></div><div class="ms-lbl">Open</div></div>
    <div class="mini-stat"><div class="ms-val"><?= $stats['reunited'] ?></div><div class="ms-lbl">Reunited</div></div>
  </div>

  <!-- FILTER BAR -->
  <form method="GET" action="manage-items.php">
    <div class="filter-bar">
      <div class="filter-group">
        <div class="filter-label">Search</div>
        <input type="text" name="search" placeholder="Item, location, user…" value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div class="filter-group">
        <div class="filter-label">Type</div>
        <select name="type">
          <option value="all"   <?= $f_type==='all'   ? 'selected':'' ?>>All Types</option>
          <option value="lost"  <?= $f_type==='lost'  ? 'selected':'' ?>>Lost</option>
          <option value="found" <?= $f_type==='found' ? 'selected':'' ?>>Found</option>
        </select>
      </div>
      <div class="filter-group">
        <div class="filter-label">Status</div>
        <select name="status">
          <option value="all"      <?= $f_status==='all'      ? 'selected':'' ?>>All Statuses</option>
          <option value="open"     <?= $f_status==='open'     ? 'selected':'' ?>>Open</option>
          <option value="claimed"  <?= $f_status==='claimed'  ? 'selected':'' ?>>Claimed</option>
          <option value="reunited" <?= $f_status==='reunited' ? 'selected':'' ?>>Reunited</option>
        </select>
      </div>
      <div class="filter-group">
        <div class="filter-label">Category</div>
        <select name="category">
          <option value="all" <?= $f_category==='all' ? 'selected':'' ?>>All Categories</option>
          <?php foreach (array_keys($icons) as $cat): ?>
            <option value="<?= $cat ?>" <?= $f_category===$cat ? 'selected':'' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;align-self:flex-end;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="manage-items.php" class="btn btn-outline">Reset</a>
      </div>
    </div>
  </form>

  <!-- ITEMS TABLE -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">All Items</div>
      <div class="result-count"><?= $total ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th>Posted By</th>
          <th>Type</th>
          <th>Claims</th>
          <th>Posted</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($total === 0): ?>
          <tr class="empty-row"><td colspan="7">No items found.</td></tr>
        <?php else: ?>
        <?php while ($item = mysqli_fetch_assoc($items_result)):
          $icon      = $icons[$item['category']] ?? '📦';
          $has_photo = !empty($item['photo']) && file_exists('../uploads/' . $item['photo']);
        ?>
        <tr>
          <!-- Item name + location -->
          <td>
            <div class="item-cell">
              <div class="item-thumb">
                <?php if ($has_photo): ?>
                  <img src="../uploads/<?= htmlspecialchars($item['photo']) ?>" alt=""/>
                <?php else: ?>
                  <?= $icon ?>
                <?php endif; ?>
              </div>
              <div>
                <div class="item-name-txt"><?= htmlspecialchars($item['name']) ?></div>
                <div class="item-loc">📍 <?= htmlspecialchars($item['location']) ?></div>
              </div>
            </div>
          </td>

          <!-- Poster -->
          <td>
            <div style="font-weight:500;font-size:13px;"><?= htmlspecialchars($item['poster_name']) ?></div>
            <div class="td-muted"><?= htmlspecialchars($item['poster_email']) ?></div>
          </td>

          <!-- Type -->
          <td><span class="badge b-<?= $item['type'] ?>"><?= ucfirst($item['type']) ?></span></td>

          <!-- Claims count -->
          <td style="text-align:center;">
            <?php if ($item['claim_count'] > 0): ?>
              <a href="../item-detail.php?id=<?= $item['id'] ?>" class="btn btn-blue" style="padding:3px 10px;font-size:11px;">
                <?= $item['claim_count'] ?> claim<?= $item['claim_count']>1?'s':'' ?>
              </a>
            <?php else: ?>
              <span class="td-muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Posted when -->
          <td class="td-muted"><?= time_ago($item['created_at']) ?></td>

          <!-- Status override -->
          <td>
            <form method="POST" class="status-form">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>
              <select name="new_status" class="status-select" onchange="this.form.submit()">
                <option value="open"     <?= $item['status']==='open'     ? 'selected':'' ?>>Open</option>
                <option value="claimed"  <?= $item['status']==='claimed'  ? 'selected':'' ?>>Claimed</option>
                <option value="reunited" <?= $item['status']==='reunited' ? 'selected':'' ?>>Reunited</option>
              </select>
              <input type="hidden" name="change_status" value="1"/>
            </form>
          </td>

          <!-- Actions -->
          <td>
            <div class="actions">
              <a href="../item-detail.php?id=<?= $item['id'] ?>" class="btn btn-outline">View</a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>"/>
                <button type="submit" name="delete_item" class="btn btn-danger"
                        onclick="return confirm('Delete this item and all its claims?')">🗑</button>
              </form>
            </div>
          </td>

        </tr>
        <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>
</body>
</html>
