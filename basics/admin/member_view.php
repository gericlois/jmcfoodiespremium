<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_basics_admin_login();

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_credit') {
        $weekly_limit = round((float) ($_POST['weekly_credit_limit'] ?? 0), 2);
        $emergency_limit = round((float) ($_POST['emergency_credit_limit'] ?? 0), 2);
        $unfreeze = isset($_POST['unfreeze']) ? 0 : null;
        if ($unfreeze === null) {
            $stmt = $conn->prepare("UPDATE basics_members SET weekly_credit_limit = ?, emergency_credit_limit = ? WHERE id = ?");
            $stmt->bind_param('ddi', $weekly_limit, $emergency_limit, $id);
        } else {
            $stmt = $conn->prepare("UPDATE basics_members SET weekly_credit_limit = ?, emergency_credit_limit = ?, credit_limit_frozen = 0 WHERE id = ?");
            $stmt->bind_param('ddi', $weekly_limit, $emergency_limit, $id);
        }
        $stmt->execute();
        $stmt->close();
        log_activity($conn, 'update_basics_credit', 'Updated credit line for Basics member #' . $id . ' (weekly ' . format_price($weekly_limit) . ', emergency ' . format_price($emergency_limit) . ')');
    } elseif ($action === 'suspend') {
        $stmt = $conn->prepare("UPDATE basics_members SET membership_status = 'suspended' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, 'suspend_basics_member', 'Suspended Basics member #' . $id);
    } elseif ($action === 'reinstate') {
        $stmt = $conn->prepare("UPDATE basics_members SET membership_status = 'active', suspended_until = NULL WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, 'reinstate_basics_member', 'Reinstated Basics member #' . $id);
    } elseif ($action === 'terminate') {
        $stmt = $conn->prepare("UPDATE basics_members SET membership_status = 'terminated' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, 'terminate_basics_member', 'Terminated Basics member #' . $id);
    }
    redirect('/basics/admin/member_view.php?id=' . $id);
}

$stmt = $conn->prepare("SELECT bm.*, u.full_name, u.username, u.email, u.contact_number
                         FROM basics_members bm JOIN basics_users u ON u.id = bm.user_id WHERE bm.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    redirect('/basics/admin/members.php');
}

$outstanding = basics_outstanding_balance($conn, $member['id']);

$stmt = $conn->prepare("SELECT o.*, c.label AS cycle_label FROM basics_orders o
                         JOIN basics_cycles c ON c.id = o.cycle_id
                         WHERE o.member_id = ? AND o.status != 'draft' ORDER BY o.created_at DESC LIMIT 10");
$stmt->bind_param('i', $id);
$stmt->execute();
$orders = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM basics_payments WHERE member_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param('i', $id);
$stmt->execute();
$payments = $stmt->get_result();

$page_title = $member['full_name'];
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <a href="<?= BASE_URL ?>/basics/admin/members.php" class="small">&larr; Back to Members</a>
    <h1 class="stitle" style="font-size:2rem;"><?= sanitize($member['full_name']) ?></h1>
  </div>
</div>

<div class="container-fluid py-4">
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num" style="font-size:1.3rem;"><?= format_price($member['weekly_credit_limit']) ?></div><div class="stat-lbl">Weekly Limit</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num accent" style="font-size:1.3rem;"><?= format_price($outstanding) ?></div><div class="stat-lbl">Outstanding</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num"><?= (int) $member['offense_count'] ?></div><div class="stat-lbl">Offenses</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-tile"><div class="stat-num"><?= (int) $member['consecutive_on_time_payments'] ?></div><div class="stat-lbl">On-Time Streak</div></div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
      <div class="panel-card mb-4">
        <h2 class="h6">Profile</h2>
        <p class="mb-1">Username: <?= sanitize($member['username']) ?></p>
        <p class="mb-1">Email: <?= sanitize($member['email']) ?></p>
        <p class="mb-1">Contact #: <?= sanitize($member['contact_number']) ?></p>
        <p class="mb-1">Employer: <?= sanitize($member['employer_name']) ?></p>
        <p class="mb-3">Status: <span class="pill pill-<?= $member['membership_status'] === 'active' ? 'active' : ($member['membership_status'] === 'dormant' ? 'pending' : 'suspended') ?>"><?= sanitize($member['membership_status']) ?></span>
          <?php if ($member['credit_limit_frozen']): ?><span class="pill pill-rejected">Credit Frozen</span><?php endif; ?>
        </p>
        <?php if ($member['consecutive_on_time_payments'] >= 12): ?>
          <div class="sucmsg is-visible mb-3"><p class="mb-0">Eligible for a higher credit limit (12+ on-time payments).</p></div>
        <?php endif; ?>

        <?php if ($member['membership_status'] === 'active'): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="suspend">
            <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Suspend this member?');">Suspend</button>
          </form>
        <?php elseif (in_array($member['membership_status'], ['suspended', 'dormant'], true)): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="reinstate">
            <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Reinstate this member?');">Reinstate</button>
          </form>
        <?php endif; ?>
        <?php if ($member['membership_status'] !== 'terminated'): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="terminate">
            <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Permanently terminate this membership? This cannot be undone.');">Terminate</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <h2 class="h6">Adjust Credit Line</h2>
        <form method="post">
          <input type="hidden" name="action" value="update_credit">
          <div class="mb-2">
            <label class="flbl">Weekly Credit Limit</label>
            <input type="number" step="0.01" min="0" name="weekly_credit_limit" class="fctrl" value="<?= sanitize($member['weekly_credit_limit']) ?>" required>
          </div>
          <div class="mb-2">
            <label class="flbl">Emergency Credit Limit</label>
            <input type="number" step="0.01" min="0" name="emergency_credit_limit" class="fctrl" value="<?= sanitize($member['emergency_credit_limit']) ?>">
          </div>
          <?php if ($member['credit_limit_frozen']): ?>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="unfreeze" id="unfreezeCheck" value="1">
              <label class="form-check-label" for="unfreezeCheck">Unfreeze credit limit</label>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn-chip btn-chip-success"><i class="fas fa-floppy-disk"></i> Save</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <h2 class="h6 mb-3">Recent Orders</h2>
      <div class="table-responsive mb-4">
        <table class="table-theme">
          <thead><tr><th>Cycle</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($orders->num_rows === 0): ?>
            <tr><td colspan="3" class="text-muted">No orders yet.</td></tr>
          <?php endif; ?>
          <?php while ($o = $orders->fetch_assoc()): ?>
            <tr>
              <td><?= sanitize($o['cycle_label']) ?></td>
              <td><?= format_price($o['total_amount']) ?></td>
              <td><span class="pill pill-<?= $o['status'] === 'placed' ? 'processing' : ($o['status'] === 'delivered' ? 'completed' : 'cancelled') ?>"><?= sanitize($o['status']) ?></span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <h2 class="h6 mb-3">Payment History</h2>
      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Paid</th><th>Penalty</th><th>On Time?</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($payments->num_rows === 0): ?>
            <tr><td colspan="4" class="text-muted">No payments yet.</td></tr>
          <?php endif; ?>
          <?php while ($p = $payments->fetch_assoc()): ?>
            <tr>
              <td><?= format_price($p['amount_paid']) ?></td>
              <td><?= format_price($p['penalty_amount']) ?></td>
              <td><?= $p['is_late'] ? 'No' : 'Yes' ?></td>
              <td><?= date('M j, Y', strtotime($p['paid_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
