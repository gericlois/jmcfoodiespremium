<?php
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/functions.php';

require_basics_access($conn);

$member = basics_get_member($conn, current_user_id());
$cycle = basics_active_order_cycle($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    if ($cycle) {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $stmt = $conn->prepare("SELECT * FROM basics_products WHERE id = ? AND status = 'active'");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($product) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT id FROM basics_orders WHERE member_id = ? AND cycle_id = ? AND status = 'draft'");
                $stmt->bind_param('ii', $member['id'], $cycle['id']);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$order) {
                    $stmt = $conn->prepare("INSERT INTO basics_orders (member_id, cycle_id, status) VALUES (?, ?, 'draft')");
                    $stmt->bind_param('ii', $member['id'], $cycle['id']);
                    $stmt->execute();
                    $order_id = $stmt->insert_id;
                    $stmt->close();
                } else {
                    $order_id = $order['id'];
                }

                $stmt = $conn->prepare("SELECT id, quantity FROM basics_order_items WHERE order_id = ? AND product_id = ?");
                $stmt->bind_param('ii', $order_id, $product_id);
                $stmt->execute();
                $existing_item = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing_item) {
                    $new_qty = $existing_item['quantity'] + $quantity;
                    $line_total = round($product['srp'] * $new_qty, 2);
                    $stmt = $conn->prepare("UPDATE basics_order_items SET quantity = ?, line_total = ? WHERE id = ?");
                    $stmt->bind_param('idi', $new_qty, $line_total, $existing_item['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $line_total = round($product['srp'] * $quantity, 2);
                    $stmt = $conn->prepare("INSERT INTO basics_order_items (order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiidd', $order_id, $product_id, $quantity, $product['srp'], $line_total);
                    $stmt->execute();
                    $stmt->close();
                }

                $stmt = $conn->prepare("UPDATE basics_orders SET total_amount = (SELECT COALESCE(SUM(line_total),0) FROM basics_order_items WHERE order_id = ?) WHERE id = ?");
                $stmt->bind_param('ii', $order_id, $order_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                redirect('/basics/catalog.php?added=1');
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
}

$category_filter = $_GET['category'] ?? '';
$valid_categories = ['Bigas', 'Pang-almusal', 'Pang-ulam'];
$sql = "SELECT * FROM basics_products WHERE status = 'active'";
if (in_array($category_filter, $valid_categories, true)) {
    $sql .= " AND category = '" . $conn->real_escape_string($category_filter) . "'";
}
$sql .= " ORDER BY category ASC, name ASC";
$products = $conn->query($sql);

$page_title = 'Catalog';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<div class="inner-hero">
  <div class="container">
    <span class="slbl">Grocery Catalog</span>
    <h1 class="stitle">Browse <span>Basic Needs</span></h1>
    <div class="sline"></div>
  </div>
</div>

<div class="shop-bg py-5">
  <div class="container">
    <?php if (!$cycle): ?>
      <div class="errmsg mb-4">
        <p class="mb-0">No ordering window is open right now &mdash; you can browse, but adding to cart is disabled until Monday.</p>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['added'])): ?>
      <div class="sucmsg is-visible mb-4"><p>Added to cart! <a href="<?= BASICS_URL ?>/cart.php">View Cart</a></p></div>
    <?php endif; ?>

    <div class="mb-3">
      <div class="position-relative">
        <input type="text" id="catalogSearch" class="fctrl" placeholder="Search by product name or SKU..." style="padding-left:40px;" autocomplete="off">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#999;"></i>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <a href="<?= BASICS_URL ?>/catalog.php" class="filter-pill <?= $category_filter === '' ? 'active' : '' ?>">All</a>
      <?php foreach ($valid_categories as $cat): ?>
        <a href="<?= BASICS_URL ?>/catalog.php?category=<?= urlencode($cat) ?>" class="filter-pill <?= $category_filter === $cat ? 'active' : '' ?>"><?= sanitize($cat) ?></a>
      <?php endforeach; ?>
    </div>

    <p id="catalogNoResults" class="text-muted" style="display:none;">No products match your search.</p>

    <div class="row g-4" id="catalogGrid">
      <?php if ($products->num_rows === 0): ?>
        <p class="text-muted">No products in this category yet.</p>
      <?php endif; ?>
      <?php while ($product = $products->fetch_assoc()): ?>
        <div class="col-6 col-md-3 catalog-item" data-name="<?= sanitize(strtolower($product['name'])) ?>" data-sku="<?= sanitize(strtolower($product['sku'])) ?>">
          <div class="panel-card catalog-card h-100 d-flex flex-column p-0 overflow-hidden">
            <?php if ($product['image']): ?>
              <img src="<?= UPLOAD_URL ?>basics_products/<?= sanitize($product['image']) ?>" alt="<?= sanitize($product['name']) ?>" class="product-photo">
            <?php endif; ?>
            <div class="p-3 d-flex flex-column flex-grow-1 text-center">
              <div class="small text-muted mb-1"><?= sanitize($product['unit']) ?></div>
              <h3 class="h6 mb-2"><?= sanitize($product['name']) ?></h3>
              <div class="fw-bold mb-3" style="color:var(--primary);">
                <?= $product['srp'] > 0 ? format_price($product['srp']) : 'TBD' ?>
              </div>
              <?php if ($cycle && $product['srp'] > 0): ?>
                <form method="post" class="mt-auto d-flex gap-2">
                  <input type="hidden" name="action" value="add_to_cart">
                  <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                  <input type="number" name="quantity" value="1" min="1" class="fctrl" style="width:64px;">
                  <button type="submit" class="btn-red flex-grow-1 justify-content-center"><i class="fas fa-cart-plus"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<script>
  var catalogSearch = document.getElementById('catalogSearch');
  var catalogItems = document.querySelectorAll('.catalog-item');
  var catalogNoResults = document.getElementById('catalogNoResults');

  catalogSearch.addEventListener('input', function () {
    var query = catalogSearch.value.trim().toLowerCase();
    var visibleCount = 0;

    catalogItems.forEach(function (item) {
      var matches = item.dataset.name.indexOf(query) !== -1 || item.dataset.sku.indexOf(query) !== -1;
      item.style.display = matches ? '' : 'none';
      if (matches) visibleCount++;
    });

    catalogNoResults.style.display = (query && visibleCount === 0) ? '' : 'none';
  });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
