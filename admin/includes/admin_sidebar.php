<?php
$admin_shell_open = true;

$current = basename($_SERVER['SCRIPT_NAME']);
// Detail/edit sub-pages highlight their parent list page in the sidebar.
$sub_to_parent = [
    'order_view.php'   => 'orders.php',
    'user_view.php'    => 'users.php',
    'product_edit.php' => 'products.php',
];
$current = $sub_to_parent[$current] ?? $current;

$nav_items = [
    'index.php'     => ['icon' => 'fa-gauge-high',     'label' => 'Dashboard'],
    'products.php'  => ['icon' => 'fa-box',             'label' => 'Manage Products'],
    'orders.php'    => ['icon' => 'fa-receipt',         'label' => 'Manage Orders'],
    'users.php'     => ['icon' => 'fa-users',           'label' => 'Manage Users'],
    'cashouts.php'  => ['icon' => 'fa-money-bill-wave', 'label' => 'Manage Cashouts'],
    'settings.php'  => ['icon' => 'fa-gear',            'label' => 'Settings'],
];
?>
<div class="admin-shell">
  <div class="offcanvas offcanvas-start offcanvas-lg admin-sidebar" tabindex="-1" id="adminSidebar">
    <div class="offcanvas-header d-lg-none">
      <div class="brand-logo-box">
        <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="<?= sanitize(APP_NAME) ?>" class="brand-logo" style="height:30px;">
      </div>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body admin-sidebar-body">
      <a href="<?= BASE_URL ?>/admin/index.php" class="admin-sidebar-brand d-none d-lg-flex">
        <div class="brand-logo-box">
          <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="<?= sanitize(APP_NAME) ?>" class="brand-logo" style="height:34px;">
        </div>
      </a>
      <nav class="admin-sidebar-nav">
        <?php foreach ($nav_items as $file => $item): ?>
          <a class="admin-nav-link <?= $current === $file ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/<?= $file ?>">
            <i class="fas <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-sidebar-bottom">
        <a class="admin-nav-link" href="<?= BASE_URL ?>/index.php" target="_blank"><i class="fas fa-arrow-up-right-from-square"></i> View Site</a>
        <a class="admin-nav-link" href="<?= BASE_URL ?>/admin/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>

  <div class="admin-main">
    <div class="admin-topbar d-lg-none">
      <button type="button" class="admin-topbar-toggle" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
        <i class="fas fa-bars"></i>
      </button>
      <div class="brand-logo-box">
        <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="<?= sanitize(APP_NAME) ?>" class="brand-logo" style="height:28px;">
      </div>
    </div>
    <div class="admin-content">