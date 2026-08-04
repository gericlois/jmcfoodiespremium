<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';

$page_title = 'Home';

$products = $conn->query("SELECT * FROM products WHERE status = 'active' ORDER BY name ASC");
$product_count = $products->num_rows;
$ref = $_GET['ref'] ?? '';
$register_url = WELLNESS_URL . '/register.php' . ($ref ? '?ref=' . urlencode($ref) : '');
$rebate_rate = (float) setting($conn, 'personal_rebate_rate', 0.20);
$override_rate = (float) setting($conn, 'referral_override_rate', 0.10);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<section id="hero">
  <div class="container">
    <div class="row align-items-center g-5" style="min-height:78vh;">
      <div class="col-lg-7 mx-auto text-center">
        <div class="hbadge mx-auto">
          <div class="hbi"><i class="fas fa-star"></i></div>
          <span>JMC Foodies Wellness Consumer Empowerment Program</span>
        </div>
        <h1 class="htitle">Earn Every Time<br/>You <span class="hl">Shop &amp; Share</span></h1>
        <p class="hdesc mx-auto">
          Get a <?= (int) ($rebate_rate * 100) ?>% personal rebate on your own purchases, plus a
          <?= (int) ($override_rate * 100) ?>% referral override on every purchase made by the friends
          you personally invite.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mb-2">
          <?php if (is_logged_in()): ?>
            <a href="<?= WELLNESS_URL ?>/dashboard.php" class="btn-red"><i class="fas fa-gauge-high"></i>Go to Dashboard</a>
          <?php else: ?>
            <a href="<?= sanitize($register_url) ?>" class="btn-red"><i class="fas fa-user-plus"></i>Register Now</a>
            <a href="<?= BASE_URL ?>/login.php" class="btn-outline-theme"><i class="fas fa-right-to-bracket"></i>Login</a>
          <?php endif; ?>
        </div>
        <div class="hstats d-flex justify-content-center gap-3 flex-wrap mt-4">
          <div class="hstat"><span class="snum"><?= (int) ($rebate_rate * 100) ?><em>%</em></span><small>Purchase Rebate</small></div>
          <div class="sdiv"></div>
          <div class="hstat"><span class="snum"><?= (int) ($override_rate * 100) ?><em>%</em></span><small>Purchase Override</small></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW EARNING WORKS -->
<section id="how-it-works">
  <div class="container">
    <div class="text-center mb-5">
      <span class="slbl">How It Works</span>
      <h2 class="stitle">Three Ways to <span>Earn</span></h2>
      <div class="sline"></div>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="stat-tile h-100">
          <div class="hbi mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;"><i class="fas fa-cart-shopping"></i></div>
          <h3 class="h5">Purchase Rebate</h3>
          <p class="text-muted mb-0">Earn <?= (int) ($rebate_rate * 100) ?>% back on every purchase you make yourself &mdash; credited straight to your <?= sanitize(WALLET_NAME) ?>.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-tile h-100">
          <div class="hbi mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;"><i class="fas fa-user-group"></i></div>
          <h3 class="h5">Purchase Override</h3>
          <p class="text-muted mb-0">Share your unique referral link. Earn <?= (int) ($override_rate * 100) ?>% on every purchase your direct referral makes &mdash; for as long as they keep buying.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-tile h-100">
          <div class="hbi mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;"><i class="fas fa-wallet"></i></div>
          <h3 class="h5"><?= sanitize(WALLET_NAME) ?> &amp; Cashout</h3>
          <p class="text-muted mb-0">Cash out your earnings via GCash, or use your <?= sanitize(WALLET_NAME) ?> balance as credit toward your next purchase.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PRODUCTS -->
<?php if ($product_count > 0): $products->data_seek(0); ?>
<section id="product" class="shop-bg">
  <div class="container">
    <div class="text-center mb-5">
      <span class="slbl">Our Products</span>
      <h2 class="stitle">What We're <span>Selling</span></h2>
      <div class="sline"></div>
    </div>
    <div class="row g-4 justify-content-center">
      <?php while ($product = $products->fetch_assoc()): ?>
        <div class="col-6 col-md-3">
          <div class="panel-card h-100 d-flex flex-column p-0 overflow-hidden text-center">
            <a href="<?= WELLNESS_URL ?>/product.php?id=<?= (int) $product['id'] ?>">
              <?php if ($product['image']): ?>
                <img src="<?= UPLOAD_URL ?>products/<?= sanitize($product['image']) ?>" alt="<?= sanitize($product['name']) ?>" class="product-photo">
              <?php endif; ?>
            </a>
            <div class="p-4 d-flex flex-column flex-grow-1">
              <h3 class="h5 mb-1"><?= sanitize($product['name']) ?></h3>
              <p class="text-muted small mb-3">Suggested Retail Price</p>
              <div class="stitle mb-3" style="font-size:1.9rem;">₱<?= number_format($product['srp'], 2) ?></div>
              <div class="mt-auto">
                <a href="<?= WELLNESS_URL ?>/product.php?id=<?= (int) $product['id'] ?>" class="btn-red justify-content-center w-100"><i class="fas fa-eye"></i>View Product</a>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
    <div class="text-center mt-5">
      <a href="<?= WELLNESS_URL ?>/menu.php" class="btn-outline-theme"><i class="fas fa-store"></i>View Full Shop</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CTA BANNER -->
<section id="special">
  <div class="spbg"></div>
  <div class="container text-center" style="position:relative;z-index:2;">
    <div class="sptag mx-auto"><i class="fas fa-bolt me-1"></i>Referral Code Required</div>
    <h2 class="sptitle">Ready to Start<br/>Earning With <span>Your Network?</span></h2>
    <p class="spdesc mx-auto" style="max-width:520px;">Registration requires a referral code from an existing member &mdash; ask whoever invited you, or use the link they shared.</p>
    <?php if (!is_logged_in()): ?>
      <a href="<?= sanitize($register_url) ?>" class="btn-red"><i class="fas fa-user-plus"></i>Register Now</a>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
