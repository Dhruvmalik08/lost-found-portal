<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── FILTERS ──────────────────────────────────────────────
$filter_type     = isset($_GET['type'])     ? $_GET['type']     : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$filter_status   = isset($_GET['status'])   ? $_GET['status']   : 'open';
$search          = isset($_GET['search'])   ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';

// Build WHERE clause
$where = [];

if ($filter_type !== 'all')
    $where[] = "items.type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";

if ($filter_category !== 'all')
    $where[] = "items.category = '" . mysqli_real_escape_string($conn, $filter_category) . "'";

if ($filter_status !== 'all')
    $where[] = "items.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";

if (!empty($search))
    $where[] = "(items.name LIKE '%$search%' OR items.location LIKE '%$search%' OR items.description LIKE '%$search%')";

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ── FETCH ITEMS ───────────────────────────────────────────
$items_result = mysqli_query($conn, "
    SELECT items.*, users.name AS poster_name
    FROM items
    JOIN users ON items.user_id = users.id
    $where_sql
    ORDER BY items.created_at DESC
");

$total_results = mysqli_num_rows($items_result);

// Category icons
$icons = [
    'Electronics' => '📱', 'Wallet' => '👜', 'Bag' => '🎒',
    'Keys' => '🔑', 'ID' => '🪪', 'Clothing' => '👕',
    'Jewellery' => '💍', 'Books' => '📚', 'Other' => '📦'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Items – Lost & Found Portal</title>
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
    .main { flex: 1; padding: 32px 36px; overflow-y: auto; }

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

    /* ── FILTER BAR ── */
    .filter-bar {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 20px;
      margin-bottom: 24px;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: flex-end;
    }

    .filter-group { display: flex; flex-direction: column; gap: 6px; }
    .filter-label {
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 500;
    }

    select, input[type="text"] {
      padding: 9px 12px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 13px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      transition: border-color 0.15s;
      appearance: none;
      min-width: 140px;
    }
    select:focus, input[type="text"]:focus { border-color: var(--accent2); }
    select option { background: var(--surface2); }
    input::placeholder { color: var(--muted); }

    .search-input { min-width: 200px; }

    .btn {
      padding: 9px 20px;
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
    .btn-primary  { background: var(--accent);  color: #0a0f1e; }
    .btn-primary:hover  { background: #33d4b7; }
    .btn-outline  {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .btn-outline:hover { color: var(--text); background: var(--surface2); }

    /* ── RESULTS COUNT ── */
    .results-info {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 16px;
    }
    .results-info strong { color: var(--text); }

    /* ── ITEMS GRID ── */
    .items-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
    }

    .item-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: border-color 0.2s, transform 0.15s;
      text-decoration: none;
      color: inherit;
      display: flex;
      flex-direction: column;
    }
    .item-card:hover {
      border-color: #3a4060;
      transform: translateY(-2px);
    }

    /* photo area */
    .item-photo {
      width: 100%;
      height: 160px;
      background: var(--surface2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      flex-shrink: 0;
      overflow: hidden;
    }
    .item-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* card body */
    .item-body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 8px; }

    .item-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .item-name {
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      line-height: 1.3;
      flex: 1;
    }

    .type-badge {
      font-size: 10px;
      padding: 3px 8px;
      border-radius: 20px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      flex-shrink: 0;
    }
    .type-lost  { background: rgba(224,82,82,0.15);  color: var(--danger);  }
    .type-found { background: rgba(0,201,167,0.15); color: var(--success); }

    .item-meta { font-size: 12px; color: var(--muted); display: flex; flex-direction: column; gap: 4px; }

    .item-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: auto;
      padding-top: 12px;
      border-top: 1px solid var(--border);
    }

    .cat-pill {
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      background: rgba(59,158,255,0.1);
      color: var(--accent2);
    }

    .status-pill {
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      font-weight: 500;
    }
    .s-open     { background: rgba(0,201,167,0.15); color: var(--accent);  }
    .s-claimed  { background: rgba(59,158,255,0.15); color: var(--accent2); }
    .s-reunited { background: rgba(0,201,167,0.15); color: var(--success); }

    /* ── EMPTY STATE ── */
    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }
    .empty-icon { font-size: 48px; margin-bottom: 16px; }
    .empty-title {
      font-family: 'Syne', sans-serif;
      font-size: 18px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
    }
    .empty-sub { font-size: 13px; margin-bottom: 20px; }
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
        <div class="user-role"><?= ucfirst($_SESSION['user_role']) ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-link">← Log out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <div class="topbar">
    <div>
      <div class="page-title">Browse Items</div>
      <div class="page-sub">Search through all lost and found reports</div>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="post-lost.php"  class="btn btn-outline">+ Post Lost</a>
      <a href="post-found.php" class="btn btn-primary">+ Post Found</a>
    </div>
  </div>

  <!-- FILTER BAR -->
  <form method="GET" action="browse.php">
    <div class="filter-bar">

      <div class="filter-group">
        <div class="filter-label">Search</div>
        <input type="text" name="search" class="search-input"
               placeholder="Item name, location..."
               value="<?= htmlspecialchars($search) ?>"/>
      </div>

      <div class="filter-group">
        <div class="filter-label">Type</div>
        <select name="type">
          <option value="all"   <?= $filter_type === 'all'   ? 'selected' : '' ?>>All Types</option>
          <option value="lost"  <?= $filter_type === 'lost'  ? 'selected' : '' ?>>Lost</option>
          <option value="found" <?= $filter_type === 'found' ? 'selected' : '' ?>>Found</option>
        </select>
      </div>

      <div class="filter-group">
        <div class="filter-label">Category</div>
        <select name="category">
          <option value="all"         <?= $filter_category === 'all'         ? 'selected' : '' ?>>All Categories</option>
          <option value="Electronics" <?= $filter_category === 'Electronics' ? 'selected' : '' ?>>Electronics</option>
          <option value="Wallet"      <?= $filter_category === 'Wallet'      ? 'selected' : '' ?>>Wallet</option>
          <option value="Bag"         <?= $filter_category === 'Bag'         ? 'selected' : '' ?>>Bag</option>
          <option value="Keys"        <?= $filter_category === 'Keys'        ? 'selected' : '' ?>>Keys</option>
          <option value="ID"          <?= $filter_category === 'ID'          ? 'selected' : '' ?>>ID / Card</option>
          <option value="Clothing"    <?= $filter_category === 'Clothing'    ? 'selected' : '' ?>>Clothing</option>
          <option value="Jewellery"   <?= $filter_category === 'Jewellery'   ? 'selected' : '' ?>>Jewellery</option>
          <option value="Books"       <?= $filter_category === 'Books'       ? 'selected' : '' ?>>Books</option>
          <option value="Other"       <?= $filter_category === 'Other'       ? 'selected' : '' ?>>Other</option>
        </select>
      </div>

      <div class="filter-group">
        <div class="filter-label">Status</div>
        <select name="status">
          <option value="all"      <?= $filter_status === 'all'      ? 'selected' : '' ?>>All Statuses</option>
          <option value="open"     <?= $filter_status === 'open'     ? 'selected' : '' ?>>Open</option>
          <option value="claimed"  <?= $filter_status === 'claimed'  ? 'selected' : '' ?>>Claimed</option>
          <option value="reunited" <?= $filter_status === 'reunited' ? 'selected' : '' ?>>Reunited</option>
        </select>
      </div>

      <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:8px;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="browse.php" class="btn btn-outline">Reset</a>
      </div>

    </div>
  </form>

  <!-- RESULTS COUNT -->
  <div class="results-info">
    Showing <strong><?= $total_results ?></strong> item<?= $total_results !== 1 ? 's' : '' ?>
    <?php if (!empty($search)): ?>
      for <strong>"<?= htmlspecialchars($search) ?>"</strong>
    <?php endif; ?>
  </div>

  <!-- ITEMS GRID -->
  <div class="items-grid">

    <?php if ($total_results === 0): ?>
      <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <div class="empty-title">No items found</div>
        <div class="empty-sub">Try adjusting your filters or search term</div>
        <a href="browse.php" class="btn btn-outline">Clear filters</a>
      </div>

    <?php else: ?>
      <?php while ($item = mysqli_fetch_assoc($items_result)):
        $icon        = $icons[$item['category']] ?? '📦';
        $has_photo   = !empty($item['photo']) && file_exists('uploads/' . $item['photo']);
        $status_class = 's-' . $item['status'];
        $time_ago    = time_ago($item['created_at']);
      ?>
      <a href="item-detail.php?id=<?= $item['id'] ?>" class="item-card">

        <!-- Photo / Icon -->
        <div class="item-photo">
          <?php if ($has_photo): ?>
            <img src="uploads/<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"/>
          <?php else: ?>
            <?= $icon ?>
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="item-body">
          <div class="item-header">
            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
            <span class="type-badge type-<?= $item['type'] ?>"><?= $item['type'] ?></span>
          </div>

          <div class="item-meta">
            <span>📍 <?= htmlspecialchars($item['location']) ?></span>
            <span>🕐 <?= $time_ago ?></span>
            <span>👤 <?= htmlspecialchars($item['poster_name']) ?></span>
          </div>

          <div class="item-footer">
            <span class="cat-pill"><?= htmlspecialchars($item['category']) ?></span>
            <span class="status-pill <?= $status_class ?>"><?= ucfirst($item['status']) ?></span>
          </div>
        </div>

      </a>
      <?php endwhile; ?>
    <?php endif; ?>

  </div>
</main>

<?php
// Helper: human-readable time ago
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)          return 'Just now';
    if ($diff < 3600)        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)       return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000)     return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}
?>

</body>
</html>
