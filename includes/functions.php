<?php
function format_price($amount) {
    return '₱' . number_format((float) $amount, 2);
}

function sanitize($value) {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

// ---------------------------------------------------------------
// Editable business settings (admin/settings.php), stored as a simple
// key-value table instead of hardcoded constants so they can change
// without a code deploy. Cached per-request (one query no matter how many
// times individual keys are read on a page).
// ---------------------------------------------------------------
function setting($conn, $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

// ---------------------------------------------------------------
// Validates and saves an uploaded image to uploads/products/, deleting the
// old file if one is replaced. Returns [filename_to_store, error_or_null].
// $existing_filename is returned unchanged if no new file was uploaded.
// ---------------------------------------------------------------
function handle_product_image_upload($file_key, $existing_filename) {
    if (empty($_FILES[$file_key]['name'])) {
        return [$existing_filename, null];
    }

    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
    finfo_close($finfo);

    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return [$existing_filename, 'Image upload failed.'];
    }
    if ($_FILES[$file_key]['size'] > 2 * 1024 * 1024) {
        return [$existing_filename, 'Image must be smaller than 2MB.'];
    }
    if (!isset($allowed_types[$mime])) {
        return [$existing_filename, 'Image must be a JPG, PNG, or WEBP file.'];
    }

    $new_filename = bin2hex(random_bytes(8)) . '.' . $allowed_types[$mime];
    $dest = UPLOAD_PATH . 'products/' . $new_filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $dest)) {
        return [$existing_filename, 'Failed to save uploaded image.'];
    }

    if ($existing_filename && is_file(UPLOAD_PATH . 'products/' . $existing_filename)) {
        unlink(UPLOAD_PATH . 'products/' . $existing_filename);
    }
    return [$new_filename, null];
}

// ---------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------
// Sent when an admin approves a pending registration. Uses PHP's mail()
// (needs a working sendmail/SMTP relay on the server to actually deliver —
// on a bare XAMPP install this call will silently fail, which is fine
// during local dev, but should be verified once this goes live).
function send_account_approved_email($to_email, $full_name) {
    if (empty($to_email)) {
        return false;
    }
    $subject = 'Your ' . APP_NAME . ' account has been confirmed';
    $message = "Hi {$full_name},\r\n\r\n"
        . 'Good news! Your ' . APP_NAME . " account has been reviewed and confirmed by our team.\r\n"
        . "You can now log in and start earning.\r\n\r\n"
        . 'Log in here: ' . BASE_URL . "/login.php\r\n\r\n"
        . '— ' . APP_NAME . ' Team';
    $headers = 'From: ' . APP_NAME . ' <no-reply@' . preg_replace('/^www\./', '', parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost') . ">\r\n"
        . 'Content-Type: text/plain; charset=UTF-8';
    return mail($to_email, $subject, $message, $headers);
}

// Used by both admin/users.php and admin/user_view.php. Emails the user
// only on a pending -> active transition (i.e. an actual approval), not on
// suspend/reinstate of an already-active account.
function update_user_status($conn, $id, $new_status) {
    $allowed_statuses = ['pending', 'active', 'suspended'];
    if (!in_array($new_status, $allowed_statuses, true)) {
        $new_status = 'active';
    }

    $stmt = $conn->prepare("SELECT status, email, full_name FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return;
    }

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $new_status, $id);
    $stmt->execute();
    $stmt->close();

    if ($user['status'] === 'pending' && $new_status === 'active') {
        send_account_approved_email($user['email'], $user['full_name']);
    }
}

// ---------------------------------------------------------------
// Referral codes
// ---------------------------------------------------------------
function generate_referral_code($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I to avoid confusion
    do {
        $code = 'JMC-';
        for ($i = 0; $i < 9; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } while ($exists);
    return $code;
}

function referral_link($code) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . BASE_URL . '/register.php?ref=' . urlencode($code);
}

// ---------------------------------------------------------------
// Wallet ledger
// ---------------------------------------------------------------
function wallet_balance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS balance FROM wallet_transactions WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $balance = $stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();
    return (float) $balance;
}

function wallet_sum_by_type($conn, $user_id, $type) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM wallet_transactions WHERE user_id = ? AND type = ?");
    $stmt->bind_param('is', $user_id, $type);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return (float) $total;
}

function wallet_credit($conn, $user_id, $type, $amount, $order_id = null, $cashout_id = null, $description = null) {
    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, reference_order_id, reference_cashout_id, description)
                             VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isdiis', $user_id, $type, $amount, $order_id, $cashout_id, $description);
    $stmt->execute();
    $stmt->close();
}

// ---------------------------------------------------------------
// Order lifecycle: pending -> processing -> completed (or cancelled from either).
// Rewards only credit at "delivered" (completed) — matches the program manual's
// Payment Confirmation -> Order Processing -> Successful Delivery -> Reward Crediting flow.
// ---------------------------------------------------------------

// GCash orders only: admin confirms payment was received. No wallet effect yet.
function confirm_order_payment($conn, $order_id) {
    $stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();
}

// Admin marks the order delivered. This is the only place rewards are credited.
function mark_order_delivered($conn, $order_id, $admin_id) {
    $stmt = $conn->prepare("SELECT o.*, u.referred_by FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ? FOR UPDATE");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || $order['status'] !== 'processing') {
        return;
    }

    $rebate_rate = (float) setting($conn, 'personal_rebate_rate', 0.20);
    $override_rate = (float) setting($conn, 'referral_override_rate', 0.10);

    $rebate = round($order['total_amount'] * $rebate_rate, 2);
    wallet_credit($conn, $order['user_id'], 'personal_rebate', $rebate, $order_id, null,
        'Personal rebate (' . (int) ($rebate_rate * 100) . '%) on order #' . $order_id);

    if (!empty($order['referred_by'])) {
        $override = round($order['total_amount'] * $override_rate, 2);
        wallet_credit($conn, $order['referred_by'], 'referral_override', $override, $order_id, null,
            'Referral override (' . (int) ($override_rate * 100) . '%) on order #' . $order_id . ' by your direct referral');
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'completed', confirmed_at = NOW(), confirmed_by = ? WHERE id = ?");
    $stmt->bind_param('ii', $admin_id, $order_id);
    $stmt->execute();
    $stmt->close();
}

// Cancels a pending/processing order. Refunds the wallet debit if it was a
// wallet-paid order (money already left the wallet before delivery).
function cancel_order($conn, $order_id) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('pending', 'processing') FOR UPDATE");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return;
    }

    if ($order['payment_method'] === 'wallet') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM wallet_transactions WHERE reference_order_id = ? AND type = 'purchase_refund'");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $already_refunded = (int) $stmt->get_result()->fetch_assoc()['c'] > 0;
        $stmt->close();

        if (!$already_refunded) {
            wallet_credit($conn, $order['user_id'], 'purchase_refund', $order['total_amount'], $order_id, null,
                'Refund for cancelled order #' . $order_id);
        }
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();
}
