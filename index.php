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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.php">
<meta name="theme-color" content="#14532d">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/icons/icon-192.png">
<style>
  body {
    font-family: "Poppins", sans-serif;
    background: #f0f2f5;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .chooser-card { width: 100%; max-width: 400px; }
  .chooser-card .card {
    border: none;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,.08);
  }
  .chooser-header {
    background: linear-gradient(195deg, #1a6b3c, #0d2818);
    padding: 1.75rem 1.5rem 1.25rem;
    text-align: center;
    color: #fff;
    border-bottom: 4px solid #d9a521;
  }
  .chooser-header .chooser-brand {
    font-family: "Playfair Display", serif;
    font-weight: 900;
    font-size: 3rem;
    line-height: 1.1;
    color: #fff;
    margin: 0;
  }
  .chooser-header p { margin: 8px 0 0; font-size: 13px; color: rgba(255,255,255,.9); }
  .portal-btn {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 14px 18px;
    border-radius: 0.5rem;
    border: 2px solid #e9ecef;
    background: #fff;
    text-decoration: none;
    transition: 0.2s;
  }
  .portal-btn:hover { transform: translateY(-1px); }
  .portal-btn.portal-wellness:hover { border-color: #14532d; background: #f7faf8; }
  .portal-btn.portal-basics:hover { border-color: #e8720c; background: #fef8f3; }
  .portal-btn .portal-icon {
    width: 44px; height: 44px; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid #eee;
    overflow: hidden;
  }
  .portal-btn .portal-icon img { width: 100%; height: 100%; object-fit: contain; }
  .portal-btn .portal-text h6 { margin: 0; color: #212529; font-weight: 600; font-size: 0.95rem; }
  .portal-btn .portal-text p { margin: 0; font-size: 12px; color: #6c757d; }
</style>
</head>
<body>
  <div class="chooser-card">
    <div class="card">
      <div class="chooser-header">
        <h1 class="chooser-brand">JMC<br>Digital</h1>
        <p>One login, two ways to save &amp; earn</p>
      </div>
      <div class="card-body p-4">
        <p class="text-center small text-muted mb-3">Choose where you'd like to login / sign in</p>
        <div class="d-flex flex-column gap-3">
          <a href="<?= WELLNESS_URL ?>/index.php" class="portal-btn portal-wellness">
            <div class="portal-icon">
              <img src="<?= BASE_URL ?>/assets/img/wellness/logo.jpg" alt="JMC Wellness">
            </div>
            <div class="portal-text">
              <h6>JMC Wellness</h6>
              <p>Personal rebate &amp; referral rewards</p>
            </div>
          </a>
          <a href="<?= BASICS_URL ?>/index.php" class="portal-btn portal-basics">
            <div class="portal-icon">
              <img src="<?= BASE_URL ?>/assets/img/basics/logo.jpg" alt="JMC Basics">
            </div>
            <div class="portal-text">
              <h6>JMC Basics</h6>
              <p>Weekly grocery credit line</p>
            </div>
          </a>
        </div>
      </div>
    </div>
    <p class="text-center small text-muted mt-3 mb-0">Already a member of either? Sign in from the portal above.</p>
  </div>
</body>
</html>
