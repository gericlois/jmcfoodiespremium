<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/functions.php';

require_basics_access($conn);

$member = basics_get_member($conn, basics_current_user_id());
$outstanding = basics_outstanding_balance($conn, $member['id']);
$available = basics_credit_available($conn, $member);
$cycle = basics_active_order_cycle($conn);

$stmt = $conn->prepare("SELECT o.*, c.label AS cycle_label FROM basics_orders o
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.member_id = ? ORDER BY o.created_at DESC LIMIT 5");
$stmt->bind_param('i', $member['id']);
$stmt->execute();
$recent_orders = $stmt->get_result();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Your Overview</span>
    <h1 class="stitle">Welcome, <span><?= sanitize($member['full_name']) ?></span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <?php if ($member['credit_limit_frozen']): ?>
    <div class="errmsg mb-4">
      <p class="mb-0"><i class="fas fa-triangle-exclamation me-1"></i>Your credit limit is currently frozen due to a late payment. It will unfreeze once your payment performance improves.</p>
    </div>
  <?php endif; ?>
  <?php if ($member['consecutive_on_time_payments'] >= 12): ?>
    <div class="sucmsg is-visible mb-4"><p><i class="fas fa-star me-1"></i>You've made 12+ consecutive on-time payments &mdash; you may be eligible for a higher credit limit. Contact support to ask about an increase.</p></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Sidebar: credit summary + quick actions -->
    <div class="col-12 col-lg-4">
      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-12">
          <div class="stat-tile">
            <div class="stat-num"><?= format_price($member['weekly_credit_limit']) ?></div>
            <div class="stat-lbl">Weekly Credit Limit</div>
          </div>
        </div>
        <div class="col-6 col-lg-12">
          <div class="stat-tile">
            <div class="stat-num accent"><?= format_price($available) ?></div>
            <div class="stat-lbl">Available Credit</div>
          </div>
        </div>
        <div class="col-6 col-lg-12">
          <div class="stat-tile">
            <div class="stat-num"><?= format_price($outstanding) ?></div>
            <div class="stat-lbl">Outstanding Balance</div>
          </div>
        </div>
        <div class="col-6 col-lg-12">
          <div class="stat-tile">
            <div class="stat-num"><?= (int) $member['consecutive_on_time_payments'] ?></div>
            <div class="stat-lbl">On-Time Payments</div>
          </div>
        </div>
      </div>

      <div class="panel-card">
        <h2 class="h6 mb-3">Quick Actions</h2>
        <div class="d-grid gap-2">
          <a href="<?= BASICS_URL ?>/catalog.php" class="btn-red justify-content-center"><i class="fas fa-basket-shopping"></i>Browse Catalog</a>
          <a href="<?= BASICS_URL ?>/cart.php" class="btn-outline-theme justify-content-center"><i class="fas fa-cart-shopping"></i>Go to Cart</a>
          <a href="<?= BASICS_URL ?>/payments.php" class="btn-outline-theme justify-content-center"><i class="fas fa-receipt"></i>Payment History</a>
          <a href="<?= BASICS_URL ?>/emergency_credit.php" class="btn-outline-theme justify-content-center"><i class="fas fa-hand-holding-dollar"></i>Emergency Cash Credit</a>
          <a href="<?= BASICS_URL ?>/benefits.php" class="btn-outline-theme justify-content-center"><i class="fas fa-hand-holding-heart"></i>Member Benefits</a>
          <a href="<?= BASICS_URL ?>/change_password.php" class="btn-outline-theme justify-content-center"><i class="fas fa-key"></i>Change Password</a>
        </div>
      </div>
    </div>

    <!-- Main content: ordering window, recent orders, benefits -->
    <div class="col-12 col-lg-8">
      <div class="panel-card mb-4">
        <h2 class="h6 mb-3">This Week's Ordering Window</h2>
        <?php if ($cycle): ?>
          <p class="mb-0"><strong><?= sanitize($cycle['label']) ?></strong> &mdash; ordering is open until <?= date('M j, Y', strtotime($cycle['order_cutoff_date'])) ?>.</p>
        <?php else: ?>
          <p class="text-muted mb-0">No ordering window is open right now. Orders open Monday through Thursday each week.</p>
        <?php endif; ?>
      </div>

      <div class="panel-card mb-4">
        <h2 class="h6 mb-3">Recent Orders</h2>
        <div class="table-responsive">
          <table class="table-theme">
            <thead><tr><th>Cycle</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if ($recent_orders->num_rows === 0): ?>
              <tr><td colspan="4" class="text-muted">No orders yet. <a href="<?= BASICS_URL ?>/catalog.php">Browse the catalog</a>.</td></tr>
            <?php endif; ?>
            <?php while ($o = $recent_orders->fetch_assoc()): ?>
              <tr>
                <td><?= sanitize($o['cycle_label']) ?></td>
                <td><?= format_price($o['total_amount']) ?></td>
                <td><span class="pill pill-<?= basics_order_status_badge($o['status']) ?>"><?= sanitize($o['status']) ?></span></td>
                <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel-card text-center">
        <h2 class="h6 mb-3">Member Benefits</h2>
        <img src="<?= BASE_URL ?>/assets/img/basics/JMCBasics_catalog.jpg" alt="JMC Foodies Basics membership benefits" class="highlight-poster mb-3" style="max-width:600px;">
        <div>
          <a href="<?= BASICS_URL ?>/benefits.php" class="btn-red justify-content-center"><i class="fas fa-hand-holding-heart"></i>Request Assistance</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
