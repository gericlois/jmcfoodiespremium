<?php
require __DIR__ . '/config/constants.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

// Logged-in visitors skip the chooser entirely if they only have one module.
// route_after_login() returns '/index.php' itself for dual-access users —
// redirecting there would just bounce back here again, so only redirect
// when the target is actually somewhere else.
if (is_logged_in()) {
    $target = route_after_login($conn, current_user_id());
    if ($target !== '/index.php') {
        redirect($target);
    }
}

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<section id="hero">
  <div class="container">
    <div class="row align-items-center g-5" style="min-height:60vh;">
      <div class="col-lg-8 mx-auto text-center">
        <div class="hbadge mx-auto">
          <div class="hbi"><i class="fas fa-star"></i></div>
          <span><?= sanitize(SITE_NAME) ?></span>
        </div>
        <h1 class="htitle">One Login,<br/>Two Ways to <span class="hl">Save &amp; Earn</span></h1>
        <p class="hdesc mx-auto">
          <?= sanitize(SITE_NAME) ?> brings together JMC Foodies Wellness and JMC Foodies Basics —
          choose where you want to go, or log in once to reach whichever you're a member of.
        </p>
      </div>
    </div>
  </div>
</section>

<div class="shop-bg py-5">
  <div class="container py-4">
    <div class="row g-4 justify-content-center">
      <div class="col-12 col-md-6 col-lg-5">
        <div class="panel-card h-100 d-flex flex-column text-center" style="border-top:6px solid #14532d;">
          <div class="brand-logo-box mx-auto mb-3">
            <img src="<?= BASE_URL ?>/assets/img/wellness/logo.jpg" alt="JMC Foodies Wellness" class="brand-logo" style="height:52px;">
          </div>
          <h2 class="h4 mb-2">JMC Foodies Wellness</h2>
          <p class="text-muted mb-4">Earn a personal rebate on your own purchases, plus a referral override on every purchase your friends make. Simple, transparent, one level deep.</p>
          <div class="mt-auto">
            <a href="<?= WELLNESS_URL ?>/index.php" class="btn-red justify-content-center w-100"><i class="fas fa-leaf"></i>Enter JMC Foodies Wellness</a>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-5">
        <div class="panel-card h-100 d-flex flex-column text-center" style="border-top:6px solid #e8720c;">
          <div class="brand-logo-box mx-auto mb-3">
            <?php if (is_file(__DIR__ . '/assets/img/basics/logo.jpg')): ?>
              <img src="<?= BASE_URL ?>/assets/img/basics/logo.jpg" alt="JMC Foodies Basics" class="brand-logo" style="height:52px;">
            <?php else: ?>
              <span class="fw-bold" style="color:#e8720c;font-size:1.1rem;">JMC<span style="color:#14532d;"> FOODIES</span> BASICS</span>
            <?php endif; ?>
          </div>
          <h2 class="h4 mb-2">JMC Foodies Basics</h2>
          <p class="text-muted mb-4">A weekly grocery credit line for employees of partner companies &mdash; order your basic needs now, settle up on payday. Basic needs, everyday, for every family.</p>
          <div class="mt-auto">
            <a href="<?= BASICS_URL ?>/index.php" class="w-100 d-inline-flex align-items-center justify-content-center gap-2" style="background:#e8720c;color:#fff;border:none;border-radius:50px;padding:14px 32px;font-weight:600;font-size:0.93rem;font-family:'Poppins',sans-serif;text-decoration:none;"><i class="fas fa-basket-shopping"></i>Enter JMC Foodies Basics</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
