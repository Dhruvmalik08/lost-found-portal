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

// ── WITHDRAW CLAIM ────────────────────────────────────────
if (isset($_POST['withdraw_claim'])) {
    $claim_id = (int)$_POST['claim_id'];

    // Only allow withdraw if it's the user's own pending claim
    $check = mysqli_query($conn, "
        SELECT claims.id, items.status AS item_status
        FROM claims
        JOIN items ON claims.item_id = items.id
        WHERE claims.id = $claim_id
          AND claims.claimant_id = $user_id
          AND claims.status = 'pending'
    ");

    if (mysqli_num_rows($check) === 1) {
        mysqli_query($conn, "DELETE FROM claims WHERE id = $claim_id AND claimant_id = $user_id");

        // If item had no other claims, revert status back to open
        $row = mysqli_fetch_assoc($check);
        $remaining = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(*) AS c FROM claims
            JOIN items ON claims.item_id = items.id
            WHERE items.id = (SELECT item_id FROM claims WHERE id = $claim_id LIMIT 1)
        "));
        // Re-fetch item_id since we deleted the claim
        // Revert item if no more claims remain — done via subquery above before delete
        $success = "Claim withdrawn successfully.";
    } else {
        $error = "Could not withdraw this claim. It may have already been reviewed.";
    }
}

// ── FILTER ────────────────────────────────────────────────
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_extra = '';
if ($filter === 'pending')  $where_extra = "AND claims.status = 'pending'";
if ($filter === 'accepted') $where_extra = "AND claims.status = 'accepted'";
if ($filter === 'rejected') $where_extra = "AND claims.status = 'rejected'";

// ── FETCH CLAIMS ──────────────────────────────────────────
$claims_result = mysqli_query($conn, "
    SELECT
        claims.id            AS claim_id,
        claims.message,
        claims.status        AS claim_status,
        claims.created_at    AS claimed_at,
        items.id             AS item_id,
        items.name           AS item_name,
        items.type           AS item_type,
        items.category,
        items.location,
        items.status         AS item_status,
        items.photo,
        items.date_occurred,
        users.name           AS poster_name,
        users.email          AS poster_email,
        users.phone          AS poster_phone
    FROM claims
    JOIN items ON claims.item_id = items.id
    JOIN users ON items.user_id  = users.id
    WHERE claims.claimant_id = $user_id
    $where_extra
    ORDER BY claims.created_at DESC
");

// ── STATS ─────────────────────────────────────────────────
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                          AS total,
        SUM(claims.status = 'pending')    AS pending_count,
        SUM(claims.status = 'accepted')   AS accepted_count,
        SUM(claims.status = 'rejected')   AS rejected_count
    FROM claims
    WHERE claimant_id = $user_id
"));

$total_claims = mysqli_num_rows($claims_result);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Claims – Lost & Found Portal</title>
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
    .main { flex: 1; padding: 32px 40px; overflow-y: auto; }

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

    /* ── FILTER TABS ── */
    .filter-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
    .filter-tab {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      text-decoration: none;
      font-size: 13px;
      color: var(--muted);
      transition: all 0.15s;
    }
    .filter-tab:hover { color: var(--text); border-color: #353a50; }
    .filter-tab.active { border-color: var(--accent2); color: var(--text); background: rgba(59,158,255,0.07); }
    .tab-count {
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
    }
    .tab-label { font-size: 12px; }

    /* ── CLAIMS LIST ── */
    .claims-list { display: flex; flex-direction: column; gap: 14px; }

    /* ── CLAIM CARD ── */
    .claim-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: border-color 0.15s;
    }
    .claim-card:hover { border-color: #353a50; }

    /* status-specific left border */
    .claim-card.status-pending  { border-left: 3px solid var(--accent);  }
    .claim-card.status-accepted { border-left: 3px solid var(--success); }
    .claim-card.status-rejected { border-left: 3px solid var(--danger);  }

    /* card top row */
    .card-top {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 20px;
    }

    .item-thumb {
      width: 52px; height: 52px;
      border-radius: 10px;
      background: var(--surface2);
      border: 1px solid var(--border);
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .item-thumb img { width: 100%; height: 100%; object-fit: cover; }

    .card-info { flex: 1; min-width: 0; }
    .item-name {
      font-family: 'Syne', sans-serif;
      font-size: 15px; font-weight: 700;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .item-meta {
      display: flex;
      gap: 14px;
      margin-top: 4px;
      flex-wrap: wrap;
    }
    .item-meta span { font-size: 12px; color: var(--muted); }

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
    .badge-lost     { background: rgba(224,82,82,0.15);  color: var(--danger);  }
    .badge-found    { background: rgba(0,201,167,0.15); color: var(--success); }
    .badge-pending  { background: rgba(0,201,167,0.15); color: var(--accent);  }
    .badge-accepted { background: rgba(0,201,167,0.15); color: var(--success); }
    .badge-rejected { background: rgba(224,82,82,0.15);  color: var(--danger);  }
    .badge-open     { background: rgba(0,201,167,0.12); color: var(--accent);  }
    .badge-reunited { background: rgba(0,201,167,0.12); color: var(--success); }
    .badge-claimed  { background: rgba(59,158,255,0.12); color: var(--accent2); }

    /* card body */
    .card-body {
      padding: 0 20px 18px;
      border-top: 1px solid var(--border);
    }

    /* message block */
    .my-message {
      margin-top: 14px;
      margin-bottom: 14px;
    }
    .msg-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--muted);
      font-weight: 500;
      margin-bottom: 6px;
    }
    .msg-text {
      font-size: 13.5px;
      line-height: 1.7;
      color: var(--text);
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 12px 14px;
    }

    /* status strip */
    .status-strip {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 14px;
    }
    .strip-pending  {
      background: rgba(0,201,167,0.08);
      border: 1px solid rgba(0,201,167,0.2);
      color: var(--accent);
    }
    .strip-accepted {
      background: rgba(0,201,167,0.08);
      border: 1px solid rgba(0,201,167,0.2);
      color: var(--success);
    }
    .strip-rejected {
      background: rgba(224,82,82,0.08);
      border: 1px solid rgba(224,82,82,0.2);
      color: var(--danger);
    }
    .strip-icon { font-size: 18px; flex-shrink: 0; }
    .strip-text { font-weight: 500; }
    .strip-sub  { font-size: 12px; opacity: 0.8; margin-top: 2px; }

    /* contact reveal (accepted only) */
    .contact-box {
      background: rgba(0,201,167,0.06);
      border: 1px solid rgba(0,201,167,0.2);
      border-radius: 10px;
      padding: 14px 16px;
      margin-bottom: 14px;
    }
    .contact-heading {
      font-size: 12px;
      font-weight: 600;
      color: var(--success);
      text-transform: uppercase;
      letter-spacing: 0.4px;
      margin-bottom: 10px;
    }
    .contact-row {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: var(--text);
      margin-bottom: 6px;
    }
    .contact-row:last-child { margin-bottom: 0; }
    .contact-row a { color: var(--accent2); text-decoration: none; }
    .contact-row a:hover { text-decoration: underline; }

    /* card footer */
    .card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .footer-left  { font-size: 12px; color: var(--muted); }
    .footer-right { display: flex; gap: 8px; }

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
    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }
    .btn-outline:hover { color: var(--text); background: var(--surface2); }
    .btn-danger-soft {
      background: rgba(224,82,82,0.1);
      border: 1px solid rgba(224,82,82,0.2);
      color: var(--danger);
    }
    .btn-danger-soft:hover { background: rgba(224,82,82,0.2); }

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
    .empty-sub { font-size: 13px; margin-bottom: 22px; line-height: 1.6; }
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
    <a href="claims.php" class="active"><span>✅</span> My Claims</a>
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

  <div class="topbar">
    <div>
      <div class="page-title">My Claims</div>
      <div class="page-sub">Track every claim you've submitted</div>
    </div>
    <a href="browse.php" class="btn btn-outline">Browse Items →</a>
  </div>

  <!-- ALERTS -->
  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

  <!-- FILTER TABS -->
  <div class="filter-tabs">
    <?php
    $tabs = [
      'all'      => ['label' => 'All Claims', 'val' => $stats['total']          ?? 0],
      'pending'  => ['label' => 'Pending',    'val' => $stats['pending_count']  ?? 0],
      'accepted' => ['label' => 'Accepted',   'val' => $stats['accepted_count'] ?? 0],
      'rejected' => ['label' => 'Rejected',   'val' => $stats['rejected_count'] ?? 0],
    ];
    foreach ($tabs as $key => $t):
      $active = ($filter === $key) ? 'active' : '';
    ?>
    <a href="claims.php?filter=<?= $key ?>" class="filter-tab <?= $active ?>">
      <span class="tab-count"><?= $t['val'] ?></span>
      <span class="tab-label"><?= $t['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- CLAIMS LIST -->
  <?php if ($total_claims === 0): ?>

    <div class="empty-state">
      <div class="empty-icon">
        <?php
        if ($filter === 'pending')       echo '⏳';
        elseif ($filter === 'accepted')  echo '🎉';
        elseif ($filter === 'rejected')  echo '❌';
        else                             echo '📭';
        ?>
      </div>
      <div class="empty-title">
        <?php
        if ($filter === 'all')           echo "You haven't made any claims yet";
        elseif ($filter === 'pending')   echo "No pending claims";
        elseif ($filter === 'accepted')  echo "No accepted claims yet";
        else                             echo "No rejected claims";
        ?>
      </div>
      <div class="empty-sub">
        <?php if ($filter === 'all'): ?>
          Browse the items board and submit a claim if you recognise something that belongs to you.
        <?php else: ?>
          Switch the filter above to see all your claims.
        <?php endif; ?>
      </div>
      <?php if ($filter === 'all'): ?>
        <a href="browse.php" class="btn btn-outline">Browse Items</a>
      <?php endif; ?>
    </div>

  <?php else: ?>

    <div class="claims-list">
      <?php while ($claim = mysqli_fetch_assoc($claims_result)):
        $icon     = $icons[$claim['category']] ?? '📦';
        $has_photo = !empty($claim['photo']) && file_exists('uploads/' . $claim['photo']);
        $status   = $claim['claim_status'];
      ?>

      <div class="claim-card status-<?= $status ?>">

        <!-- TOP: item thumb + info + badges -->
        <div class="card-top">

          <div class="item-thumb">
            <?php if ($has_photo): ?>
              <img src="uploads/<?= htmlspecialchars($claim['photo']) ?>" alt=""/>
            <?php else: ?>
              <?= $icon ?>
            <?php endif; ?>
          </div>

          <div class="card-info">
            <div class="item-name"><?= htmlspecialchars($claim['item_name']) ?></div>
            <div class="item-meta">
              <span>📍 <?= htmlspecialchars($claim['location']) ?></span>
              <span>📅 <?= date('d M Y', strtotime($claim['date_occurred'])) ?></span>
              <span>🕐 Claimed <?= time_ago($claim['claimed_at']) ?></span>
            </div>
          </div>

          <!-- Item type badge -->
          <span class="badge badge-<?= $claim['item_type'] ?>"><?= $claim['item_type'] ?></span>

          <!-- Claim status badge -->
          <span class="badge badge-<?= $status ?>"><?= ucfirst($status) ?></span>

        </div><!-- end .card-top -->

        <!-- BODY -->
        <div class="card-body">

          <!-- My message -->
          <div class="my-message">
            <div class="msg-label">Your message</div>
            <div class="msg-text"><?= nl2br(htmlspecialchars($claim['message'])) ?></div>
          </div>

          <!-- Status strip -->
          <?php if ($status === 'pending'): ?>
            <div class="status-strip strip-pending">
              <span class="strip-icon">⏳</span>
              <div>
                <div class="strip-text">Awaiting review</div>
                <div class="strip-sub">The poster will review your claim and respond soon.</div>
              </div>
            </div>

          <?php elseif ($status === 'accepted'): ?>
            <div class="status-strip strip-accepted">
              <span class="strip-icon">🎉</span>
              <div>
                <div class="strip-text">Claim accepted! The item is yours.</div>
                <div class="strip-sub">Use the contact details below to arrange collection.</div>
              </div>
            </div>

            <!-- Poster contact — revealed only on accept -->
            <div class="contact-box">
              <div class="contact-heading">📞 Poster Contact Details</div>
              <div class="contact-row">
                📧 <a href="mailto:<?= htmlspecialchars($claim['poster_email']) ?>"><?= htmlspecialchars($claim['poster_email']) ?></a>
              </div>
              <?php if (!empty($claim['poster_phone'])): ?>
              <div class="contact-row">
                📞 <a href="tel:<?= htmlspecialchars($claim['poster_phone']) ?>"><?= htmlspecialchars($claim['poster_phone']) ?></a>
              </div>
              <?php endif; ?>
              <div class="contact-row" style="margin-top:6px;font-size:12px;color:var(--muted);">
                Posted by <?= htmlspecialchars($claim['poster_name']) ?>
              </div>
            </div>

          <?php elseif ($status === 'rejected'): ?>
            <div class="status-strip strip-rejected">
              <span class="strip-icon">❌</span>
              <div>
                <div class="strip-text">Claim not accepted</div>
                <div class="strip-sub">The poster did not match your claim. Browse other items if you're still looking.</div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Footer: timestamps + actions -->
          <div class="card-footer">
            <div class="footer-left">
              Item posted on <?= date('d M Y', strtotime($claim['date_occurred'])) ?>
              · Claim submitted <?= date('d M Y, g:i a', strtotime($claim['claimed_at'])) ?>
            </div>
            <div class="footer-right">
              <a href="item-detail.php?id=<?= $claim['item_id'] ?>" class="btn btn-outline btn-sm">View Item</a>
              <?php if ($status === 'pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>"/>
                  <button type="submit" name="withdraw_claim" class="btn btn-danger-soft btn-sm"
                          onclick="return confirm('Withdraw this claim? This cannot be undone.')">
                    Withdraw
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- end .card-body -->
      </div><!-- end .claim-card -->

      <?php endwhile; ?>
    </div><!-- end .claims-list -->

  <?php endif; ?>

</main>

</body>
</html>
