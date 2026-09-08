<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';

$page_title = 'Shop';

$products = $conn->query("SELECT * FROM products WHERE status = 'active' ORDER BY name ASC");

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Our Products</span>
    <h1 class="stitle">Shop <span>JMC Foodies Wellness</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="shop-bg py-5">
  <div class="container">
    <div class="row g-4">
      <?php if ($products->num_rows === 0): ?>
        <p class="text-muted">No products are available right now.</p>
      <?php endif; ?>
      <?php while ($product = $products->fetch_assoc()): ?>
        <div class="col-6 col-md-3">
          <div class="panel-card h-100 d-flex flex-column p-0 overflow-hidden">
            <a href="<?= WELLNESS_URL ?>/product.php?id=<?= (int) $product['id'] ?>">
              <?php if ($product['image']): ?>
                <img src="<?= UPLOAD_URL ?>products/<?= sanitize($product['image']) ?>" alt="<?= sanitize($product['name']) ?>" class="product-photo">
              <?php endif; ?>
            </a>
            <div class="p-4 d-flex flex-column flex-grow-1 text-center">
              <h2 class="h5 mb-3"><a href="<?= WELLNESS_URL ?>/product.php?id=<?= (int) $product['id'] ?>" class="text-decoration-none" style="color:inherit;"><?= sanitize($product['name']) ?></a></h2>
              <div class="mt-auto">
                <div class="fw-bold mb-3" style="color:var(--primary);font-size:1.2rem;"><?= format_price($product['srp']) ?></div>
                <a href="<?= WELLNESS_URL ?>/product.php?id=<?= (int) $product['id'] ?>" class="btn-red justify-content-center w-100"><i class="fas fa-eye"></i>View Product</a>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
