<?php
date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'JMC Foodies Wellness');
define('WALLET_NAME', 'JMC Wallet');

/*
 * BASE_URL CONFIGURATION
 * ======================
 * Auto-detects environment:
 *   - Local (localhost / 127.0.0.1) -> '/jmcfoodies' (app runs in a subfolder)
 *   - Live  (deployed at domain root) -> '' (empty string)
 */
$host_header = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($host_header, 'localhost') !== false)
         || (strpos($host_header, '127.0.0.1') !== false);
define('BASE_URL', $is_local ? '/jmcfoodies' : '');

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Personal rebate rate, referral override rate, minimum cashout amount, and
// company email are now editable at runtime — see the `settings` table and
// the setting() helper in includes/functions.php, managed via
// admin/settings.php. Not hardcoded here anymore.
