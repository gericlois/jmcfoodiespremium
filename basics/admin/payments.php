<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_admin_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $amount_paid = round((float) ($_POST['amount_paid'] ?? 0), 2);
    $paid_at = trim($_POST['paid_at'] ?? '') ?: date('Y-m-d H:i:s');
    $notes = trim($_POST['notes'] ?? '') ?: null;

    if ($amount_paid <= 0) {
        $errors[] = 'Enter a valid amount paid.';
    } else {
        $conn->begin_transaction();
        try {
            $result = basics_record_payment($conn, $order_id, $amount_paid, $paid_at, current_admin_id(), $notes);
            $conn->commit();
            redirect('/basics/admin/order_view.php?id=' . $order_id . '&recorded=1');
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$order_id_prefill = (int) ($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("SELECT o.*, u.full_name, u.username, c.label AS cycle_label, c.payment_due_date,
                                (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
                         FROM basics_orders o
                         JOIN basics_members bm ON bm.id = o.member_id
                         JOIN users u ON u.id = bm.user_id
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.status = 'placed'
                         HAVING amount_paid < o.total_amount
                         ORDER BY c.payment_due_date ASC");
$stmt->execute();
$awaiting = $stmt->get_result();

$page_title = 'Record Payment';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/../../admin/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">JMC Foodies Basics</span>
    <h1 class="stitle" style="font-size:2rem;">Record Payment</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <?php if (isset($_GET['recorded'])): ?>
    <div class="sucmsg is-visible"><p>Payment recorded.</p></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="errmsg">
      <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Order #</th><th>Member</th><th>Cycle</th><th>Due</th><th>Amount Due</th><th>Already Paid</th><th>Payment Due Date</th><th></th></tr></thead>
      <tbody>
      <?php if ($awaiting->num_rows === 0): ?>
        <tr><td colspan="8" class="text-muted">No orders currently awaiting payment.</td></tr>
      <?php endif; ?>
      <?php while ($o = $awaiting->fetch_assoc()): ?>
        <?php $remaining = $o['total_amount'] - $o['amount_paid']; $is_expanded = $order_id_prefill === (int) $o['id']; ?>
        <tr>
          <td>#<?= (int) $o['id'] ?></td>
          <td><?= sanitize($o['full_name']) ?> <span class="text-muted small">(<?= sanitize($o['username']) ?>)</span></td>
          <td><?= sanitize($o['cycle_label']) ?></td>
          <td><?= format_price($remaining) ?></td>
          <td><?= format_price($o['total_amount']) ?></td>
          <td><?= format_price($o['amount_paid']) ?></td>
          <td><?= date('M j, Y', strtotime($o['payment_due_date'])) ?><?= date('Y-m-d') > $o['payment_due_date'] ? ' <span class="pill pill-rejected">Overdue</span>' : '' ?></td>
          <td>
            <button type="button" class="btn-chip btn-chip-success" data-bs-toggle="collapse" data-bs-target="#pay-<?= (int) $o['id'] ?>">Record Payment</button>
          </td>
        </tr>
        <tr class="collapse <?= $is_expanded ? 'show' : '' ?>" id="pay-<?= (int) $o['id'] ?>">
          <td colspan="8">
            <form method="post" class="d-flex flex-wrap gap-2 align-items-end py-2">
              <input type="hidden" name="action" value="record_payment">
              <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
              <div>
                <label class="flbl">Amount Paid</label>
                <input type="number" step="0.01" min="0.01" name="amount_paid" class="fctrl" value="<?= sanitize($remaining) ?>" required style="width:140px;">
              </div>
              <div>
                <label class="flbl">Date/Time Paid</label>
                <input type="datetime-local" name="paid_at" class="fctrl" value="<?= date('Y-m-d\TH:i') ?>" required>
              </div>
              <div class="flex-grow-1">
                <label class="flbl">Notes (optional)</label>
                <input type="text" name="notes" class="fctrl">
              </div>
              <button type="submit" class="btn-red" onclick="return confirm('Record this payment? A late payment will apply the penalty tier automatically.');"><i class="fas fa-check"></i>Confirm</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
