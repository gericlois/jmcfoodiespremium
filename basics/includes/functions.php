<?php
// JMC Foodies Basics business logic: weekly ordering cycles, revolving
// credit-line checks, and the tiered late-payment penalty engine.

// Thin wrapper around send_sms() (includes/functions.php) — every Basics
// SMS trigger has a $member array (from basics_get_member() or a JOIN
// selecting u.contact_number) on hand already, so this saves repeating the
// column lookup at every call site. Gated by the admin-editable
// basics_sms_notifications_enabled setting (basics/admin/settings.php) —
// unlike an explicit admin broadcast, these are automatic triggers, so the
// admin gets a master off-switch for them.
function basics_notify($conn, $member, $message) {
    if (setting($conn, 'basics_sms_notifications_enabled', '1') !== '1') {
        return false;
    }
    return send_sms($member['contact_number'] ?? '', $message);
}

// Sent alongside the existing approval SMS (basics_notify() in
// basics/admin/application_view.php) when an admin approves a pending
// membership application — the SMS is a quick heads-up, this carries the
// actual login instructions. Via send_email() (includes/functions.php,
// Gmail SMTP). $password is the default-pattern password
// (basics_default_password()) that approval just reset the account to —
// the app never stores what the applicant originally typed at signup
// (only its one-way hash), so this reset is the only way to hand back a
// working password in the notification.
function send_basics_account_approved_email($to_email, $full_name, $username, $weekly_limit, $password) {
    if (empty($to_email)) {
        return false;
    }
    $subject = 'Your JMC Foodies Basics membership has been approved';
    $message = "Hi {$full_name},\r\n\r\n"
        . "Good news! Your JMC Foodies Basics membership application has been reviewed and approved.\r\n\r\n"
        . 'Weekly Credit Limit: ' . format_price($weekly_limit) . "\r\n\r\n"
        . "You can now log in and start ordering:\r\n\r\n"
        . "Username: {$username}\r\n"
        . "Password: {$password}\r\n\r\n"
        . 'Log in here: ' . BASICS_URL . "/login.php\r\n\r\n"
        . "You'll be asked to set a new password the first time you log in.\r\n\r\n"
        . '— JMC Foodies Basics Team';
    return send_email($to_email, $subject, $message);
}

// Sent alongside the existing denial SMS (basics_notify() in
// basics/admin/application_view.php) when an admin denies a pending
// membership application. The phone number reminder isn't repeated here —
// send_email() (includes/functions.php) already appends it to every email.
function send_basics_account_denied_email($to_email, $full_name) {
    if (empty($to_email)) {
        return false;
    }
    $subject = 'Your JMC Foodies Basics application status';
    $message = "Hi {$full_name},\r\n\r\n"
        . "Thank you for choosing to apply for the JMC Foodies Basics Program.\r\n\r\n"
        . "Unfortunately, we are unable to approve your application at this time, based on your available credit and financial information. However, we would like you to consider applying again after 30 days.\r\n\r\n"
        . '— JMC Foodies Basics Team';
    return send_email($to_email, $subject, $message);
}

// The Basics default/reset password convention: "basics" + the member's
// lowercase last name (e.g. "Julius Menor" -> "basicsmenor"). Thin wrapper
// over generate_default_password() (includes/functions.php), which Wellness
// uses the same way with prefix "wellness".
function basics_default_password($full_name) {
    return generate_default_password('basics', $full_name);
}

// Looks up the full member row (for basics_notify()) from a basics_orders.id
// — several admin actions only have the order id on hand, not the member.
function basics_member_by_order_id($conn, $order_id) {
    $stmt = $conn->prepare("SELECT bm.user_id FROM basics_orders o JOIN basics_members bm ON bm.id = o.member_id WHERE o.id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? basics_get_member($conn, $row['user_id']) : null;
}

// Looks up the full member row (for basics_notify()) from a basics_members.id.
function basics_member_by_id($conn, $member_id) {
    $stmt = $conn->prepare("SELECT user_id FROM basics_members WHERE id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? basics_get_member($conn, $row['user_id']) : null;
}

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
                             FROM basics_members bm JOIN basics_users u ON u.id = bm.user_id
                             WHERE bm.user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $member ?: null;
}

// Sum of `pending` (checked out, not yet fully paid) orders — status flips
// to 'paid' automatically once payments cover the total, so a plain status
// filter is enough. A revolving ceiling, not a per-cycle reset — a member
// who hasn't paid down prior weeks simply can't order more.
function basics_outstanding_balance($conn, $member_id) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(o.total_amount), 0) AS outstanding
                             FROM basics_orders o
                             WHERE o.member_id = ? AND o.status = 'pending'");
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

// Total released across all of this member's approved Emergency Cash Credit
// requests, minus confirmed repayments against them. Derived, not stored —
// a repayment submission just needs confirming for this to update itself.
function basics_emergency_credit_outstanding($conn, $member_id) {
    $stmt = $conn->prepare("SELECT
        COALESCE((SELECT SUM(amount_released) FROM basics_emergency_credit_requests WHERE member_id = ? AND status = 'approved'), 0)
        -
        COALESCE((SELECT SUM(s.amount) FROM basics_payment_submissions s
                  JOIN basics_emergency_credit_requests r ON r.id = s.loan_request_id
                  WHERE r.member_id = ? AND s.status = 'confirmed'), 0) AS outstanding");
    $stmt->bind_param('ii', $member_id, $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float) $row['outstanding'];
}

function basics_emergency_credit_available($conn, $member) {
    $outstanding = basics_emergency_credit_outstanding($conn, $member['id']);
    return max(0, (float) $member['emergency_credit_limit'] - $outstanding);
}

// This member's approved requests that still have a balance owed —
// populates the "which loan is this repaying" choice on the Pay! form.
function basics_member_outstanding_loans($conn, $member_id) {
    $stmt = $conn->prepare("SELECT r.*,
                                (r.amount_released - COALESCE((SELECT SUM(s.amount) FROM basics_payment_submissions s WHERE s.loan_request_id = r.id AND s.status = 'confirmed'), 0)) AS remaining
                             FROM basics_emergency_credit_requests r
                             WHERE r.member_id = ? AND r.status = 'approved'
                             HAVING remaining > 0
                             ORDER BY r.released_at ASC");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    return $stmt->get_result();
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
    resize_image_if_needed($dest, $mime);
    return [$new_filename, null];
}

// Optional receipt/screenshot attached to a member's payment submission.
// Unlike handle_kyc_document_upload(), a missing file is not an error —
// proof is a nice-to-have, the reference number is the primary evidence.
function handle_payment_proof_upload($file_key) {
    if (empty($_FILES[$file_key]['name'])) {
        return [null, null];
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
        return [null, 'Proof file must be a JPG, PNG, WEBP, or PDF.'];
    }

    $new_filename = bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime];
    $dest = UPLOAD_PATH . 'basics_payment_proofs/' . $new_filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $dest)) {
        return [null, 'Failed to save the proof file.'];
    }
    resize_image_if_needed($dest, $mime);
    return [$new_filename, null];
}

// This member's pending orders that aren't fully paid yet — populates the
// "which order is this for" choice on the Grocery payment submission form.
function basics_member_awaiting_orders($conn, $member_id) {
    $stmt = $conn->prepare("SELECT o.*, c.label AS cycle_label,
                                    (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
                             FROM basics_orders o
                             JOIN basics_cycles c ON c.id = o.cycle_id
                             WHERE o.member_id = ? AND o.status = 'pending'
                             HAVING amount_paid < o.total_amount
                             ORDER BY o.created_at DESC");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Records a payment against a pending order, applying the late-payment
// penalty tier + credit-line/suspension escalation in one transaction.
// Returns ['is_late' => bool, 'penalty_amount' => float, 'membership_status' => string].
function basics_record_payment($conn, $order_id, $amount_paid, $paid_at, $admin_id, $notes = null) {
    $stmt = $conn->prepare("SELECT o.*, c.payment_due_date
                             FROM basics_orders o JOIN basics_cycles c ON c.id = o.cycle_id
                             WHERE o.id = ? AND o.status = 'pending' FOR UPDATE");
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

    $total_paid = (float) $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS s FROM basics_payments WHERE order_id = " . (int) $order_id)->fetch_assoc()['s'];
    if ($total_paid >= $amount_due) {
        $stmt = $conn->prepare("UPDATE basics_orders SET status = 'paid' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE basics_members SET
        offense_count = ?, consecutive_on_time_payments = ?, credit_limit_frozen = ?,
        membership_status = ?, suspended_until = ?, last_activity_at = NOW()
        WHERE id = ?");
    $stmt->bind_param('iiissi', $new_offense_count, $new_on_time, $new_frozen, $new_status, $new_suspended_until, $member['id']);
    $stmt->execute();
    $stmt->close();

    log_activity($conn, 'record_basics_payment', 'Recorded ' . ($is_late ? 'late' : 'on-time') . ' payment of ' . format_price($amount_paid) . ' for Basics order #' . $order_id);

    $notify_member = basics_get_member($conn, $member['user_id']);
    if ($notify_member) {
        if ($new_status === 'active') {
            basics_notify($conn, $notify_member, "Hi {$notify_member['full_name']}, we've received your payment of " . format_price($amount_paid) . ". Your credit limit has been restored - you may now place new orders. - JMC Foodies Basics");
        } elseif ($new_status === 'suspended') {
            basics_notify($conn, $notify_member, "Hi {$notify_member['full_name']}, we've received your payment of " . format_price($amount_paid) . ". Due to repeated late payment, your membership has been suspended until " . date('M j, Y', strtotime($new_suspended_until)) . ". - JMC Foodies Basics");
        } elseif ($new_status === 'terminated') {
            basics_notify($conn, $notify_member, "Hi {$notify_member['full_name']}, we've received your payment of " . format_price($amount_paid) . ". Due to repeated late payment, your JMC Foodies Basics membership has been terminated. - JMC Foodies Basics");
        }
    }

    return ['is_late' => $is_late, 'penalty_amount' => $penalty_amount, 'membership_status' => $new_status];
}

// ---------------------------------------------------------------
// Phase 2 benefit programs (program manual section 10): Electric Bill Cash
// Subsidy, Hospital Financial Assistance, Burial Financial Assistance, Baon
// Eskwela Subsidy. One shared request table + doc table for all four — see
// basics/benefits.php (member) and basics/admin/benefit_requests.php (admin).
// ---------------------------------------------------------------

function basics_benefit_type_labels() {
    return [
        'electric_subsidy' => 'Electric Bill Cash Subsidy',
        'hospital_assistance' => 'Hospital Financial Assistance',
        'burial_assistance' => 'Burial Financial Assistance',
        'baon_eskwela' => 'Baon Eskwela Subsidy',
    ];
}

// Single source of truth for which documents each benefit type requires —
// drives both the member submission form's required fields and the admin
// review page's document labels, so the two can never drift apart.
function basics_benefit_doc_requirements($benefit_type) {
    $requirements = [
        'electric_subsidy' => ['electric_bill' => 'Electric Bill'],
        'hospital_assistance' => [
            'medical_abstract' => 'Medical Abstract / Certificate',
            'hospital_bill' => 'Hospital Bill',
            'prescription' => 'Prescription(s)',
        ],
        'burial_assistance' => ['death_certificate' => 'Death Certificate'],
        'baon_eskwela' => [
            'enrollment_form' => 'Current Enrollment Form',
            'child_id' => "Child's ID",
            'birth_certificate' => "Child's Birth Certificate",
        ],
    ];
    return $requirements[$benefit_type] ?? [];
}

// Same upload rules as handle_kyc_document_upload() (JPG/PNG/WEBP/PDF, 5MB),
// but saved under uploads/basics_benefit_docs/ — also not publicly servable,
// these are medical records / death certificates. Missing file is an error
// here (unlike the optional payment proof) since each doc_type is required
// per basics_benefit_doc_requirements().
function handle_benefit_document_upload($file_key) {
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
    $dest = UPLOAD_PATH . 'basics_benefit_docs/' . $new_filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $dest)) {
        return [null, 'Failed to save the uploaded file.'];
    }
    resize_image_if_needed($dest, $mime);
    return [$new_filename, null];
}
