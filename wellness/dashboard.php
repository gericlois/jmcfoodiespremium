<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';

require_wellness_access($conn);

$user_id = current_user_id();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_rebates = wallet_sum_by_type($conn, $user_id, 'personal_rebate');
$total_overrides = wallet_sum_by_type($conn, $user_id, 'referral_override');
$balance = wallet_balance($conn, $user_id);

$stmt = $conn->prepare("SELECT u.id, u.full_name, u.username, u.created_at,
                                COUNT(o.id) AS order_count,
                                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) AS total_purchased
                         FROM users u
                         LEFT JOIN orders o ON o.user_id = u.id
                         WHERE u.referred_by = ?
                         GROUP BY u.id
                         ORDER BY u.created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$referrals = $stmt->get_result();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Your Overview</span>
    <h1 class="stitle">Welcome, <span><?= sanitize($user['full_name']) ?></span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-tile">
        <div class="stat-num"><?= format_price($total_rebates) ?></div>
        <div class="stat-lbl">Purchase Rebates</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile">
        <div class="stat-num"><?= format_price($total_overrides) ?></div>
        <div class="stat-lbl">Purchase Over-ride Earnings</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile">
        <div class="stat-num"><?= (int) $referrals->num_rows ?></div>
        <div class="stat-lbl">Tagged Friends</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile">
        <div class="stat-num accent"><?= format_price($balance) ?></div>
        <div class="stat-lbl"><?= sanitize(WALLET_NAME) ?> Balance</div>
      </div>
    </div>
  </div>

  <div class="panel-card mb-4">
    <h2 class="h6 mb-3">Your Refer-a-Friend Code &amp; Link</h2>
    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-3 text-center">
        <div id="qrcode" class="d-inline-block"></div>
      </div>
      <div class="col-12 col-md-3">
        <div class="refcode-box d-flex align-items-center justify-content-between gap-2">
          <span id="refCode"><?= sanitize($user['referral_code']) ?></span>
          <button type="button" class="btn-copy-icon" data-copy-target="refCode" aria-label="Copy referral code"><i class="fas fa-copy"></i></button>
        </div>
      </div>
      <div class="col-12 col-md-5">
        <input type="text" class="fctrl" id="refLink" value="<?= sanitize(referral_link($user['referral_code'])) ?>" readonly>
      </div>
      <div class="col-12 col-md-1">
        <button class="btn-outline-theme w-100 justify-content-center" data-copy-target="refLink">Copy</button>
      </div>
    </div>
  </div>

  <h2 class="h5 mb-3">Your Tagged Friends</h2>
  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Name</th><th>Username</th><th>Orders</th><th>Total Purchased</th><th>Joined</th></tr></thead>
      <tbody>
      <?php if ($referrals->num_rows === 0): ?>
        <tr><td colspan="5" class="text-muted">You haven't referred anyone yet. Share your referral link above!</td></tr>
      <?php endif; ?>
      <?php while ($ref = $referrals->fetch_assoc()): ?>
        <tr>
          <td><?= sanitize($ref['full_name']) ?></td>
          <td><?= sanitize($ref['username']) ?></td>
          <td><?= (int) $ref['order_count'] ?></td>
          <td><?= format_price($ref['total_purchased']) ?></td>
          <td><?= date('M j, Y', strtotime($ref['created_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qrcode'), {
    text: document.getElementById('refLink').value,
    width: 96,
    height: 96
  });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
