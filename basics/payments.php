<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/functions.php';

require_basics_access($conn);

$member = basics_get_member($conn, current_user_id());

$stmt = $conn->prepare("SELECT p.*, o.id AS order_id FROM basics_payments p
                         JOIN basics_orders o ON o.id = p.order_id
                         WHERE p.member_id = ? ORDER BY p.created_at DESC");
$stmt->bind_param('i', $member['id']);
$stmt->execute();
$payments = $stmt->get_result();

$page_title = 'Payments';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Settlement History</span>
    <h1 class="stitle">My <span>Payments</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-tile">
        <div class="stat-num"><?= (int) $member['offense_count'] ?></div>
        <div class="stat-lbl">Late Payment Offenses</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile">
        <div class="stat-num accent"><?= (int) $member['consecutive_on_time_payments'] ?></div>
        <div class="stat-lbl">Consecutive On-Time</div>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Order #</th><th>Amount Due</th><th>Penalty</th><th>Paid</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php if ($payments->num_rows === 0): ?>
        <tr><td colspan="6" class="text-muted">No payments recorded yet.</td></tr>
      <?php endif; ?>
      <?php while ($p = $payments->fetch_assoc()): ?>
        <tr>
          <td>#<?= (int) $p['order_id'] ?></td>
          <td><?= format_price($p['amount_due']) ?></td>
          <td class="<?= $p['penalty_amount'] > 0 ? 'amount-debit' : '' ?>"><?= format_price($p['penalty_amount']) ?></td>
          <td><?= format_price($p['amount_paid']) ?></td>
          <td><span class="pill pill-<?= $p['is_late'] ? 'rejected' : 'approved' ?>"><?= $p['is_late'] ? 'Late' : 'On Time' ?></span></td>
          <td><?= date('M j, Y', strtotime($p['paid_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
