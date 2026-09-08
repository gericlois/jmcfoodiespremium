<?php
require __DIR__ . '/../../config/constants.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../includes/auth.php';

require_basics_admin_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $label = trim($_POST['label'] ?? '');
    $order_open_date = trim($_POST['order_open_date'] ?? '');
    $order_cutoff_date = trim($_POST['order_cutoff_date'] ?? '');
    $payment_start_date = trim($_POST['payment_start_date'] ?? '');
    $payment_due_date = trim($_POST['payment_due_date'] ?? '');
    $delivery_date = trim($_POST['delivery_date'] ?? '');

    if ($label === '') $errors[] = 'Label is required.';
    foreach (['order_open_date', 'order_cutoff_date', 'payment_start_date', 'payment_due_date', 'delivery_date'] as $field) {
        if (!DateTime::createFromFormat('Y-m-d', $$field)) $errors[] = 'All dates are required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO basics_cycles (label, order_open_date, order_cutoff_date, payment_start_date, payment_due_date, delivery_date) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss', $label, $order_open_date, $order_cutoff_date, $payment_start_date, $payment_due_date, $delivery_date);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, 'create_basics_cycle', 'Created Basics cycle "' . $label . '" (' . $order_open_date . ' to ' . $order_cutoff_date . ')');
        redirect('/basics/admin/cycles.php');
    }
}

$cycles = $conn->query("SELECT * FROM basics_cycles ORDER BY order_open_date DESC");
$today = date('Y-m-d');

// Sensible defaults for the "create" form: next Monday through the following Monday.
$next_monday = date('Y-m-d', strtotime('next monday'));
$default_open = $next_monday;
$default_cutoff = date('Y-m-d', strtotime($next_monday . ' +4 days'));
$default_payment_start = date('Y-m-d', strtotime($next_monday . ' +5 days'));
$default_payment_due = date('Y-m-d', strtotime($next_monday . ' +6 days'));
$default_delivery = date('Y-m-d', strtotime($next_monday . ' +7 days'));

$page_title = 'Weekly Cycles';
require __DIR__ . '/../../admin/includes/admin_header.php';
require __DIR__ . '/includes/admin_sidebar.php';
?>
<div class="inner-hero" style="padding:36px 0;">
  <div class="container">
    <span class="slbl">JMC Foodies Basics</span>
    <h1 class="stitle" style="font-size:2rem;">Weekly Cycles</h1>
  </div>
</div>

<div class="container-fluid py-4">
  <?php if ($errors): ?>
    <div class="errmsg">
      <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= sanitize($error) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-5">
      <div class="panel-card">
        <h2 class="h6 mb-3">Create Cycle</h2>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="mb-2">
            <label class="flbl">Label</label>
            <input type="text" name="label" class="fctrl" placeholder="e.g. Week of Aug 4" required>
          </div>
          <div class="mb-2">
            <label class="flbl">Order Open (Monday)</label>
            <input type="date" name="order_open_date" class="fctrl" value="<?= $default_open ?>" required>
          </div>
          <div class="mb-2">
            <label class="flbl">Order Cutoff (Friday)</label>
            <input type="date" name="order_cutoff_date" class="fctrl" value="<?= $default_cutoff ?>" required>
          </div>
          <div class="mb-2">
            <label class="flbl">Payment Start (Saturday)</label>
            <input type="date" name="payment_start_date" class="fctrl" value="<?= $default_payment_start ?>" required>
          </div>
          <div class="mb-2">
            <label class="flbl">Payment Due (Sunday)</label>
            <input type="date" name="payment_due_date" class="fctrl" value="<?= $default_payment_due ?>" required>
          </div>
          <div class="mb-3">
            <label class="flbl">Delivery Date</label>
            <input type="date" name="delivery_date" class="fctrl" value="<?= $default_delivery ?>" required>
          </div>
          <button type="submit" class="btn-red w-100 justify-content-center"><i class="fas fa-plus"></i>Create Cycle</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn-outline-theme no-print" onclick="window.print()"><i class="fas fa-print"></i>Print</button>
      </div>
      <div class="table-responsive">
        <table class="table-theme">
          <thead><tr><th>Label</th><th>Order Window</th><th>Payment Window</th><th>Delivery</th></tr></thead>
          <tbody>
          <?php if ($cycles->num_rows === 0): ?>
            <tr><td colspan="4" class="text-muted">No cycles created yet.</td></tr>
          <?php endif; ?>
          <?php while ($c = $cycles->fetch_assoc()): ?>
            <?php $is_current = $today >= $c['order_open_date'] && $today <= $c['order_cutoff_date']; ?>
            <tr>
              <td><?= sanitize($c['label']) ?> <?php if ($is_current): ?><span class="pill pill-active">Open Now</span><?php endif; ?></td>
              <td><?= date('M j', strtotime($c['order_open_date'])) ?> &ndash; <?= date('M j', strtotime($c['order_cutoff_date'])) ?></td>
              <td><?= date('M j', strtotime($c['payment_start_date'])) ?> &ndash; <?= date('M j', strtotime($c['payment_due_date'])) ?></td>
              <td><?= date('M j, Y', strtotime($c['delivery_date'])) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../admin/includes/admin_footer.php'; ?>
