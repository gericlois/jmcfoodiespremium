<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/functions.php';

require_basics_access($conn);

$member = basics_get_member($conn, basics_current_user_id());
$cycle = basics_active_order_cycle($conn);
$errors = [];

$order = null;
if ($cycle) {
    $stmt = $conn->prepare("SELECT * FROM basics_orders WHERE member_id = ? AND cycle_id = ? AND status = 'pending' AND placed_at IS NULL");
    $stmt->bind_param('ii', $member['id'], $cycle['id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove_item') {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM basics_order_items WHERE id = ? AND order_id = ?");
        $stmt->bind_param('ii', $item_id, $order['id']);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE basics_orders SET total_amount = (SELECT COALESCE(SUM(line_total),0) FROM basics_order_items WHERE order_id = ?) WHERE id = ?");
        $stmt->bind_param('ii', $order['id'], $order['id']);
        $stmt->execute();
        $stmt->close();
        redirect('/basics/cart.php');
    } elseif ($action === 'place_order') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM basics_order_items WHERE order_id = ?");
        $stmt->bind_param('i', $order['id']);
        $stmt->execute();
        $item_count = $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($item_count == 0) {
            $errors[] = 'Add at least one item before placing your order.';
        } else {
            $available = basics_credit_available($conn, $member);
            if ($member['membership_status'] !== 'active') {
                $errors[] = 'Your account is not currently active for ordering.';
            } elseif ($order['total_amount'] > $available) {
                $errors[] = 'This order (' . format_price($order['total_amount']) . ') exceeds your available credit (' . format_price($available) . ').';
            } else {
                $stmt = $conn->prepare("UPDATE basics_orders SET placed_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $order['id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE basics_members SET last_activity_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $member['id']);
                $stmt->execute();
                $stmt->close();

                basics_notify($conn, $member, "Hi {$member['full_name']}, we've received your order of " . format_price($order['total_amount']) . ". Delivery takes place on " . date('M j, Y', strtotime($cycle['delivery_date'])) . ". - JMC Foodies Basics");

                redirect('/basics/orders.php?placed=1');
            }
        }
    }
}

$items = null;
if ($order) {
    $stmt = $conn->prepare("SELECT oi.*, p.name, p.sku, p.unit FROM basics_order_items oi
                             JOIN basics_products p ON p.id = oi.product_id
                             WHERE oi.order_id = ? ORDER BY oi.id ASC");
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $items = $stmt->get_result();
}

$available = basics_credit_available($conn, $member);

$page_title = 'Cart';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">This Week's Order</span>
    <h1 class="stitle">Your <span>Cart</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <?php if ($errors): ?>
    <div class="errmsg">
      <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if (!$cycle): ?>
    <div class="panel-card text-center">
      <p class="text-muted mb-0">No ordering window is open right now. Orders open Monday through Thursday each week &mdash; check back then.</p>
    </div>
  <?php elseif (!$order || $items->num_rows === 0): ?>
    <div class="panel-card text-center">
      <p class="text-muted mb-3">Your cart is empty for this cycle.</p>
      <a href="<?= BASICS_URL ?>/catalog.php" class="btn-red"><i class="fas fa-basket-shopping"></i>Browse Catalog</a>
    </div>
  <?php else: ?>
    <p class="small text-muted mb-3">Available credit: <strong><?= format_price($available) ?></strong></p>
    <div class="table-responsive mb-4">
      <table class="table-theme">
        <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th><th></th></tr></thead>
        <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
          <tr>
            <td><?= sanitize($item['name']) ?></td>
            <td><?= (int) $item['quantity'] ?> <?= sanitize($item['unit']) ?></td>
            <td><?= format_price($item['unit_price']) ?></td>
            <td><?= format_price($item['line_total']) ?></td>
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="remove_item">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <button type="submit" class="btn-chip btn-chip-outline"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="panel-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0">Order Total</h2>
        <div class="stitle mb-0" style="font-size:1.6rem;"><?= format_price($order['total_amount']) ?></div>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="place_order">
        <button type="submit" class="btn-red w-100 justify-content-center" <?= $order['total_amount'] > $available ? 'disabled' : '' ?>><i class="fas fa-check"></i>Place Order</button>
      </form>
      <?php if ($order['total_amount'] > $available): ?>
        <p class="small text-muted mt-2 mb-0">This order exceeds your available credit.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
