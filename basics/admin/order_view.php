<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_basics_admin_login();

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deliver') {
    $stmt = $conn->prepare("UPDATE basics_orders SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'placed'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $delivered = $stmt->affected_rows > 0;
    $stmt->close();
    if ($delivered) {
        log_activity($conn, 'deliver_basics_order', 'Marked Basics order #' . $id . ' as delivered');
        $stmt = $conn->prepare("SELECT payment_due_date FROM basics_orders o JOIN basics_cycles c ON c.id = o.cycle_id WHERE o.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $due_date = $stmt->get_result()->fetch_assoc()['payment_due_date'] ?? null;
        $stmt->close();
        $member = basics_member_by_order_id($conn, $id);
        if ($member && $due_date) {
            basics_notify($conn, $member, "Hi {$member['full_name']}, your order has been delivered! Please settle your balance by " . date('M j, Y', strtotime($due_date)) . ". - JMC Foodies Basics");
        }
    }
    redirect('/basics/admin/order_view.php?id=' . $id);
}

$stmt = $conn->prepare("SELECT o.*, u.full_name, u.username, c.label AS cycle_label, c.payment_due_date
                         FROM basics_orders o
                         JOIN basics_members bm ON bm.id = o.member_id
                         JOIN basics_users u ON u.id = bm.user_id
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    redirect('/basics/admin/orders.php');
}

$stmt = $conn->prepare("SELECT oi.*, p.name, p.sku, p.unit FROM basics_order_items oi
                         JOIN basics_products p ON p.id = oi.product_id
                         WHERE oi.order_id = ? ORDER BY oi.id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM basics_payments WHERE order_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $id);
$stmt->execute();
$payments = $stmt->get_result();

$amount_paid = (float) $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS s FROM basics_payments WHERE order_id = $id")->fetch_assoc()['s'];

$page_title = 'Order #' . $order['id'];
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <a href="<?= BASE_URL ?>/basics/admin/orders.php" class="small">&larr; Back to Orders</a>
    <h1 class="stitle" style="font-size:2rem;">Order #<?= (int) $order['id'] ?></h1>
  </div>
</div>

<div class="container-fluid py-4">
  <div class="row g-4">
    <div class="col-12 col-md-7">
      <div class="panel-card mb-4">
        <h2 class="h6">Order Details</h2>
        <p class="mb-1">Member: <?= sanitize($order['full_name']) ?> (<?= sanitize($order['username']) ?>)</p>
        <p class="mb-1">Cycle: <?= sanitize($order['cycle_label']) ?></p>
        <p class="mb-1">Payment Due: <?= date('M j, Y', strtotime($order['payment_due_date'])) ?></p>
        <p class="mb-0">Status: <span class="pill pill-<?= $order['status'] === 'placed' ? 'processing' : ($order['status'] === 'delivered' ? 'completed' : 'cancelled') ?>"><?= sanitize($order['status']) ?></span></p>
      </div>

      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
          <tbody>
          <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
              <td><?= sanitize($item['name']) ?> <span class="text-muted small">(<?= sanitize($item['sku']) ?>)</span></td>
              <td><?= (int) $item['quantity'] ?> <?= sanitize($item['unit']) ?></td>
              <td><?= format_price($item['unit_price']) ?></td>
              <td><?= format_price($item['line_total']) ?></td>
            </tr>
          <?php endwhile; ?>
          <tr><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold"><?= format_price($order['total_amount']) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-12 col-md-5">
      <div class="panel-card mb-4">
        <h2 class="h6">Payment</h2>
        <p class="mb-1">Amount Due: <?= format_price($order['total_amount']) ?></p>
        <p class="mb-3">Amount Paid: <span class="fw-bold"><?= format_price($amount_paid) ?></span></p>
        <?php if ($order['status'] === 'placed' && $amount_paid < $order['total_amount']): ?>
          <a href="<?= BASE_URL ?>/basics/admin/payments.php?order_id=<?= (int) $order['id'] ?>" class="btn-chip btn-chip-success">Record Payment</a>
        <?php elseif ($order['status'] === 'placed'): ?>
          <form method="post">
            <input type="hidden" name="action" value="deliver">
            <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Mark this order as delivered?');">Mark Delivered</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <h2 class="h6">Payment History</h2>
        <?php if ($payments->num_rows === 0): ?>
          <p class="text-muted mb-0">No payments recorded yet.</p>
        <?php endif; ?>
        <?php while ($p = $payments->fetch_assoc()): ?>
          <div class="mb-3 pb-3" style="border-bottom:1px solid #f1f1f1;">
            <p class="mb-1">Paid: <span class="fw-bold"><?= format_price($p['amount_paid']) ?></span></p>
            <?php if ($p['is_late']): ?>
              <p class="mb-1 small" style="color:var(--primary);">Late &mdash; offense #<?= (int) $p['offense_number'] ?>, <?= format_price($p['penalty_amount']) ?> penalty</p>
            <?php else: ?>
              <p class="mb-1 small" style="color:var(--green);">On time</p>
            <?php endif; ?>
            <p class="mb-0 small text-muted"><?= date('M j, Y', strtotime($p['paid_at'])) ?></p>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
