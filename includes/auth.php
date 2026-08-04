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
// JMC Digital: one login, two modules (Wellness / Basics). A user can have
// either, both, or (mid-application) neither yet.
// ---------------------------------------------------------------
function user_module_access($conn, $user_id) {
    $stmt = $conn->prepare("SELECT u.status, u.wellness_enrolled,
                                    bm.application_status AS basics_app,
                                    bm.membership_status AS basics_mem
                             FROM users u
                             LEFT JOIN basics_members bm ON bm.user_id = u.id
                             WHERE u.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['wellness' => false, 'wellness_pending' => false, 'basics' => false, 'basics_pending' => false, 'basics_blocked' => false];
    }

    return [
        'wellness'         => (bool) $row['wellness_enrolled'] && $row['status'] === 'active',
        'wellness_pending' => (bool) $row['wellness_enrolled'] && $row['status'] === 'pending',
        'basics'           => $row['basics_app'] === 'approved' && in_array($row['basics_mem'], ['active', 'dormant'], true),
        'basics_pending'   => $row['basics_app'] === 'pending',
        'basics_blocked'   => in_array($row['basics_mem'], ['suspended', 'terminated'], true) || $row['basics_app'] === 'denied',
    ];
}

// Use on every Wellness page instead of require_login().
function require_wellness_access($conn) {
    require_login($conn);
    if (!user_module_access($conn, current_user_id())['wellness']) {
        redirect('/index.php');
    }
}

// Use on every Basics page instead of require_login().
function require_basics_access($conn) {
    require_login($conn);
    $access = user_module_access($conn, current_user_id());
    if ($access['basics_pending'] || $access['basics_blocked']) {
        redirect('/basics/pending.php');
    }
    if (!$access['basics']) {
        redirect('/index.php');
    }
}

// Where to send someone right after a successful login (or when they land on
// the hub already logged in). Both modules -> hub (let them choose). Exactly
// one -> straight into it. Basics still pending/blocked -> its holding page.
function route_after_login($conn, $user_id) {
    $access = user_module_access($conn, $user_id);
    if ($access['wellness'] && $access['basics']) {
        return '/index.php';
    }
    if ($access['wellness']) {
        return '/wellness/dashboard.php';
    }
    if ($access['basics']) {
        return '/basics/dashboard.php';
    }
    if ($access['basics_pending'] || $access['basics_blocked']) {
        return '/basics/pending.php';
    }
    return '/index.php';
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
