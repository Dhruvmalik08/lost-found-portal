<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$error     = "";
$success   = "";

if (isset($_POST['submit'])) {
    $name        = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $description = trim(mysqli_real_escape_string($conn, $_POST['description']));
    $category    = trim(mysqli_real_escape_string($conn, $_POST['category']));
    $location    = trim(mysqli_real_escape_string($conn, $_POST['location']));
    $date        = $_POST['date_occurred'];
    $photo_name  = "";

    if (empty($name) || empty($category) || empty($location) || empty($date)) {
        $error = "Please fill in all required fields.";
    } else {

        if (!empty($_FILES['photo']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, PNG, GIF, WEBP images are allowed.";
            } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $error = "Image must be under 5MB.";
            } else {
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                $photo_name = uniqid('item_', true) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $photo_name);
            }
        }

        if (empty($error)) {
            $sql = "INSERT INTO items (user_id, type, name, description, category, location, date_occurred, photo)
                    VALUES ('$user_id', 'found', '$name', '$description', '$category', '$location', '$date', '$photo_name')";
            if (mysqli_query($conn, $sql)) {
                $success = "Found item posted successfully! Someone will reach out if it's theirs.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Post Found Item – Lost & Found Portal</title>
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
      color: var(--success);
      border-left-color: var(--success);
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
    .main { flex: 1; padding: 36px 48px; max-width: 780px; }

    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 22px; font-weight: 700;
      margin-bottom: 4px;
    }
    .page-sub { font-size: 13px; color: var(--muted); margin-bottom: 32px; }

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

    /* ── FORM CARD ── */
    .form-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 32px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .form-group { display: flex; flex-direction: column; gap: 7px; }
    .form-group.full { grid-column: 1 / -1; }

    label {
      font-size: 12px;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .required { color: var(--danger); margin-left: 2px; }

    input[type="text"],
    input[type="date"],
    select,
    textarea {
      width: 100%;
      padding: 11px 14px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      transition: border-color 0.15s;
      appearance: none;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--success); }
    input::placeholder, textarea::placeholder { color: var(--muted); }
    textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
    select option { background: var(--surface2); }

    /* ── PHOTO UPLOAD ── */
    .upload-zone {
      border: 2px dashed var(--border);
      border-radius: 10px;
      padding: 28px;
      text-align: center;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      position: relative;
    }
    .upload-zone:hover { border-color: var(--success); background: rgba(0,201,167,0.04); }
    .upload-zone input[type="file"] {
      position: absolute; inset: 0;
      opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .upload-icon { font-size: 28px; margin-bottom: 8px; }
    .upload-text { font-size: 13px; color: var(--muted); }
    .upload-text strong { color: var(--success); }

    #preview-wrap { display: none; margin-top: 14px; }
    #preview-wrap img {
      max-height: 180px;
      border-radius: 8px;
      border: 1px solid var(--border);
      object-fit: cover;
    }
    #preview-name { font-size: 12px; color: var(--muted); margin-top: 6px; }

    .divider { border: none; border-top: 1px solid var(--border); margin: 28px 0; }

    /* ── BUTTONS ── */
    .form-actions { display: flex; gap: 12px; align-items: center; }
    .btn {
      padding: 11px 24px;
      border-radius: 8px;
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      cursor: pointer;
      border: none;
      text-decoration: none;
      display: inline-block;
      transition: all 0.15s;
    }
    .btn-success { background: var(--success); color: #0a0f1e; }
    .btn-success:hover { background: #33d4b7; }
    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
    }
    .btn-outline:hover { background: var(--surface2); }
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
    <a href="post-found.php" class="active"><span>📦</span> Post Found Item</a>
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
  <div class="page-title">Post a Found Item</div>
  <div class="page-sub">Help someone find what they lost — fill in the details below.</div>

  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="browse.php" style="color:inherit;text-decoration:underline;">Browse all items →</a></div><?php endif; ?>

  <div class="form-card">
    <form method="POST" action="" enctype="multipart/form-data">

      <div class="form-grid">

        <!-- Item Name -->
        <div class="form-group">
          <label>Item Name <span class="required">*</span></label>
          <input type="text" name="name" placeholder="e.g. Blue water bottle" required
                 value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"/>
        </div>

        <!-- Category -->
        <div class="form-group">
          <label>Category <span class="required">*</span></label>
          <select name="category" required>
            <option value="" disabled selected>Select a category</option>
            <option value="Electronics">Electronics</option>
            <option value="Wallet">Wallet</option>
            <option value="Bag">Bag</option>
            <option value="Keys">Keys</option>
            <option value="ID">ID / Card</option>
            <option value="Clothing">Clothing</option>
            <option value="Jewellery">Jewellery</option>
            <option value="Books">Books</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <!-- Location -->
        <div class="form-group">
          <label>Where Did You Find It? <span class="required">*</span></label>
          <input type="text" name="location" placeholder="e.g. Canteen, near exit door"
                 value="<?= isset($_POST['location']) ? htmlspecialchars($_POST['location']) : '' ?>"/>
        </div>

        <!-- Date -->
        <div class="form-group">
          <label>Date Found <span class="required">*</span></label>
          <input type="date" name="date_occurred"
                 max="<?= date('Y-m-d') ?>"
                 value="<?= isset($_POST['date_occurred']) ? $_POST['date_occurred'] : date('Y-m-d') ?>"/>
        </div>

        <!-- Description -->
        <div class="form-group full">
          <label>Description</label>
          <textarea name="description" placeholder="Describe the item — colour, brand, size, any identifying marks..."><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
        </div>

        <!-- Photo Upload -->
        <div class="form-group full">
          <label>Photo <span style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;">(optional but recommended)</span></label>
          <div class="upload-zone">
            <input type="file" name="photo" id="photoInput" accept="image/*" onchange="previewPhoto(this)"/>
            <div class="upload-icon">📷</div>
            <div class="upload-text"><strong>Click to upload</strong> or drag and drop</div>
            <div class="upload-text" style="margin-top:4px;">JPG, PNG, WEBP · Max 5MB</div>
          </div>
          <div id="preview-wrap">
            <img id="preview-img" src="" alt="Preview"/>
            <div id="preview-name"></div>
          </div>
        </div>

      </div>

      <hr class="divider"/>

      <div class="form-actions">
        <button type="submit" name="submit" class="btn btn-success">Post Found Item</button>
        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
      </div>

    </form>
  </div>
</main>

<script>
  function previewPhoto(input) {
    const wrap = document.getElementById('preview-wrap');
    const img  = document.getElementById('preview-img');
    const name = document.getElementById('preview-name');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        img.src = e.target.result;
        name.textContent = input.files[0].name;
        wrap.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    }
  }
</script>

</body>
</html>
