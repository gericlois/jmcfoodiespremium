<div id="topbar">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="top-contact d-flex flex-wrap">
        <span><i class="fas fa-envelope"></i><?= sanitize(setting($conn, 'company_email', 'support@example.com')) ?></span>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="ttag"><i class="fas fa-percent me-1"></i><?= (int) ((float) setting($conn, 'personal_rebate_rate', 0.20) * 100) ?>% Personal Rebate</span>
      </div>
    </div>
  </div>
</div>

<nav class="navbar navbar-expand-lg" id="nav">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
      <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="<?= sanitize(APP_NAME) ?>" class="brand-logo">
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
      <i class="fas fa-bars" style="color:var(--primary);font-size:1.35rem;"></i>
    </button>
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto">
        <?php if (is_logged_in()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/menu.php">Shop</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/orders.php">My Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/wallet.php"><?= sanitize(WALLET_NAME) ?></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/menu.php">Shop</a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-2 ms-3">
        <?php if (is_logged_in()): ?>
          <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-cta"><i class="fas fa-right-from-bracket me-1"></i>Logout</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/login.php" class="nav-link">Login</a>
          <a href="<?= BASE_URL ?>/register.php" class="nav-link nav-cta"><i class="fas fa-user-plus me-1"></i>Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
