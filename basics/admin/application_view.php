<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

require_admin_login();

$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $admin_id = current_admin_id();

    if ($action === 'approve') {
        $weekly_limit = round((float) ($_POST['weekly_credit_limit'] ?? 0), 2);
        $emergency_limit = round((float) ($_POST['emergency_credit_limit'] ?? 0), 2);
        $stmt = $conn->prepare("UPDATE basics_members SET application_status = 'approved', membership_status = 'active',
                                 weekly_credit_limit = ?, emergency_credit_limit = ?, reviewed_by = ?, reviewed_at = NOW()
                                 WHERE id = ? AND application_status = 'pending'");
        $stmt->bind_param('ddii', $weekly_limit, $emergency_limit, $admin_id, $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'deny') {
        $notes = trim($_POST['admin_notes'] ?? '');
        $stmt = $conn->prepare("UPDATE basics_members SET application_status = 'denied', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                                 WHERE id = ? AND application_status = 'pending'");
        $stmt->bind_param('sii', $notes, $admin_id, $id);
        $stmt->execute();
        $stmt->close();
    }
    redirect('/basics/admin/application_view.php?id=' . $id);
}

$stmt = $conn->prepare("SELECT bm.*, u.full_name, u.username, u.email, u.contact_number, u.address, u.birthdate
                         FROM basics_members bm JOIN users u ON u.id = bm.user_id WHERE bm.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    redirect('/basics/admin/applications.php');
}

$stmt = $conn->prepare("SELECT * FROM basics_kyc_documents WHERE member_id = ? ORDER BY doc_type ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$documents = $stmt->get_result();

$doc_labels = [
    'valid_id_1' => 'Valid ID #1',
    'valid_id_2' => 'Valid ID #2',
    'barangay_clearance' => 'Barangay Clearance',
    'membership_application_form' => 'Membership Application Form',
    'certificate_of_employment' => 'Certificate of Employment / Work Clearance',
];

$page_title = 'Review Application';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/../../admin/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <a href="<?= BASE_URL ?>/basics/admin/applications.php" class="small">&larr; Back to Applications</a>
    <h1 class="stitle" style="font-size:2rem;"><?= sanitize($application['full_name']) ?></h1>
  </div>
</div>

<div class="container-fluid py-4">
  <div class="row g-4">
    <div class="col-12 col-md-6">
      <div class="panel-card mb-4">
        <h2 class="h6">Applicant</h2>
        <p class="mb-1">Username: <?= sanitize($application['username']) ?></p>
        <p class="mb-1">Email: <?= sanitize($application['email']) ?></p>
        <p class="mb-1">Contact #: <?= sanitize($application['contact_number']) ?></p>
        <p class="mb-1">Address: <?= sanitize($application['address']) ?></p>
        <p class="mb-0">Birthdate: <?= date('M j, Y', strtotime($application['birthdate'])) ?></p>
      </div>
      <div class="panel-card mb-4">
        <h2 class="h6">Employer</h2>
        <p class="mb-1">Employer: <?= sanitize($application['employer_name']) ?></p>
        <p class="mb-1">Contact: <?= $application['employer_contact'] ? sanitize($application['employer_contact']) : '—' ?></p>
        <p class="mb-0">Position: <?= $application['position'] ? sanitize($application['position']) : '—' ?></p>
      </div>
      <div class="panel-card">
        <h2 class="h6">Submitted Documents</h2>
        <?php if ($documents->num_rows === 0): ?>
          <p class="text-muted mb-0">No documents on file.</p>
        <?php endif; ?>
        <?php while ($doc = $documents->fetch_assoc()): ?>
          <p class="mb-2">
            <a href="<?= BASE_URL ?>/basics/admin/kyc_view.php?doc_id=<?= (int) $doc['id'] ?>" target="_blank" class="btn-chip btn-chip-outline">
              <i class="fas fa-file-arrow-down"></i> <?= sanitize($doc_labels[$doc['doc_type']] ?? $doc['doc_type']) ?>
            </a>
          </p>
        <?php endwhile; ?>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <div class="panel-card">
        <h2 class="h6">Application Status</h2>
        <p class="mb-3">Status: <span class="pill pill-<?= $application['application_status'] === 'approved' ? 'approved' : ($application['application_status'] === 'denied' ? 'rejected' : 'pending') ?>"><?= sanitize($application['application_status']) ?></span></p>

        <?php if ($application['application_status'] === 'pending'): ?>
          <form method="post" class="mb-4">
            <input type="hidden" name="action" value="approve">
            <h3 class="h6 mb-2">Approve &amp; Set Credit Line</h3>
            <div class="mb-2">
              <label class="flbl">Weekly Grocery Credit Limit (₱1,500&ndash;2,000)</label>
              <input type="number" step="0.01" min="0" name="weekly_credit_limit" class="fctrl" value="1500" required>
            </div>
            <div class="mb-3">
              <label class="flbl">Emergency Cash Credit Limit (up to ₱1,000)</label>
              <input type="number" step="0.01" min="0" max="1000" name="emergency_credit_limit" class="fctrl" value="0">
            </div>
            <button type="submit" class="btn-chip btn-chip-success" onclick="return confirm('Approve this application?');"><i class="fas fa-check"></i> Approve Membership</button>
          </form>
          <form method="post">
            <input type="hidden" name="action" value="deny">
            <h3 class="h6 mb-2">Deny</h3>
            <div class="mb-3">
              <label class="flbl">Reason (shown to applicant)</label>
              <textarea name="admin_notes" class="fctrl" rows="2"></textarea>
            </div>
            <button type="submit" class="btn-chip btn-chip-outline" onclick="return confirm('Deny this application?');"><i class="fas fa-xmark"></i> Deny Application</button>
          </form>
        <?php else: ?>
          <p class="mb-1">Weekly Credit Limit: <?= format_price($application['weekly_credit_limit']) ?></p>
          <p class="mb-1">Emergency Credit Limit: <?= format_price($application['emergency_credit_limit']) ?></p>
          <?php if ($application['admin_notes']): ?>
            <p class="mb-0 text-muted">Notes: <?= sanitize($application['admin_notes']) ?></p>
          <?php endif; ?>
          <p class="mt-3"><a href="<?= BASE_URL ?>/basics/admin/member_view.php?id=<?= (int) $application['id'] ?>" class="btn-chip btn-chip-outline">View Member</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
