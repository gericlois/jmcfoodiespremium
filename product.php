<?php
require __DIR__ . '/config/constants.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    redirect('/menu.php');
}

$stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$highlights = $stmt->get_result();

$ref = $_GET['ref'] ?? '';
$register_url = BASE_URL . '/register.php' . ($ref ? '?ref=' . urlencode($ref) : '');

$page_title = $product['name'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<div class="container pt-4">
  <nav aria-label="breadcrumb" class="small">
    <a href="<?= BASE_URL ?>/menu.php" class="text-decoration-none">Shop</a>
    <span class="text-muted mx-1">/</span>
    <span class="text-muted"><?= sanitize($product['name']) ?></span>
  </nav>
</div>

<div class="container py-4">
  <div class="row g-5 align-items-start">
    <div class="col-lg-5">
      <?php if ($product['image']): ?>
        <img src="<?= UPLOAD_URL ?>products/<?= sanitize($product['image']) ?>" alt="<?= sanitize($product['name']) ?>" class="product-photo-lg">
      <?php endif; ?>
    </div>

    <div class="col-lg-7">
      <h1 class="h2 mb-2"><?= sanitize($product['name']) ?></h1>
      <div class="stitle mb-3" style="font-size:2.3rem;"><?= format_price($product['srp']) ?></div>

      <div class="d-flex flex-wrap gap-3 mb-4">
        <?php if (is_logged_in()): ?>
          <a href="<?= BASE_URL ?>/buy.php?product=<?= (int) $product['id'] ?>" class="btn-red"><i class="fas fa-bag-shopping"></i>Buy Now</a>
        <?php else: ?>
          <a href="<?= sanitize($register_url) ?>" class="btn-red"><i class="fas fa-user-plus"></i>Register to Buy</a>
          <a href="<?= BASE_URL ?>/login.php" class="btn-outline-theme"><i class="fas fa-right-to-bracket"></i>Login</a>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <div class="d-flex align-items-start gap-3 mb-3">
          <div class="hbi" style="width:40px;height:40px;font-size:1rem;flex-shrink:0;"><i class="fas fa-percent"></i></div>
          <div>
            <strong class="d-block"><?= (int) ((float) setting($conn, 'personal_rebate_rate', 0.20) * 100) ?>% Personal Rebate</strong>
            <span class="small text-muted">Credited to your <?= sanitize(WALLET_NAME) ?> once this order is delivered.</span>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3">
          <div class="hbi" style="width:40px;height:40px;font-size:1rem;flex-shrink:0;"><i class="fas fa-user-group"></i></div>
          <div>
            <strong class="d-block"><?= (int) ((float) setting($conn, 'referral_override_rate', 0.10) * 100) ?>% Referral Override</strong>
            <span class="small text-muted">Your referrer earns this too, if you were referred.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel-card mt-5">
    <h2 class="h5 mb-3">Product Description</h2>
    <?php if ($product['description']): ?>
      <p class="text-muted mb-0" style="white-space:pre-line;"><?= sanitize($product['description']) ?></p>
    <?php else: ?>
      <p class="text-muted mb-0">No description available yet.</p>
    <?php endif; ?>
  </div>

  <?php if ($highlights->num_rows > 0): ?>
  <div class="panel-card mt-4 text-center">
    <h2 class="h5 mb-3">Product Highlights</h2>
    <div class="d-flex flex-column align-items-center gap-4">
      <?php while ($h = $highlights->fetch_assoc()): ?>
        <img src="<?= UPLOAD_URL ?>products/<?= sanitize($h['image']) ?>" alt="<?= sanitize($product['name']) ?> highlights" class="highlight-poster rounded">
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
