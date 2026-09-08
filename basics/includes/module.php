<?php
// Sets the current module's identity for the shared header/navbar/footer.
// Required by every page under basics/ before requiring the shared
// includes/header.php. See wellness/includes/module.php for the parallel.
$module_name = 'JMC Foodies Basics';
$module_logo_url = is_file(__DIR__ . '/../../assets/img/basics/logo.jpg')
    ? BASE_URL . '/assets/img/basics/logo.jpg'
    : BASE_URL . '/assets/img/wellness/logo.jpg'; // falls back to the site logo until a Basics logo file is provided
$module_home_url = BASICS_URL . '/index.php';
$module_register_url = BASICS_URL . '/apply.php';
$module_footer_desc = 'A weekly grocery credit line for employees of partner companies. Basic needs, everyday, for every family.';
// Orange + green — re-themes every shared button/badge/card component for
// Basics pages via the CSS variable override in includes/header.php.
$module_primary_color = '#e8720c';
$module_secondary_color = '#2e7d32';
// Header (topbar + nav) uses the same dark green as the footer instead of
// theme.css's default white nav.
$module_header_dark = true;
// Bold sans-serif headings + squared, left-accented components instead of
// Wellness's elegant-serif, fully-rounded look — see includes/header.php.
$module_squared_ui = true;

$module_nav_items = [
    'Dashboard'         => BASICS_URL . '/dashboard.php',
    'Catalog'           => BASICS_URL . '/catalog.php',
    'My Orders'         => BASICS_URL . '/orders.php',
    'Payments'          => BASICS_URL . '/payments.php',
    'Emergency Credit'  => BASICS_URL . '/emergency_credit.php',
    'Benefits'          => BASICS_URL . '/benefits.php',
];
$module_guest_nav_items = [
    'Home' => BASICS_URL . '/index.php',
];
