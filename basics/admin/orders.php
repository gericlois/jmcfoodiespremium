<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_basics_admin_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE basics_orders SET status = 'confirmed', confirmed_at = NOW() WHERE id = ? AND status = 'pending' AND placed_at IS NOT NULL");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $confirmed = $stmt->affected_rows > 0;
    $stmt->close();
    if ($confirmed) {
        log_activity($conn, 'confirm_basics_order', 'Confirmed Basics order #' . $id);
        $member = basics_member_by_order_id($conn, $id);
        if ($member) {
            basics_notify($conn, $member, "Hi {$member['full_name']}, your order has been confirmed. Please settle payment during the payment period so it can be delivered. - JMC Foodies Basics");
        }
    }
    redirect('/basics/admin/orders.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispatch') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE basics_orders SET status = 'out for delivery', out_for_delivery_at = NOW() WHERE id = ? AND status = 'paid'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $dispatched = $stmt->affected_rows > 0;
    $stmt->close();
    if ($dispatched) {
        log_activity($conn, 'dispatch_basics_order', 'Marked Basics order #' . $id . ' as out for delivery');
        $member = basics_member_by_order_id($conn, $id);
        if ($member) {
            basics_notify($conn, $member, "Hi {$member['full_name']}, your order is out for delivery! - JMC Foodies Basics");
        }
    }
    redirect('/basics/admin/orders.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deliver') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE basics_orders SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'out for delivery'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $delivered = $stmt->affected_rows > 0;
    $stmt->close();
    if ($delivered) {
        log_activity($conn, 'deliver_basics_order', 'Marked Basics order #' . $id . ' as delivered');
        $member = basics_member_by_order_id($conn, $id);
        if ($member) {
            basics_notify($conn, $member, "Hi {$member['full_name']}, your order has been delivered! - JMC Foodies Basics");
        }
    }
    redirect('/basics/admin/orders.php');
}

$valid_statuses = ['pending', 'confirmed', 'paid', 'out for delivery', 'delivered', 'cancelled'];
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT o.*, u.full_name, u.username, c.label AS cycle_label,
               (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
        FROM basics_orders o
        JOIN basics_members bm ON bm.id = o.member_id
        JOIN basics_users u ON u.id = bm.user_id
        JOIN basics_cycles c ON c.id = o.cycle_id
        WHERE o.placed_at IS NOT NULL";
if (in_array($status_filter, $valid_statuses, true)) {
    $sql .= " AND o.status = '" . $conn->real_escape_string($status_filter) . "'";
}
$sql .= " ORDER BY o.created_at DESC";
$orders = $conn->query($sql);

$page_title = 'Basics Orders';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">JMC Foodies Basics</span>
    <h1 class="stitle" style="font-size:2rem;">Orders</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/basics/admin/orders.php" class="filter-pill <?= $status_filter === '' ? 'active' : '' ?>">All</a>
      <?php foreach ($valid_statuses as $status): ?>
        <a href="<?= BASE_URL ?>/basics/admin/orders.php?status=<?= urlencode($status) ?>"
           class="filter-pill text-capitalize <?= $status_filter === $status ? 'active' : '' ?>"><?= sanitize($status) ?></a>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn-outline-theme no-print" onclick="window.print()"><i class="fas fa-print"></i>Print</button>
  </div>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Order #</th><th>Member</th><th>Cycle</th><th>Total</th><th>Paid</th><th>Status</th><th>Date</th><th class="no-print"></th></tr></thead>
      <tbody>
      <?php if ($orders->num_rows === 0): ?>
        <tr><td colspan="8" class="text-muted">No orders found.</td></tr>
      <?php endif; ?>
      <?php while ($o = $orders->fetch_assoc()): ?>
        <tr>
          <td>#<?= (int) $o['id'] ?></td>
          <td><?= sanitize($o['full_name']) ?> <span class="text-muted small">(<?= sanitize($o['username']) ?>)</span></td>
          <td><?= sanitize($o['cycle_label']) ?></td>
          <td><?= format_price($o['total_amount']) ?></td>
          <td><?= format_price($o['amount_paid']) ?></td>
          <td><span class="pill pill-<?= basics_order_status_badge($o['status']) ?>"><?= sanitize($o['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
          <td class="no-print">
            <a href="<?= BASE_URL ?>/basics/admin/order_view.php?id=<?= (int) $o['id'] ?>" class="btn-chip btn-chip-outline">View</a>
            <?php if ($o['status'] === 'pending'): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Confirm this order?');">Confirm</button>
              </form>
            <?php elseif ($o['status'] === 'confirmed' && $o['amount_paid'] < $o['total_amount']): ?>
              <a href="<?= BASE_URL ?>/basics/admin/payments.php?order_id=<?= (int) $o['id'] ?>" class="btn-chip btn-chip-success">Record Payment</a>
            <?php elseif ($o['status'] === 'paid'): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="dispatch">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Mark this order as out for delivery?');">Out for Delivery</button>
              </form>
            <?php elseif ($o['status'] === 'out for delivery'): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="deliver">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Mark this order as delivered?');">Deliver</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
