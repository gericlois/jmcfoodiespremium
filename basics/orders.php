<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/functions.php';

require_basics_access($conn);

$member = basics_get_member($conn, current_user_id());

$stmt = $conn->prepare("SELECT o.*, c.label AS cycle_label FROM basics_orders o
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.member_id = ? AND o.status != 'draft'
                         ORDER BY o.created_at DESC");
$stmt->bind_param('i', $member['id']);
$stmt->execute();
$orders = $stmt->get_result();

$pill_map = ['placed' => 'processing', 'delivered' => 'completed', 'cancelled' => 'cancelled'];

$page_title = 'My Orders';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Order History</span>
    <h1 class="stitle">My <span>Orders</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <?php if (isset($_GET['placed'])): ?>
    <div class="sucmsg is-visible"><p>Order placed! Settle your balance during the payment period (Sat-Sun) so it can be delivered.</p></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Cycle</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php if ($orders->num_rows === 0): ?>
        <tr><td colspan="5" class="text-muted">No orders yet. <a href="<?= BASICS_URL ?>/catalog.php">Browse the catalog</a>.</td></tr>
      <?php endif; ?>
      <?php while ($order = $orders->fetch_assoc()): ?>
        <tr>
          <td><?= sanitize($order['cycle_label']) ?></td>
          <td><?= format_price($order['total_amount']) ?></td>
          <td><span class="pill pill-<?= $pill_map[$order['status']] ?? 'pending' ?>"><?= sanitize($order['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
          <td><a href="<?= BASICS_URL ?>/order_view.php?id=<?= (int) $order['id'] ?>" class="btn-chip btn-chip-outline">View</a></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
