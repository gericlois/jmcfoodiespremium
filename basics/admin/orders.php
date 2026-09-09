<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_basics_admin_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deliver') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE basics_orders SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'paid'");
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
    redirect('/basics/admin/orders.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE basics_orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cancelled = $stmt->affected_rows > 0;
    $stmt->close();
    if ($cancelled) {
        log_activity($conn, 'cancel_basics_order', 'Cancelled Basics order #' . $id);
        $member = basics_member_by_order_id($conn, $id);
        if ($member) {
            basics_notify($conn, $member, "Hi {$member['full_name']}, your order #{$id} has been cancelled. - JMC Foodies Basics");
        }
    }
    redirect('/basics/admin/orders.php');
}

$valid_statuses = ['pending', 'paid', 'delivered', 'cancelled'];
$pill_map = ['pending' => 'processing', 'paid' => 'approved', 'delivered' => 'completed', 'cancelled' => 'cancelled'];
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT o.*, u.full_name, u.username, c.label AS cycle_label,
               (SELECT COALESCE(SUM(amount_paid),0) FROM basics_payments p WHERE p.order_id = o.id) AS amount_paid
        FROM basics_orders o
        JOIN basics_members bm ON bm.id = o.member_id
        JOIN basics_users u ON u.id = bm.user_id
        JOIN basics_cycles c ON c.id = o.cycle_id
        WHERE o.status != 'draft'";
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
        <a href="<?= BASE_URL ?>/basics/admin/orders.php?status=<?= $status ?>"
           class="filter-pill text-capitalize <?= $status_filter === $status ? 'active' : '' ?>"><?= $status ?></a>
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
          <td><span class="pill pill-<?= $pill_map[$o['status']] ?? 'pending' ?>"><?= sanitize($o['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
          <td class="no-print">
            <a href="<?= BASE_URL ?>/basics/admin/order_view.php?id=<?= (int) $o['id'] ?>" class="btn-chip btn-chip-outline">View</a>
            <?php if ($o['status'] === 'pending'): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Cancel this order? This cannot be undone.');">Cancel</button>
              </form>
            <?php elseif ($o['status'] === 'paid'): ?>
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
