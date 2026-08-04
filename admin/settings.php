<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

require_admin_login();

function save_setting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rebate_pct = (float) ($_POST['personal_rebate_rate'] ?? 0);
    $override_pct = (float) ($_POST['referral_override_rate'] ?? 0);
    $min_cashout = (float) ($_POST['min_cashout_amount'] ?? 0);
    $fee_pct = (float) ($_POST['cashout_processing_fee_rate'] ?? 0);
    $company_email = trim($_POST['company_email'] ?? '');

    if ($rebate_pct <= 0 || $rebate_pct > 100) $errors[] = 'Personal rebate rate must be between 0 and 100%.';
    if ($override_pct <= 0 || $override_pct > 100) $errors[] = 'Referral override rate must be between 0 and 100%.';
    if ($min_cashout <= 0) $errors[] = 'Minimum cashout amount must be greater than 0.';
    if ($fee_pct < 0 || $fee_pct > 100) $errors[] = 'Cashout processing fee must be between 0 and 100%.';
    if (!filter_var($company_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid company email is required.';

    if (empty($errors)) {
        save_setting($conn, 'personal_rebate_rate', (string) round($rebate_pct / 100, 4));
        save_setting($conn, 'referral_override_rate', (string) round($override_pct / 100, 4));
        save_setting($conn, 'min_cashout_amount', (string) round($min_cashout, 2));
        save_setting($conn, 'cashout_processing_fee_rate', (string) round($fee_pct / 100, 4));
        save_setting($conn, 'company_email', $company_email);
        redirect('/admin/settings.php?saved=1');
    }
}

$rebate_rate = (float) setting($conn, 'personal_rebate_rate', 0.20);
$override_rate = (float) setting($conn, 'referral_override_rate', 0.10);
$min_cashout_val = (float) setting($conn, 'min_cashout_amount', 1000.00);
$fee_rate_val = (float) setting($conn, 'cashout_processing_fee_rate', 0.10);
$company_email_val = setting($conn, 'company_email', 'support@example.com');

$page_title = 'Settings';
require __DIR__ . '/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">Configure</span>
    <h1 class="stitle" style="font-size:2rem;">Settings</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <?php if (isset($_GET['saved'])): ?>
    <div class="sucmsg is-visible"><p>Settings saved.</p></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="errmsg">
      <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-12 col-lg-7">
      <div class="panel-card">
        <form method="post">
          <h2 class="h6 mb-3">Earnings Rates</h2>
          <div class="row">
            <div class="col-sm-6 mb-3">
              <label class="flbl">Personal Rebate Rate (%)</label>
              <input type="number" step="0.01" min="0" max="100" name="personal_rebate_rate" class="fctrl" value="<?= sanitize($rebate_rate * 100) ?>" required>
              <div class="form-text">% a member earns on their own purchases.</div>
            </div>
            <div class="col-sm-6 mb-3">
              <label class="flbl">Referral Override Rate (%)</label>
              <input type="number" step="0.01" min="0" max="100" name="referral_override_rate" class="fctrl" value="<?= sanitize($override_rate * 100) ?>" required>
              <div class="form-text">% a member earns on their direct referral's purchases.</div>
            </div>
          </div>

          <h2 class="h6 mb-3 mt-2">Wallet</h2>
          <div class="row">
            <div class="col-sm-6 mb-3">
              <label class="flbl">Minimum Cashout Amount (₱)</label>
              <input type="number" step="0.01" min="0" name="min_cashout_amount" class="fctrl" value="<?= sanitize($min_cashout_val) ?>" required>
            </div>
            <div class="col-sm-6 mb-3">
              <label class="flbl">Cashout Processing Fee (%)</label>
              <input type="number" step="0.01" min="0" max="100" name="cashout_processing_fee_rate" class="fctrl" value="<?= sanitize($fee_rate_val * 100) ?>" required>
              <div class="form-text">Deducted from the payout, not added to what's debited from the wallet.</div>
            </div>
          </div>

          <h2 class="h6 mb-3 mt-2">Company Info</h2>
          <div class="mb-3">
            <label class="flbl">Company Email</label>
            <input type="email" name="company_email" class="fctrl" value="<?= sanitize($company_email_val) ?>" required>
            <div class="form-text">Shown in the site header and footer.</div>
          </div>

          <button type="submit" class="btn-red"><i class="fas fa-floppy-disk"></i>Save Settings</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
