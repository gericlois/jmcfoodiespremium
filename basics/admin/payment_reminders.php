<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_basics_admin_login();

// No cron on this hosting — due-date reminders are manual, admin-initiated
// actions (same pattern as the Dormancy Report), not scheduled jobs.

$sent = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_reminders') {
    $kind = $_POST['kind'] ?? '';
    $condition = $kind === 'due_today' ? 'c.payment_due_date = CURDATE()' : "c.payment_due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";

    $stmt = $conn->prepare("SELECT o.id AS order_id, o.total_amount, u.full_name, u.contact_number, c.payment_due_date,
                                    (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
                             FROM basics_orders o
                             JOIN basics_members bm ON bm.id = o.member_id
                             JOIN basics_users u ON u.id = bm.user_id
                             JOIN basics_cycles c ON c.id = o.cycle_id
                             WHERE o.status = 'placed' AND $condition
                             HAVING amount_paid < o.total_amount");
    $stmt->execute();
    $orders = $stmt->get_result();

    while ($o = $orders->fetch_assoc()) {
        $remaining = $o['total_amount'] - $o['amount_paid'];
        if ($kind === 'due_today') {
            $message = "URGENT: Hi {$o['full_name']}, your balance of " . format_price($remaining) . " is due TODAY. Please settle it as soon as possible to avoid a late payment penalty. - JMC Foodies Basics";
        } else {
            $message = "Hi {$o['full_name']}, this is a reminder that your balance of " . format_price($remaining) . " is due tomorrow (" . date('M j, Y', strtotime($o['payment_due_date'])) . "). - JMC Foodies Basics";
        }
        if (send_sms($o['contact_number'] ?? '', $message)) {
            $sent++;
        }
    }

    if ($sent > 0) {
        log_activity($conn, 'send_basics_payment_reminders', 'Sent ' . $sent . ' Basics payment reminder SMS (' . $kind . ')');
    }
    redirect('/basics/admin/payment_reminders.php?sent=' . $sent . '&kind=' . $kind);
}

$stmt = $conn->prepare("SELECT o.id AS order_id, o.total_amount, u.full_name, u.username, u.contact_number, c.payment_due_date,
                                (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
                         FROM basics_orders o
                         JOIN basics_members bm ON bm.id = o.member_id
                         JOIN basics_users u ON u.id = bm.user_id
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.status = 'placed' AND c.payment_due_date = CURDATE()
                         HAVING amount_paid < o.total_amount
                         ORDER BY o.id ASC");
$stmt->execute();
$due_today = $stmt->get_result();

$stmt = $conn->prepare("SELECT o.id AS order_id, o.total_amount, u.full_name, u.username, u.contact_number, c.payment_due_date,
                                (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
                         FROM basics_orders o
                         JOIN basics_members bm ON bm.id = o.member_id
                         JOIN basics_users u ON u.id = bm.user_id
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.status = 'placed' AND c.payment_due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                         HAVING amount_paid < o.total_amount
                         ORDER BY o.id ASC");
$stmt->execute();
$due_tomorrow = $stmt->get_result();

$page_title = 'Payment Reminders';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">JMC Foodies Basics</span>
    <h1 class="stitle" style="font-size:2rem;">Payment Reminders</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <?php if (isset($_GET['sent'])): ?>
    <div class="sucmsg is-visible"><p><?= (int) $_GET['sent'] ?> reminder SMS sent.</p></div>
  <?php endif; ?>
  <p class="text-muted">There is no automatic scheduler on this hosting, so due-date reminders must be sent manually from here.</p>

  <div class="panel-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h2 class="h6 mb-0">Due Tomorrow &mdash; Reminder</h2>
      <?php if ($due_tomorrow->num_rows > 0): ?>
        <form method="post">
          <input type="hidden" name="action" value="send_reminders">
          <input type="hidden" name="kind" value="due_tomorrow">
          <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Send a payment reminder SMS to everyone due tomorrow?');">Send Reminders to All (<?= $due_tomorrow->num_rows ?>)</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table-theme">
        <thead><tr><th>Order #</th><th>Member</th><th>Balance</th><th>Due Date</th></tr></thead>
        <tbody>
        <?php if ($due_tomorrow->num_rows === 0): ?>
          <tr><td colspan="4" class="text-muted">No orders due tomorrow.</td></tr>
        <?php endif; ?>
        <?php while ($o = $due_tomorrow->fetch_assoc()): ?>
          <tr>
            <td>#<?= (int) $o['order_id'] ?></td>
            <td><?= sanitize($o['full_name']) ?> <span class="text-muted small">(<?= sanitize($o['username']) ?>)</span></td>
            <td><?= format_price($o['total_amount'] - $o['amount_paid']) ?></td>
            <td><?= date('M j, Y', strtotime($o['payment_due_date'])) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h2 class="h6 mb-0">Due Today &mdash; Urgent Settlement Notice</h2>
      <?php if ($due_today->num_rows > 0): ?>
        <form method="post">
          <input type="hidden" name="action" value="send_reminders">
          <input type="hidden" name="kind" value="due_today">
          <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Send an urgent settlement notice SMS to everyone due today?');">Send Reminders to All (<?= $due_today->num_rows ?>)</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table-theme">
        <thead><tr><th>Order #</th><th>Member</th><th>Balance</th><th>Due Date</th></tr></thead>
        <tbody>
        <?php if ($due_today->num_rows === 0): ?>
          <tr><td colspan="4" class="text-muted">No orders due today.</td></tr>
        <?php endif; ?>
        <?php while ($o = $due_today->fetch_assoc()): ?>
          <tr>
            <td>#<?= (int) $o['order_id'] ?></td>
            <td><?= sanitize($o['full_name']) ?> <span class="text-muted small">(<?= sanitize($o['username']) ?>)</span></td>
            <td><?= format_price($o['total_amount'] - $o['amount_paid']) ?></td>
            <td><?= date('M j, Y', strtotime($o['payment_due_date'])) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
