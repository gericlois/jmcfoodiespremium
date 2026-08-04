<?php
$admin_shell_open = true;

// Path-based (not basename) active-link match — basics/admin/products.php and
// admin/products.php share a basename, so basename-only matching would
// collide between the two modules' sidebar entries.
$current_path = BASE_URL !== '' && strpos($_SERVER['REQUEST_URI'], BASE_URL) === 0
    ? substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE_URL))
    : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Detail/edit sub-pages highlight their parent list page in the sidebar.
$sub_to_parent = [
    '/admin/order_view.php'          => '/admin/orders.php',
    '/admin/user_view.php'           => '/admin/users.php',
    '/admin/product_edit.php'        => '/admin/products.php',
    '/basics/admin/application_view.php' => '/basics/admin/applications.php',
    '/basics/admin/member_view.php'  => '/basics/admin/members.php',
    '/basics/admin/product_edit.php' => '/basics/admin/products.php',
    '/basics/admin/order_view.php'   => '/basics/admin/orders.php',
];
$current_path = $sub_to_parent[$current_path] ?? $current_path;

$pending_basics_count = (int) $conn->query("SELECT COUNT(*) AS c FROM basics_members WHERE application_status = 'pending'")->fetch_assoc()['c'];
$pending_wellness_users = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 'pending'")->fetch_assoc()['c'];
$pending_cashouts_count = (int) $conn->query("SELECT COUNT(*) AS c FROM cashouts WHERE status = 'pending'")->fetch_assoc()['c'];

$nav_groups = [
    'Overview' => [
        '/admin/index.php' => ['icon' => 'fa-gauge-high', 'label' => 'Dashboard'],
    ],
    'Wellness' => [
        '/admin/products.php' => ['icon' => 'fa-box',             'label' => 'Manage Products'],
        '/admin/orders.php'   => ['icon' => 'fa-receipt',         'label' => 'Manage Orders'],
        '/admin/users.php'    => ['icon' => 'fa-users',           'label' => 'Manage Users', 'badge' => $pending_wellness_users],
        '/admin/cashouts.php' => ['icon' => 'fa-money-bill-wave', 'label' => 'Manage Cashouts', 'badge' => $pending_cashouts_count],
    ],
    'Basics' => [
        '/basics/admin/applications.php' => ['icon' => 'fa-file-signature', 'label' => 'Applications', 'badge' => $pending_basics_count],
        '/basics/admin/members.php'      => ['icon' => 'fa-users',          'label' => 'Members'],
        '/basics/admin/products.php'     => ['icon' => 'fa-box',            'label' => 'Manage Products'],
        '/basics/admin/cycles.php'       => ['icon' => 'fa-calendar-week',  'label' => 'Weekly Cycles'],
        '/basics/admin/orders.php'       => ['icon' => 'fa-receipt',        'label' => 'Manage Orders'],
        '/basics/admin/payments.php'     => ['icon' => 'fa-money-bill-wave', 'label' => 'Payments'],
        '/basics/admin/dormancy.php'     => ['icon' => 'fa-user-clock',     'label' => 'Dormancy Report'],
    ],
    'System' => [
        '/admin/settings.php' => ['icon' => 'fa-gear', 'label' => 'Settings'],
    ],
];
?>
<div class="admin-shell">
  <div class="offcanvas offcanvas-start offcanvas-lg admin-sidebar" tabindex="-1" id="adminSidebar">
    <div class="offcanvas-header d-lg-none">
      <div class="brand-logo-box">
        <img src="<?= BASE_URL ?>/assets/img/wellness/logo.jpg" alt="<?= sanitize(SITE_NAME) ?>" class="brand-logo" style="height:30px;">
      </div>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body admin-sidebar-body">
      <a href="<?= BASE_URL ?>/admin/index.php" class="admin-sidebar-brand d-none d-lg-flex">
        <div class="brand-logo-box">
          <img src="<?= BASE_URL ?>/assets/img/wellness/logo.jpg" alt="<?= sanitize(SITE_NAME) ?>" class="brand-logo" style="height:34px;">
        </div>
      </a>
      <nav class="admin-sidebar-nav">
        <?php foreach ($nav_groups as $group_label => $group_items): ?>
          <div class="admin-nav-group-label"><?= sanitize($group_label) ?></div>
          <?php foreach ($group_items as $path => $item): ?>
            <a class="admin-nav-link <?= $current_path === $path ? 'active' : '' ?>" href="<?= BASE_URL . $path ?>">
              <i class="fas <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
              <?php if (!empty($item['badge'])): ?><span class="admin-nav-badge"><?= (int) $item['badge'] ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
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
        <img src="<?= BASE_URL ?>/assets/img/wellness/logo.jpg" alt="<?= sanitize(SITE_NAME) ?>" class="brand-logo" style="height:28px;">
      </div>
    </div>
    <div class="admin-content">