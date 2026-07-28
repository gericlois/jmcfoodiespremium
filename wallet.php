<?php
require __DIR__ . '/config/constants.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_login($conn);

$user_id = current_user_id();
$errors = [];
$success = '';
$min_cashout = (float) setting($conn, 'min_cashout_amount', 100.00);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cashout') {
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $gcash_number = trim($_POST['gcash_number'] ?? '');
    $gcash_name = trim($_POST['gcash_name'] ?? '');

    if ($amount <= 0) $errors[] = 'Enter a valid amount.';
    if ($amount > 0 && $amount < $min_cashout) $errors[] = 'Minimum cashout amount is ' . format_price($min_cashout) . '.';
    if ($gcash_number === '') $errors[] = 'GCash number is required.';
    if ($gcash_name === '') $errors[] = 'GCash account name is required.';

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $balance = wallet_balance($conn, $user_id);
            if ($amount > $balance) {
                throw new Exception('You cannot cash out more than your current wallet balance (' . format_price($balance) . ').');
            }

            $stmt = $conn->prepare("INSERT INTO cashouts (user_id, amount, gcash_number, gcash_name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('idss', $user_id, $amount, $gcash_number, $gcash_name);
            $stmt->execute();
            $cashout_id = $stmt->insert_id;
            $stmt->close();

            wallet_credit($conn, $user_id, 'cashout', -$amount, null, $cashout_id,
                'Cashout request #' . $cashout_id . ' to GCash ' . $gcash_number);

            $conn->commit();
            $success = 'Cashout request submitted. It will be processed once an admin approves it.';
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$balance = wallet_balance($conn, $user_id);

$stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$transactions = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM cashouts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$cashouts = $stmt->get_result();

$type_labels = [
    'personal_rebate' => 'Personal Rebate',
    'referral_override' => 'Referral Override',
    'purchase_wallet_debit' => 'Purchase (Wallet)',
    'purchase_refund' => 'Order Cancelled (Refund)',
    'cashout' => 'Cashout Request',
    'cashout_reversal' => 'Cashout Reversed',
];

$page_title = WALLET_NAME;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Your Earnings</span>
    <h1 class="stitle"><?= sanitize(WALLET_NAME) ?></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="container py-5">
  <div class="stat-tile mb-4" style="max-width:320px;margin:0 auto 2rem;">
    <div class="stat-lbl mb-1">Current Balance</div>
    <div class="stat-num accent" style="font-size:2.2rem;"><?= format_price($balance) ?></div>
  </div>

  <?php if ($errors): ?>
    <div class="errmsg">
      <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="sucmsg is-visible"><p><?= sanitize($success) ?></p></div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-12 col-lg-5">
      <div class="panel-card">
        <h2 class="h6">Request GCash Cashout</h2>
        <form method="post">
          <input type="hidden" name="action" value="cashout">
          <div class="mb-2">
            <label class="flbl">Amount</label>
            <input type="number" step="0.01" min="<?= $min_cashout ?>" max="<?= $balance ?>" name="amount" class="fctrl" required>
            <div class="form-text">Minimum cashout: <?= format_price($min_cashout) ?></div>
          </div>
          <div class="mb-2">
            <label class="flbl">GCash Number</label>
            <input type="text" name="gcash_number" class="fctrl" required>
          </div>
          <div class="mb-3">
            <label class="flbl">GCash Account Name</label>
            <input type="text" name="gcash_name" class="fctrl" required>
          </div>
          <button type="submit" class="btn-red w-100 justify-content-center" <?= $balance < $min_cashout ? 'disabled' : '' ?>><i class="fas fa-money-bill-transfer"></i>Request Cashout</button>
        </form>
        <?php if ($balance < $min_cashout): ?>
          <p class="small text-muted mt-2 mb-0">You need at least <?= format_price($min_cashout) ?> in your <?= sanitize(WALLET_NAME) ?> to request a cashout.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <h2 class="h6">Your Cashout Requests</h2>
      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Amount</th><th>GCash #</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($cashouts->num_rows === 0): ?>
            <tr><td colspan="4" class="text-muted">No cashout requests yet.</td></tr>
          <?php endif; ?>
          <?php while ($c = $cashouts->fetch_assoc()): ?>
            <tr>
              <td><?= format_price($c['amount']) ?></td>
              <td><?= sanitize($c['gcash_number']) ?></td>
              <td><span class="pill pill-<?= $c['status'] ?>"><?= sanitize($c['status']) ?></span></td>
              <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <h2 class="h5 mb-3">Transaction History</h2>
  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
      <?php if ($transactions->num_rows === 0): ?>
        <tr><td colspan="4" class="text-muted">No wallet activity yet.</td></tr>
      <?php endif; ?>
      <?php while ($t = $transactions->fetch_assoc()): ?>
        <tr>
          <td><?= date('M j, Y g:i A', strtotime($t['created_at'])) ?></td>
          <td><?= sanitize($type_labels[$t['type']] ?? $t['type']) ?></td>
          <td><?= sanitize($t['description']) ?></td>
          <td class="text-end <?= $t['amount'] >= 0 ? 'amount-credit' : 'amount-debit' ?>">
            <?= $t['amount'] >= 0 ? '+' : '' ?><?= format_price($t['amount']) ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
