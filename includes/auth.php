<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------
// User auth
// ---------------------------------------------------------------
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Use on every user-facing page except change_password.php and logout.php.
// Also enforces the forced-password-change gate.
function require_login($conn) {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
    require_valid_session_user($conn);
    if (!empty($_SESSION['must_change_password'])) {
        redirect('/change_password.php');
    }
}

// Use only on change_password.php: requires login but does not loop back into itself.
function require_login_only($conn) {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
    require_valid_session_user($conn);
}

// A session can outlive its user row (e.g. a database restore, or an admin
// deleting the account) — without this, pages crash on null-array warnings
// instead of just sending the visitor back to login.
function require_valid_session_user($conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) {
        session_unset();
        session_destroy();
        redirect('/login.php');
    }
}

// ---------------------------------------------------------------
// Admin auth
// ---------------------------------------------------------------
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

function current_admin_id() {
    return $_SESSION['admin_id'] ?? null;
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        redirect('/admin/login.php');
    }
}
