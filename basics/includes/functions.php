<?php
// JMC Foodies Basics business logic: weekly ordering cycles, revolving
// credit-line checks, and the tiered late-payment penalty engine.

// The cycle currently open for placing orders (Mon-Thu window), if any.
// Always recomputed from today's date against the date columns — a stale
// `status` cache value can never mis-gate ordering.
function basics_active_order_cycle($conn) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT * FROM basics_cycles WHERE order_open_date <= ? AND order_cutoff_date >= ? ORDER BY order_open_date DESC LIMIT 1");
    $stmt->bind_param('ss', $today, $today);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $cycle ?: null;
}

function basics_get_member($conn, $user_id) {
    $stmt = $conn->prepare("SELECT bm.*, u.full_name, u.username, u.email, u.contact_number
                             FROM basics_members bm JOIN users u ON u.id = bm.user_id
                             WHERE bm.user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $member ?: null;
}

// Sum of `placed` orders that don't yet have a payment covering their full
// amount_due. A revolving ceiling, not a per-cycle reset — a member who
// hasn't paid down prior weeks simply can't order more.
function basics_outstanding_balance($conn, $member_id) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(o.total_amount), 0) AS outstanding
                             FROM basics_orders o
                             WHERE o.member_id = ? AND o.status = 'placed'
                               AND o.id NOT IN (
                                   SELECT p.order_id FROM basics_payments p
                                   WHERE p.order_id = o.id AND p.amount_paid >= o.total_amount
                               )");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float) $row['outstanding'];
}

function basics_credit_available($conn, $member) {
    $outstanding = basics_outstanding_balance($conn, $member['id']);
    return max(0, (float) $member['weekly_credit_limit'] - $outstanding);
}

// Government ID / clearance uploads. Deviates from handle_product_image_upload():
// accepts PDF too, and always saves under uploads/basics_kyc/, which is not
// publicly servable (see uploads/basics_kyc/.htaccess) — only ever read back
// through basics/admin/kyc_view.php.
function handle_kyc_document_upload($file_key) {
    if (empty($_FILES[$file_key]['name'])) {
        return [null, 'A file is required.'];
    }
    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Upload failed.'];
    }
    if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024) {
        return [null, 'File must be smaller than 5MB.'];
    }

    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed_types[$mime])) {
        return [null, 'File must be a JPG, PNG, WEBP, or PDF.'];
    }

    $new_filename = bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime];
    $dest = UPLOAD_PATH . 'basics_kyc/' . $new_filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $dest)) {
        return [null, 'Failed to save uploaded file.'];
    }
    return [$new_filename, null];
}

// Records a payment against a placed order, applying the late-payment
// penalty tier + credit-line/suspension escalation in one transaction.
// Returns ['is_late' => bool, 'penalty_amount' => float, 'membership_status' => string].
function basics_record_payment($conn, $order_id, $amount_paid, $paid_at, $admin_id, $notes = null) {
    $stmt = $conn->prepare("SELECT o.*, c.payment_due_date
                             FROM basics_orders o JOIN basics_cycles c ON c.id = o.cycle_id
                             WHERE o.id = ? AND o.status = 'placed' FOR UPDATE");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception('Order not found or not awaiting payment.');
    }

    $stmt = $conn->prepare("SELECT * FROM basics_members WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $order['member_id']);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $paid_date = date('Y-m-d', strtotime($paid_at));
    $is_late = $paid_date > $order['payment_due_date'];
    $amount_due = (float) $order['total_amount'];

    $penalty_rate = 0.0;
    $penalty_amount = 0.0;
    $offense_number = null;
    $new_offense_count = (int) $member['offense_count'];
    $new_on_time = (int) $member['consecutive_on_time_payments'];
    $new_frozen = (int) $member['credit_limit_frozen'];
    $new_status = $member['membership_status'];
    $new_suspended_until = $member['suspended_until'];

    if ($is_late) {
        $offense_number = (int) $member['offense_count'] + 1;
        $tier = min($offense_number, 3);
        $penalty_rate = (float) setting($conn, 'basics_late_penalty_tier' . $tier, $tier === 1 ? 0.03 : 0.05);
        $penalty_amount = round($amount_due * $penalty_rate, 2);

        $new_offense_count = $offense_number;
        $new_on_time = 0;
        if ($offense_number === 1) {
            $new_frozen = 1;
        } elseif ($offense_number === 2) {
            $new_status = 'suspended';
            $new_suspended_until = date('Y-m-d', strtotime('+1 month'));
        } elseif ($offense_number >= 3) {
            $new_status = 'terminated';
        }
    } else {
        $new_on_time += 1;
    }

    $stmt = $conn->prepare("INSERT INTO basics_payments
        (order_id, member_id, amount_due, penalty_rate, penalty_amount, amount_paid, offense_number, is_late, paid_at, recorded_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $is_late_int = $is_late ? 1 : 0;
    $stmt->bind_param('iiddddiisis',
        $order_id, $order['member_id'], $amount_due, $penalty_rate, $penalty_amount, $amount_paid,
        $offense_number, $is_late_int, $paid_at, $admin_id, $notes);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE basics_members SET
        offense_count = ?, consecutive_on_time_payments = ?, credit_limit_frozen = ?,
        membership_status = ?, suspended_until = ?, last_activity_at = NOW()
        WHERE id = ?");
    $stmt->bind_param('iiissi', $new_offense_count, $new_on_time, $new_frozen, $new_status, $new_suspended_until, $member['id']);
    $stmt->execute();
    $stmt->close();

    return ['is_late' => $is_late, 'penalty_amount' => $penalty_amount, 'membership_status' => $new_status];
}
