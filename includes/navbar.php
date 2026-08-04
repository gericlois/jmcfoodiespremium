<?php
// Module-data-driven: each page sets $module_name/$module_logo_url/
// $module_home_url/$module_nav_items/$module_guest_nav_items/$module_register_url
// before requiring this (see wellness/includes/module.php,
// basics/includes/module.php) — unset on neutral pages (hub, login), which
// fall back to the JMC Digital umbrella defaults below.
$module_name = $module_name ?? SITE_NAME;
$module_logo_url = $module_logo_url ?? BASE_URL . '/assets/img/wellness/logo.jpg';
$module_home_url = $module_home_url ?? BASE_URL . '/index.php';
$module_nav_items = $module_nav_items ?? [];
$module_guest_nav_items = $module_guest_nav_items ?? [];
$module_register_url = $module_register_url ?? null;
?>
<div id="topbar">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="top-contact d-flex flex-wrap">
        <span><i class="fas fa-envelope"></i><?= sanitize(setting($conn, 'company_email', 'support@example.com')) ?></span>
      </div>
      <?php if ($module_name === 'JMC Foodies Wellness'): ?>
        <div class="d-flex align-items-center gap-3">
          <span class="ttag"><i class="fas fa-percent me-1"></i><?= (int) ((float) setting($conn, 'personal_rebate_rate', 0.20) * 100) ?>% Personal Rebate</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<nav class="navbar navbar-expand-lg" id="nav">
  <div class="container">
    <a class="navbar-brand" href="<?= sanitize($module_home_url) ?>">
      <img src="<?= sanitize($module_logo_url) ?>" alt="<?= sanitize($module_name) ?>" class="brand-logo">
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <i class="fas fa-bars" style="color:var(--primary);font-size:1.35rem;"></i>
    </button>
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto">
        <?php if (is_logged_in()): ?>
          <?php foreach ($module_nav_items as $label => $url): ?>
            <li class="nav-item"><a class="nav-link" href="<?= sanitize($url) ?>"><?= sanitize($label) ?></a></li>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach ($module_guest_nav_items as $label => $url): ?>
            <li class="nav-item"><a class="nav-link" href="<?= sanitize($url) ?>"><?= sanitize($label) ?></a></li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-2 ms-3">
        <?php if (is_logged_in()): ?>
          <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-cta"><i class="fas fa-right-from-bracket me-1"></i>Logout</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/login.php" class="nav-link">Login</a>
          <?php if ($module_register_url): ?>
            <a href="<?= sanitize($module_register_url) ?>" class="nav-link nav-cta"><i class="fas fa-user-plus me-1"></i>Register</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
