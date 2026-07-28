  <footer>
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-4">
          <div class="brand-logo-box mb-3">
            <img src="<?= BASE_URL ?>/assets/img/logo.jpg" alt="<?= sanitize(APP_NAME) ?>" class="brand-logo">
          </div>
          <p class="fdesc">Earn a personal rebate on your own purchases and an override on every purchase your direct referrals make. Simple, transparent, one level deep.</p>
        </div>
        <div class="col-sm-6 col-lg-4">
          <div class="ftit">Quick Links</div>
          <ul class="flinks ps-0">
            <li><a href="<?= BASE_URL ?>/index.php"><i class="fas fa-chevron-right"></i>Home</a></li>
            <li><a href="<?= BASE_URL ?>/menu.php"><i class="fas fa-chevron-right"></i>Shop</a></li>
            <?php if (is_logged_in()): ?>
              <li><a href="<?= BASE_URL ?>/dashboard.php"><i class="fas fa-chevron-right"></i>Dashboard</a></li>
              <li><a href="<?= BASE_URL ?>/wallet.php"><i class="fas fa-chevron-right"></i><?= sanitize(WALLET_NAME) ?></a></li>
            <?php else: ?>
              <li><a href="<?= BASE_URL ?>/register.php"><i class="fas fa-chevron-right"></i>Register</a></li>
              <li><a href="<?= BASE_URL ?>/login.php"><i class="fas fa-chevron-right"></i>Login</a></li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="col-lg-4">
          <div class="ftit">Get In Touch</div>
          <div class="fci">
            <div class="fciico"><i class="fas fa-envelope"></i></div>
            <div class="fciinfo"><strong>Email</strong><?= sanitize(setting($conn, 'company_email', 'support@example.com')) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="fbot">
      <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <p>&copy; <?= date('Y') ?> <span><?= APP_NAME ?></span>. All rights reserved.</p>
        </div>
      </div>
    </div>
  </footer>
  <button id="btt" onclick="window.scrollTo({top:0,behavior:'smooth'})"><i class="fas fa-chevron-up"></i></button>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
