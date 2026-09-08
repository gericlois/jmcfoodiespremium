<?php
// Sets the current module's identity for the shared header/navbar/footer.
// Required by every page under wellness/ before requiring the shared
// includes/header.php.
define('WALLET_NAME', 'JMC Wallet');

$module_name = 'JMC Foodies Wellness';
$module_logo_url = BASE_URL . '/assets/img/wellness/logo.jpg';
$module_home_url = WELLNESS_URL . '/dashboard.php';
$module_nav_items = [
    'Dashboard' => WELLNESS_URL . '/dashboard.php',
    'Shop'      => WELLNESS_URL . '/menu.php',
    'My Orders' => WELLNESS_URL . '/orders.php',
    WALLET_NAME => WELLNESS_URL . '/wallet.php',
];
$module_guest_nav_items = [
    'Home' => WELLNESS_URL . '/index.php',
    'Shop' => WELLNESS_URL . '/menu.php',
];
$module_register_url = WELLNESS_URL . '/register.php';
$module_footer_desc = 'Earn a purchase rebate on your own purchases and a purchase over-ride on every purchase your friend/s make. Simple, transparent, one level deep.';
