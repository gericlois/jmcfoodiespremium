<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

require_admin_login();

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    update_user_status($conn, $id, $_POST['new_status'] ?? '');
    redirect('/admin/user_view.php?id=' . $id);
}

$stmt = $conn->prepare("SELECT u.*, ref.full_name AS referrer_name FROM users u
                         LEFT JOIN users ref ON ref.id = u.referred_by WHERE u.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    redirect('/admin/users.php');
}

$total_rebates = wallet_sum_by_type($conn, $id, 'personal_rebate');
$total_overrides = wallet_sum_by_type($conn, $id, 'referral_override');
$balance = wallet_balance($conn, $id);

$stmt = $conn->prepare("SELECT * FROM users WHERE referred_by = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $id);
$stmt->execute();
$referrals = $stmt->get_result();

$stmt = $conn->prepare("SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id = o.product_id WHERE o.user_id = ? ORDER BY o.created_at DESC");
$stmt->bind_param('i', $id);
$stmt->execute();
$orders = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC");
$stmt->bind_param('i', $id);
$stmt->execute();
$transactions = $stmt->get_result();

$page_title = $user['full_name'];
require __DIR__ . '/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <a href="<?= BASE_URL ?>/admin/users.php" class="small">&larr; Back to Users</a>
    <h1 class="stitle" style="font-size:2rem;"><?= sanitize($user['full_name']) ?></h1>
  </div>
</div>

<div class="container-fluid py-4">
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num" style="font-size:1.3rem;"><?= format_price($total_rebates) ?></div><div class="stat-lbl">Rebates Earned</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num" style="font-size:1.3rem;"><?= format_price($total_overrides) ?></div><div class="stat-lbl">Overrides Earned</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num accent" style="font-size:1.3rem;"><?= format_price($balance) ?></div><div class="stat-lbl"><?= sanitize(WALLET_NAME) ?> Balance</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num"><?= (int) $referrals->num_rows ?></div><div class="stat-lbl">Direct Referrals</div></div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
      <div class="panel-card">
        <h2 class="h6">Profile</h2>
        <p class="mb-1">Username: <?= sanitize($user['username']) ?></p>
        <p class="mb-1">Address: <?= sanitize($user['address']) ?></p>
        <p class="mb-1">Birthdate: <?= date('M j, Y', strtotime($user['birthdate'])) ?></p>
        <p class="mb-1">Contact #: <?= sanitize($user['contact_number']) ?></p>
        <p class="mb-1">Email: <?= $user['email'] ? sanitize($user['email']) : '—' ?></p>
        <p class="mb-1">Referral Code: <code><?= sanitize($user['referral_code']) ?></code></p>
        <p class="mb-3">Referred By: <?= $user['referrer_name'] ? sanitize($user['referrer_name']) : '— (root account)' ?></p>
        <p class="mb-3">Status: <span class="pill pill-<?= $user['status'] ?>"><?= sanitize($user['status']) ?></span></p>
        <?php if ($user['status'] === 'pending'): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="new_status" value="active">
            <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Approve this user? They will be able to log in.');">Approve</button>
          </form>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="new_status" value="suspended">
            <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Reject this registration? They will not be able to log in.');">Reject</button>
          </form>
        <?php elseif ($user['status'] === 'active'): ?>
          <form method="post">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="new_status" value="suspended">
            <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Suspend this user? They will not be able to log in.');">Suspend Account</button>
          </form>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="new_status" value="active">
            <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Reinstate this user?');">Reinstate Account</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <h2 class="h6">Direct Referrals</h2>
      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Name</th><th>Username</th><th>Joined</th></tr></thead>
          <tbody>
          <?php if ($referrals->num_rows === 0): ?>
            <tr><td colspan="3" class="text-muted">None yet.</td></tr>
          <?php endif; ?>
          <?php while ($r = $referrals->fetch_assoc()): ?>
            <tr>
              <td><?= sanitize($r['full_name']) ?></td>
              <td><?= sanitize($r['username']) ?></td>
              <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-md-6">
      <h2 class="h6">Orders</h2>
      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Product</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($orders->num_rows === 0): ?>
            <tr><td colspan="4" class="text-muted">No orders yet.</td></tr>
          <?php endif; ?>
          <?php while ($o = $orders->fetch_assoc()): ?>
            <tr>
              <td><?= sanitize($o['product_name']) ?></td>
              <td><?= format_price($o['total_amount']) ?></td>
              <td><span class="pill pill-<?= $o['status'] ?>"><?= sanitize($o['status']) ?></span></td>
              <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <h2 class="h6">Wallet Ledger</h2>
      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($transactions->num_rows === 0): ?>
            <tr><td colspan="3" class="text-muted">No wallet activity yet.</td></tr>
          <?php endif; ?>
          <?php while ($t = $transactions->fetch_assoc()): ?>
            <tr>
              <td class="text-capitalize"><?= sanitize(str_replace('_', ' ', $t['type'])) ?></td>
              <td class="<?= $t['amount'] >= 0 ? 'amount-credit' : 'amount-debit' ?>"><?= $t['amount'] >= 0 ? '+' : '' ?><?= format_price($t['amount']) ?></td>
              <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
