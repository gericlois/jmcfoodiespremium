<?php
date_default_timezone_set('Asia/Manila');

// JMC Digital is the umbrella brand for two separate systems (JMC Foodies
// Wellness and JMC Foodies Basics) that share one login. Each module sets
// its own $module_name/$module_logo_url (see wellness/includes/module.php
// and basics/includes/module.php) — SITE_NAME is only for the umbrella-level
// bits (browser tab suffix, footer copyright, admin shell).
define('SITE_NAME', 'JMC Digital');

/*
 * BASE_URL CONFIGURATION
 * ======================
 * Auto-detects environment:
 *   - Local (localhost / 127.0.0.1) -> '/jmcfoodiespremium' (app runs in a subfolder)
 *   - Live  (deployed at domain root) -> '' (empty string)
 */
$host_header = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($host_header, 'localhost') !== false)
         || (strpos($host_header, '127.0.0.1') !== false);
define('BASE_URL', $is_local ? '/jmcfoodiespremium' : '');

define('WELLNESS_URL', BASE_URL . '/wellness');
define('BASICS_URL', BASE_URL . '/basics');

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Personal rebate rate, referral override rate, minimum cashout amount,
// Basics penalty tiers, and company email are all editable at runtime — see
// the `settings` table and the setting() helper in includes/functions.php,
// managed via admin/settings.php. Not hardcoded here.
