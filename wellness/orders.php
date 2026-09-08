<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';

require_wellness_access($conn);

$user_id = current_user_id();
$stmt = $conn->prepare("SELECT o.*, p.name AS product_name FROM orders o
                         JOIN products p ON p.id = o.product_id
                         WHERE o.user_id = ? ORDER BY o.created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders = $stmt->get_result();

$page_title = 'My Orders';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Purchase History</span>
    <h1 class="stitle">My <span>Orders</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <?php if (isset($_GET['purchased'])): ?>
    <div class="sucmsg is-visible">
      <p>
        <?php if (($_GET['method'] ?? '') === 'bank_transfer'): ?>
          Order placed! It's awaiting admin confirmation of your bank transfer payment, then delivery, before your rebate is credited.
        <?php elseif (($_GET['method'] ?? '') === 'cod'): ?>
          Order placed! Your rebate will be credited once payment is collected and the order is marked delivered.
        <?php else: ?>
          Order placed and paid instantly from your wallet balance. Your rebate will be credited once the order is marked delivered.
        <?php endif; ?>
      </p>
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Order #</th><th>Product</th><th>Qty</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php if ($orders->num_rows === 0): ?>
        <tr><td colspan="7" class="text-muted">You haven't placed any orders yet. <a href="<?= WELLNESS_URL ?>/menu.php">Buy now</a>.</td></tr>
      <?php endif; ?>
      <?php while ($order = $orders->fetch_assoc()): ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td><?= sanitize($order['product_name']) ?></td>
          <td><?= (int) $order['quantity'] ?></td>
          <td><?= format_price($order['total_amount']) ?></td>
          <td><?= sanitize(payment_method_label($order['payment_method'])) ?></td>
          <td><span class="pill pill-<?= $order['status'] ?>"><?= sanitize($order['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
