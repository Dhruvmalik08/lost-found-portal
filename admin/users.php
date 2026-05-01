<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id   = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];
$error      = "";
$success    = "";

// ── PROMOTE / DEMOTE ──────────────────────────────────────
if (isset($_POST['toggle_role'])) {
    $uid      = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    if ($uid === $admin_id) {
        $error = "You cannot change your own role.";
    } elseif (in_array($new_role, ['user', 'admin'])) {
        mysqli_query($conn, "UPDATE users SET role='$new_role' WHERE id=$uid");
        $success = "User role updated to " . ucfirst($new_role) . ".";
    }
}

// ── DELETE USER ───────────────────────────────────────────
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid === $admin_id) {
        $error = "You cannot delete your own account.";
    } else {
        // Delete photo files for all items owned by this user
        $photos = mysqli_query($conn, "SELECT photo FROM items WHERE user_id=$uid AND photo != ''");
        while ($p = mysqli_fetch_assoc($photos)) {
            $path = '../uploads/' . $p['photo'];
            if (file_exists($path)) unlink($path);
        }
        mysqli_query($conn, "DELETE FROM claims WHERE claimant_id=$uid");
        mysqli_query($conn, "DELETE FROM claims WHERE item_id IN (SELECT id FROM items WHERE user_id=$uid)");
        mysqli_query($conn, "DELETE FROM items WHERE user_id=$uid");
        mysqli_query($conn, "DELETE FROM users WHERE id=$uid");
        $success = "User and all their data deleted.";
    }
}

// ── SEARCH + FILTER ───────────────────────────────────────
$search     = isset($_GET['search']) ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';
$role_filter = isset($_GET['role'])  ? $_GET['role'] : 'all';

$where = [];
if (!empty($search))          $where[] = "(name LIKE '%$search%' OR email LIKE '%$search%')";
if ($role_filter !== 'all')   $where[] = "role = '" . mysqli_real_escape_string($conn, $role_filter) . "'";
$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$users_result = mysqli_query($conn, "
    SELECT users.*,
           (SELECT COUNT(*) FROM items  WHERE items.user_id  = users.id) AS item_count,
           (SELECT COUNT(*) FROM claims WHERE claims.claimant_id = users.id) AS claim_count
    FROM users
    $where_sql
    ORDER BY created_at DESC
");
$total_users = mysqli_num_rows($users_result);

// Stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total,
           SUM(role='admin') AS admins,
           SUM(role='user')  AS users
    FROM users
"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Users – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root {
      --bg:#0a0f1e; --surface:#0f1729; --surface2:#162040; --border:#1e2d4a;
      --accent:#00c9a7; --accent2:#3b9eff; --danger:#e05252; --success:#00c9a7;
      --text:#e4eaf6; --muted:#6b7fa8; --radius:14px; --purple:#9b6cf7;
    }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

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

    .main { flex:1; padding:32px 36px; overflow-y:auto; }

    .topbar { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
    .page-title { font-family:'Syne',sans-serif; font-size:22px; font-weight:700; }
    .page-sub { font-size:13px; color:var(--muted); margin-top:3px; }

    .alert { padding:12px 16px; border-radius:10px; font-size:13.5px; margin-bottom:20px; font-weight:500; }
    .alert-error   { background:rgba(224,82,82,0.12);  color:var(--danger);  border:1px solid rgba(224,82,82,0.25); }
    .alert-success { background:rgba(0,201,167,0.12); color:var(--success); border:1px solid rgba(0,201,167,0.25); }

    /* MINI STATS */
    .mini-stats { display:flex; gap:12px; margin-bottom:20px; }
    .mini-stat {
      background:var(--surface); border:1px solid var(--border);
      border-radius:10px; padding:12px 20px;
      display:flex; align-items:center; gap:10px;
    }
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
      appearance:none; min-width:160px;
    }
    input:focus, select:focus { border-color:var(--accent2); }
    input::placeholder { color:var(--muted); }
    select option { background:var(--surface2); }

    /* TABLE */
    .table-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .table-header {
      padding:14px 20px; border-bottom:1px solid var(--border);
      display:flex; justify-content:space-between; align-items:center;
    }
    .table-title { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; }
    .result-count { font-size:12px; color:var(--muted); }

    table { width:100%; border-collapse:collapse; }
    th {
      font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.4px;
      padding:10px 20px; text-align:left; border-bottom:1px solid var(--border); font-weight:500;
    }
    td { padding:13px 20px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(255,255,255,0.015); }

    /* user row */
    .user-row { display:flex; align-items:center; gap:10px; }
    .u-avatar {
      width:32px; height:32px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      font-size:12px; font-weight:700; color:#fff;
    }
    .u-name { font-weight:500; font-size:13.5px; }
    .u-email { font-size:12px; color:var(--muted); margin-top:1px; }

    .badge {
      font-size:10px; padding:3px 9px; border-radius:20px;
      font-weight:600; text-transform:uppercase; letter-spacing:0.3px; white-space:nowrap;
    }
    .b-admin { background:rgba(155,108,247,0.15); color:var(--purple); }
    .b-user  { background:rgba(59,158,255,0.12);  color:var(--accent2); }

    .actions { display:flex; gap:8px; align-items:center; }
    .btn {
      padding:5px 13px; border-radius:7px; font-size:12px;
      font-family:'DM Sans',sans-serif; font-weight:500; cursor:pointer;
      border:none; text-decoration:none; display:inline-block; transition:all 0.15s; white-space:nowrap;
    }
    .btn-purple { background:rgba(155,108,247,0.15); color:var(--purple); border:1px solid rgba(155,108,247,0.25); }
    .btn-purple:hover { background:rgba(155,108,247,0.28); }
    .btn-outline { background:transparent; border:1px solid var(--border); color:var(--muted); }
    .btn-outline:hover { color:var(--text); background:var(--surface2); }
    .btn-danger { background:rgba(224,82,82,0.1); color:var(--danger); border:1px solid rgba(224,82,82,0.2); }
    .btn-danger:hover { background:rgba(224,82,82,0.22); }
    .btn-primary { background:var(--accent); color:#0a0f1e; }
    .btn-primary:hover { background:#33d4b7; }

    .you-chip {
      font-size:10px; padding:2px 7px; border-radius:20px;
      background:rgba(0,201,167,0.15); color:var(--accent);
      font-weight:600; text-transform:uppercase;
    }

    .empty-row td { text-align:center; color:var(--muted); padding:40px; font-size:13px; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="logo" style="padding-bottom:8px;">Lost<span>&Found</span></div>
  <div class="admin-chip">⚙ Admin Panel</div>
  <nav class="nav">
    <div class="nav-section">Admin</div>
    <a href="index.php">📊 Dashboard</a>
    <a href="users.php" class="active">👥 Manage Users</a>
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
      <div class="page-title">Manage Users</div>
      <div class="page-sub">View, promote, demote, or remove user accounts</div>
    </div>
  </div>

  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

  <!-- MINI STATS -->
  <div class="mini-stats">
    <div class="mini-stat"><div class="ms-val"><?= $stats['total'] ?></div><div class="ms-lbl">Total Users</div></div>
    <div class="mini-stat"><div class="ms-val"><?= $stats['admins'] ?></div><div class="ms-lbl">Admins</div></div>
    <div class="mini-stat"><div class="ms-val"><?= $stats['users'] ?></div><div class="ms-lbl">Regular Users</div></div>
  </div>

  <!-- FILTER BAR -->
  <form method="GET" action="users.php">
    <div class="filter-bar">
      <div class="filter-group">
        <div class="filter-label">Search</div>
        <input type="text" name="search" placeholder="Name or email…" value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div class="filter-group">
        <div class="filter-label">Role</div>
        <select name="role">
          <option value="all"   <?= $role_filter==='all'   ? 'selected':'' ?>>All Roles</option>
          <option value="user"  <?= $role_filter==='user'  ? 'selected':'' ?>>User</option>
          <option value="admin" <?= $role_filter==='admin' ? 'selected':'' ?>>Admin</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;align-self:flex-end;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="users.php" class="btn btn-outline">Reset</a>
      </div>
    </div>
  </form>

  <!-- USERS TABLE -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">All Users</div>
      <div class="result-count"><?= $total_users ?> result<?= $total_users !== 1 ? 's' : '' ?></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Items</th>
          <th>Claims</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($total_users === 0): ?>
          <tr class="empty-row"><td colspan="6">No users found.</td></tr>
        <?php else: ?>
        <?php while ($u = mysqli_fetch_assoc($users_result)):
          $is_self   = ((int)$u['id'] === $admin_id);
          $is_admin  = ($u['role'] === 'admin');
          $colors    = ['#3b9eff','#9b6cf7','#00c9a7','#00c9a7','#e05252'];
          $color     = $colors[crc32($u['name']) % count($colors)];
        ?>
        <tr>
          <td>
            <div class="user-row">
              <div class="u-avatar" style="background:<?= $color ?>;"><?= strtoupper(substr($u['name'],0,2)) ?></div>
              <div>
                <div class="u-name">
                  <?= htmlspecialchars($u['name']) ?>
                  <?php if ($is_self): ?><span class="you-chip">You</span><?php endif; ?>
                </div>
                <div class="u-email"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge b-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
          <td><?= $u['item_count'] ?></td>
          <td><?= $u['claim_count'] ?></td>
          <td style="color:var(--muted);font-size:12px;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($is_self): ?>
              <span style="font-size:12px;color:var(--muted);">—</span>
            <?php else: ?>
              <div class="actions">
                <!-- Promote / Demote -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="user_id"  value="<?= $u['id'] ?>"/>
                  <input type="hidden" name="new_role" value="<?= $is_admin ? 'user' : 'admin' ?>"/>
                  <button type="submit" name="toggle_role"
                          class="btn <?= $is_admin ? 'btn-outline' : 'btn-purple' ?>"
                          onclick="return confirm('<?= $is_admin ? 'Demote this admin to user?' : 'Promote this user to admin?' ?>')">
                    <?= $is_admin ? '↓ Demote' : '↑ Promote' ?>
                  </button>
                </form>
                <!-- Delete -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
                  <button type="submit" name="delete_user" class="btn btn-danger"
                          onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This will also delete all their items and claims.')">
                    🗑 Delete
                  </button>
                </form>
              </div>
            <?php endif; ?>
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
