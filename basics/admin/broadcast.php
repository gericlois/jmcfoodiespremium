<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_basics_admin_login();

// Deliberate, admin-initiated broadcasts always send regardless of the
// basics_sms_notifications_enabled toggle (that flag only gates the
// automatic triggers in basics_notify()) — so this calls send_sms()
// directly rather than going through basics_notify().
function basics_broadcast_recipients($conn, $audience) {
    if ($audience === 'active') {
        $sql = "SELECT u.contact_number FROM basics_users u
                JOIN basics_members bm ON bm.user_id = u.id
                WHERE bm.application_status = 'approved' AND bm.membership_status IN ('active','dormant')";
    } elseif ($audience === 'pending') {
        $sql = "SELECT u.contact_number FROM basics_users u
                JOIN basics_members bm ON bm.user_id = u.id
                WHERE bm.application_status = 'pending'";
    } else {
        $sql = "SELECT contact_number FROM basics_users";
    }
    $numbers = [];
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        if (trim((string) $row['contact_number']) !== '') {
            $numbers[] = trim($row['contact_number']);
        }
    }
    return $numbers;
}

$audiences = [
    'active'  => 'Active & Approved Members',
    'pending' => 'Pending Applicants',
    'all'     => 'All Basics Accounts',
];

$errors = [];
$sent_count = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $audience = $_POST['audience'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if (!isset($audiences[$audience])) {
        $errors[] = 'Choose a valid audience.';
    }
    if ($message === '') {
        $errors[] = 'Enter a message.';
    } elseif (strlen($message) > 480) {
        $errors[] = 'Message is too long (max 480 characters, about 3 SMS segments).';
    }

    if (empty($errors)) {
        $numbers = basics_broadcast_recipients($conn, $audience);
        $sent_count = 0;
        // Semaphore accepts up to 1000 comma-separated numbers per call.
        foreach (array_chunk($numbers, 1000) as $chunk) {
            if (send_sms(implode(',', $chunk), $message)) {
                $sent_count += count($chunk);
            }
        }
        log_activity($conn, 'send_basics_broadcast', 'Sent Basics announcement to ' . $sent_count . ' recipient(s) (' . $audiences[$audience] . ')');
    }
}

$page_title = 'Announcement Broadcast';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">JMC Foodies Basics</span>
    <h1 class="stitle" style="font-size:2rem;">Announcement Broadcast</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <?php if ($sent_count !== null): ?>
    <div class="sucmsg is-visible"><p>Announcement sent to <?= (int) $sent_count ?> recipient(s).</p></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="errmsg">
      <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-12 col-lg-7">
      <div class="panel-card">
        <?php if (!defined('SEMAPHORE_API_KEY') || SEMAPHORE_API_KEY === ''): ?>
          <div class="errmsg mb-3"><p class="mb-0">SMS is not configured yet — set up your Semaphore API key first (see Settings).</p></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="send">
          <div class="mb-3">
            <label class="flbl">Audience</label>
            <select name="audience" class="fctrl" required>
              <?php foreach ($audiences as $key => $label): ?>
                <option value="<?= $key ?>"><?= sanitize($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="flbl">Message</label>
            <textarea name="message" class="fctrl" rows="5" maxlength="480" required placeholder="e.g. Order cutoff for this week has been moved to Friday 5PM."></textarea>
            <div class="form-text">Max 480 characters (~3 SMS segments). Keep it clear and short.</div>
          </div>
          <button type="submit" class="btn-red" onclick="return confirm('Send this SMS to the selected audience? This will use real SMS credits.');"><i class="fas fa-paper-plane"></i>Send Announcement</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
