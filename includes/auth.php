<?php
// ── AUTH GUARD ────────────────────────────────────────────
// Include this file on any page that requires a logged-in user.
// Usage: require 'includes/auth.php';  (after session_start and db.php)
//
// For admin-only pages use:
//   require 'includes/auth.php';
//   require_admin();

/**
 * Redirect to login if user is not logged in.
 * Call this on every protected user page.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . get_base_url() . "index.php");
        exit();
    }
}

/**
 * Redirect to dashboard if user is not an admin.
 * Call this on every admin-only page (after require_login).
 */
function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: " . get_base_url() . "dashboard.php");
        exit();
    }
}

/**
 * Returns true if a user is currently logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Returns true if the logged-in user is an admin.
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Returns the current logged-in user's ID, or null.
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Returns the current logged-in user's name, or empty string.
 */
function current_user_name() {
    return $_SESSION['user_name'] ?? '';
}

/**
 * Detects the base URL so redirects work whether
 * the file is in root or in admin/ subfolder.
 */
function get_base_url() {
    // Walk up until we find index.php in the directory
    $depth = 0;
    $dir   = __DIR__;
    while ($depth < 3) {
        if (file_exists($dir . '/index.php')) {
            // Found root — build relative prefix
            return str_repeat('../', $depth);
        }
        $dir = dirname($dir);
        $depth++;
    }
    return '';
}
?>
