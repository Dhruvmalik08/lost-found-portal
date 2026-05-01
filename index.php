<?php
session_start();
require 'includes/db.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error   = "";
$success = "";
$mode    = "login"; // default tab

// ───────────────────────────────────────────
// SIGNUP
// ───────────────────────────────────────────
if (isset($_POST['signup'])) {
    $mode  = "signup";
    $name  = trim(mysqli_real_escape_string($conn, $_POST['name']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $pass  = $_POST['password'];

    if (empty($name) || empty($email) || empty($pass)) {
        $error = "All fields are required.";
    } elseif (strlen($pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if email already exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Email is already registered.";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password) VALUES ('$name', '$email', '$hashed')";
            if (mysqli_query($conn, $sql)) {
                $success = "Account created! You can now log in.";
                $mode = "login";
            } else {
                $error = "Something went wrong. Try again.";
            }
        }
    }
}

// ───────────────────────────────────────────
// LOGIN
// ───────────────────────────────────────────
if (isset($_POST['login'])) {
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $pass  = $_POST['password'];

    if (empty($email) || empty($pass)) {
        $error = "Please fill in all fields.";
    } else {
        $sql    = "SELECT * FROM users WHERE email='$email'";
        $result = mysqli_query($conn, $sql);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Lost & Found Portal – Login</title>
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
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* subtle grid bg */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(var(--border) 1px, transparent 1px),
        linear-gradient(90deg, var(--border) 1px, transparent 1px);
      background-size: 40px 40px;
      opacity: 0.35;
      pointer-events: none;
    }

    .wrapper {
      display: flex;
      width: 860px;
      min-height: 520px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      overflow: hidden;
      position: relative;
      z-index: 1;
      box-shadow: 0 24px 80px rgba(0,8,30,0.7);
    }

    /* LEFT PANEL */
    .left-panel {
      width: 340px;
      background: linear-gradient(160deg, #112044 0%, #0a0f1e 100%);
      border-right: 1px solid var(--border);
      padding: 52px 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      flex-shrink: 0;
    }

    .brand {
      font-family: 'Syne', sans-serif;
      font-size: 26px;
      font-weight: 800;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .brand span { color: var(--text); }

    .tagline {
      font-size: 13px;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 40px;
    }

    .feature-list { list-style: none; }
    .feature-list li {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13.5px;
      color: var(--muted);
      margin-bottom: 16px;
    }
    .feature-list li .dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
    }

    .stats-row {
      display: flex;
      gap: 20px;
      margin-top: auto;
    }
    .mini-stat { text-align: center; }
    .mini-stat .val {
      font-family: 'Syne', sans-serif;
      font-size: 22px;
      font-weight: 700;
      color: var(--text);
    }
    .mini-stat .lbl { font-size: 11px; color: var(--muted); margin-top: 2px; }

    /* RIGHT PANEL */
    .right-panel {
      flex: 1;
      padding: 52px 44px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    /* TABS */
    .tabs {
      display: flex;
      gap: 4px;
      background: var(--surface2);
      border-radius: 9px;
      padding: 4px;
      margin-bottom: 32px;
      width: fit-content;
    }
    .tab {
      padding: 8px 24px;
      border-radius: 7px;
      font-size: 13.5px;
      font-weight: 500;
      cursor: pointer;
      border: none;
      background: transparent;
      color: var(--muted);
      font-family: 'DM Sans', sans-serif;
      transition: all 0.18s;
    }
    .tab.active {
      background: var(--surface);
      color: var(--text);
      border: 1px solid var(--border);
    }

    /* FORMS */
    .form-section { display: none; }
    .form-section.visible { display: block; }

    .form-title {
      font-family: 'Syne', sans-serif;
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 6px;
    }
    .form-sub { font-size: 13px; color: var(--muted); margin-bottom: 28px; }

    .form-group { margin-bottom: 18px; }
    label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 7px;
    }
    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 11px 14px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      transition: border-color 0.18s;
    }
    input:focus { border-color: var(--accent2); }
    input::placeholder { color: var(--muted); }

    .btn-submit {
      width: 100%;
      padding: 12px;
      background: var(--accent);
      color: #0a0f1e;
      font-size: 14px;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 6px;
      transition: background 0.18s;
      letter-spacing: 0.2px;
    }
    .btn-submit:hover { background: #33d4b7; }

    /* ALERTS */
    .alert {
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 20px;
      font-weight: 500;
    }
    .alert-error   { background: rgba(224,82,82,0.12);  color: var(--danger);  border: 1px solid rgba(224,82,82,0.25); }
    .alert-success { background: rgba(0,201,167,0.12); color: var(--success); border: 1px solid rgba(0,201,167,0.25); }
  </style>
</head>
<body>

<div class="wrapper">

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div>
      <div class="brand">Lost<span>&Found</span></div>
      <p class="tagline">A community portal to reunite people with their lost belongings.</p>
      <ul class="feature-list">
        <li><span class="dot"></span> Post lost or found items instantly</li>
        <li><span class="dot"></span> Photo upload for easy identification</li>
        <li><span class="dot"></span> Claim system with contact info</li>
        <li><span class="dot"></span> Mark items as reunited</li>
        <li><span class="dot"></span> Browse & filter by category</li>
      </ul>
    </div>
    <div class="stats-row">
      <div class="mini-stat">
        <div class="val">142</div>
        <div class="lbl">Lost Reports</div>
      </div>
      <div class="mini-stat">
        <div class="val">63</div>
        <div class="lbl">Reunited</div>
      </div>
      <div class="mini-stat">
        <div class="val">98</div>
        <div class="lbl">Found Items</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">

    <!-- TABS -->
    <div class="tabs">
      <button class="tab <?= $mode === 'login'  ? 'active' : '' ?>" onclick="switchTab('login')">Log In</button>
      <button class="tab <?= $mode === 'signup' ? 'active' : '' ?>" onclick="switchTab('signup')">Sign Up</button>
    </div>

    <!-- ALERTS -->
    <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <!-- LOGIN FORM -->
    <div class="form-section <?= $mode === 'login' ? 'visible' : '' ?>" id="loginForm">
      <div class="form-title">Welcome back</div>
      <div class="form-sub">Log in to manage your posts and claims</div>
      <form method="POST" action="">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@example.com" required />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required />
        </div>
        <button type="submit" name="login" class="btn-submit">Log In →</button>
      </form>
    </div>

    <!-- SIGNUP FORM -->
    <div class="form-section <?= $mode === 'signup' ? 'visible' : '' ?>" id="signupForm">
      <div class="form-title">Create an account</div>
      <div class="form-sub">Join the community and help reunite lost items</div>
      <form method="POST" action="">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" placeholder="Your name" required />
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@example.com" required />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min. 6 characters" required />
        </div>
        <button type="submit" name="signup" class="btn-submit">Create Account →</button>
      </form>
    </div>

  </div>
</div>

<script>
  function switchTab(tab) {
    document.getElementById('loginForm').classList.toggle('visible', tab === 'login');
    document.getElementById('signupForm').classList.toggle('visible', tab === 'signup');
    document.querySelectorAll('.tab').forEach((t, i) => {
      t.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'signup'));
    });
  }
</script>

</body>
</html>
