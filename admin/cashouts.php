<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM cashouts WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cashout = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cashout) {
        $admin_id = current_admin_id();
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE cashouts SET status = 'approved', processed_by = ?, processed_at = NOW() WHERE id = ?");
            $stmt->bind_param('ii', $admin_id, $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'reject') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE cashouts SET status = 'rejected', processed_by = ?, processed_at = NOW() WHERE id = ?");
                $stmt->bind_param('ii', $admin_id, $id);
                $stmt->execute();
                $stmt->close();

                wallet_credit($conn, $cashout['user_id'], 'cashout_reversal', $cashout['amount'], null, $id,
                    'Cashout request #' . $id . ' rejected — funds returned');

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
    redirect('/admin/cashouts.php');
}

$valid_statuses = ['pending', 'approved', 'rejected'];
$status_filter = $_GET['status'] ?? 'pending';

$sql = "SELECT c.*, u.full_name, u.username FROM cashouts c JOIN users u ON u.id = c.user_id";
if (in_array($status_filter, $valid_statuses, true)) {
    $sql .= " WHERE c.status = '" . $conn->real_escape_string($status_filter) . "'";
}
$sql .= " ORDER BY c.created_at DESC";
$cashouts = $conn->query($sql);

$page_title = 'Cashouts';
require __DIR__ . '/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">Manage</span>
    <h1 class="stitle" style="font-size:2rem;">Cashout Requests</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?= BASE_URL ?>/admin/cashouts.php?status=" class="filter-pill <?= $status_filter === '' ? 'active' : '' ?>">All</a>
    <?php foreach ($valid_statuses as $status): ?>
      <a href="<?= BASE_URL ?>/admin/cashouts.php?status=<?= $status ?>"
         class="filter-pill text-capitalize <?= $status_filter === $status ? 'active' : '' ?>"><?= $status ?></a>
    <?php endforeach; ?>
  </div>

  <div class="table-responsive">
    <table class="table-theme">
      <thead><tr><th>User</th><th>Amount</th><th>GCash #</th><th>GCash Name</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php if ($cashouts->num_rows === 0): ?>
        <tr><td colspan="7" class="text-muted">No cashout requests found.</td></tr>
      <?php endif; ?>
      <?php while ($c = $cashouts->fetch_assoc()): ?>
        <tr>
          <td><?= sanitize($c['full_name']) ?> <span class="text-muted small">(<?= sanitize($c['username']) ?>)</span></td>
          <td><?= format_price($c['amount']) ?></td>
          <td><?= sanitize($c['gcash_number']) ?></td>
          <td><?= sanitize($c['gcash_name']) ?></td>
          <td><span class="pill pill-<?= $c['status'] ?>"><?= sanitize($c['status']) ?></span></td>
          <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
          <td>
            <?php if ($c['status'] === 'pending'): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Mark this cashout as paid via GCash?');">Approve</button>
              </form>
              <form method="post" class="d-inline">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Reject and return funds to the user\'s wallet?');">Reject</button>
              </form>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
