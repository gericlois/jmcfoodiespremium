<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------
// JMC Foodies Wellness — member auth (users table)
// Wellness and Basics are fully separate account systems: separate tables,
// separate login pages, separate sessions. See the "Basics — member auth"
// section below for its parallel. There is no shared login between them.
// ---------------------------------------------------------------
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Use on every Wellness user-facing page except change_password.php and logout.php.
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

// Kept as the name every wellness/*.php page already calls — Wellness no
// longer shares its account table with Basics, so this is now just
// require_login() under its established name (avoids touching every caller).
function require_wellness_access($conn) {
    require_login($conn);
}

// Every Wellness login now goes straight to the Wellness dashboard — kept as
// a named function (rather than inlining the path at each call site) since
// login.php/register.php/change_password.php all call it.
function route_after_login($conn, $user_id) {
    return '/wellness/dashboard.php';
}

// ---------------------------------------------------------------
// JMC Foodies Wellness — admin auth (admins table)
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

// ---------------------------------------------------------------
// JMC Foodies Basics — member auth (basics_users table). Independent
// session keys from Wellness's, so the two can never collide.
// ---------------------------------------------------------------
function basics_is_logged_in() {
    return isset($_SESSION['basics_user_id']);
}

function basics_current_user_id() {
    return $_SESSION['basics_user_id'] ?? null;
}

function require_basics_login($conn) {
    if (!basics_is_logged_in()) {
        redirect('/basics/login.php');
    }
    require_valid_basics_session_user($conn);
    if (!empty($_SESSION['basics_must_change_password'])) {
        redirect('/basics/change_password.php');
    }
}

function require_basics_login_only($conn) {
    if (!basics_is_logged_in()) {
        redirect('/basics/login.php');
    }
    require_valid_basics_session_user($conn);
}

function require_valid_basics_session_user($conn) {
    $stmt = $conn->prepare("SELECT id FROM basics_users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['basics_user_id']);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) {
        session_unset();
        session_destroy();
        redirect('/basics/login.php');
    }
}

// Use on every Basics member page instead of require_basics_login() alone —
// also redirects to the holding page while the membership application is
// still pending, denied, suspended, or terminated.
function require_basics_access($conn) {
    require_basics_login($conn);
    $member = basics_get_member($conn, basics_current_user_id());
    if (!$member) {
        redirect('/basics/apply.php');
    }
    if ($member['application_status'] !== 'approved' || !in_array($member['membership_status'], ['active', 'dormant'], true)) {
        redirect('/basics/pending.php');
    }
}

// ---------------------------------------------------------------
// JMC Foodies Basics — admin auth (basics_admins table), fully separate
// from the Wellness admin login/session above.
// ---------------------------------------------------------------
function basics_is_admin_logged_in() {
    return isset($_SESSION['basics_admin_id']);
}

function basics_current_admin_id() {
    return $_SESSION['basics_admin_id'] ?? null;
}

function require_basics_admin_login() {
    if (!basics_is_admin_logged_in()) {
        redirect('/basics/admin/login.php');
    }
}

function basics_admin_role() {
    return $_SESSION['basics_admin_role'] ?? 'super_admin';
}

// Where each role lands after login, and where a permission-denied redirect
// sends them — must be a page that role can actually open, or a denied
// staff_payments admin bouncing off a staff_orders-only page (or vice versa)
// would redirect-loop forever.
function basics_admin_landing_url() {
    switch (basics_admin_role()) {
        case 'staff_orders':
            return '/basics/admin/applications.php';
        case 'staff_payments':
            return '/basics/admin/payments.php';
        default:
            return '/basics/admin/index.php';
    }
}

// staff_orders and staff_payments are restricted roles, each scoped to its
// own slice of the admin (orders/applications vs payments/benefits).
// Everything else (members, settings, etc.) is super_admin-only. Pages call
// this instead of require_basics_admin_login() when they should be
// off-limits to one or both staff roles.
function require_basics_admin_role(array $allowed_roles) {
    require_basics_admin_login();
    if (!in_array(basics_admin_role(), $allowed_roles, true)) {
        redirect(basics_admin_landing_url());
    }
}
