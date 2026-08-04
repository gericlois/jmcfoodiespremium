<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'flag_dormant') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE basics_members SET membership_status = 'dormant' WHERE id = ? AND membership_status = 'active'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    redirect('/basics/admin/dormancy.php');
}

$dormancy_weeks = (int) setting($conn, 'basics_dormancy_weeks', 3);
$cutoff = date('Y-m-d H:i:s', strtotime('-' . $dormancy_weeks . ' weeks'));

$stmt = $conn->prepare("SELECT bm.*, u.full_name, u.username FROM basics_members bm
                         JOIN users u ON u.id = bm.user_id
                         WHERE bm.membership_status = 'active' AND bm.application_status = 'approved'
                           AND (bm.last_activity_at IS NULL OR bm.last_activity_at < ?)
                           AND bm.applied_at < ?
                         ORDER BY bm.last_activity_at ASC");
$stmt->bind_param('ss', $cutoff, $cutoff);
$stmt->execute();
$dormant_candidates = $stmt->get_result();

$page_title = 'Dormancy Report';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/../../admin/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">JMC Foodies Basics</span>
    <h1 class="stitle" style="font-size:2rem;">Dormancy Report</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <p class="text-muted mb-4">Active members with no order or payment activity in the last <?= $dormancy_weeks ?> consecutive weeks. Review and flag as dormant if appropriate &mdash; this is a manual step, not automatic.</p>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>Member</th><th>Last Activity</th><th>Weekly Limit</th><th></th></tr></thead>
      <tbody>
      <?php if ($dormant_candidates->num_rows === 0): ?>
        <tr><td colspan="4" class="text-muted">No members currently flagged for review.</td></tr>
      <?php endif; ?>
      <?php while ($m = $dormant_candidates->fetch_assoc()): ?>
        <tr>
          <td><?= sanitize($m['full_name']) ?> <span class="text-muted small">(<?= sanitize($m['username']) ?>)</span></td>
          <td><?= $m['last_activity_at'] ? date('M j, Y', strtotime($m['last_activity_at'])) : 'Never' ?></td>
          <td><?= format_price($m['weekly_credit_limit']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/basics/admin/member_view.php?id=<?= (int) $m['id'] ?>" class="btn-chip btn-chip-outline">View</a>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="flag_dormant">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Flag this member as dormant?');">Flag Dormant</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
