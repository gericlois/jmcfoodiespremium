<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
$module_name = $module_name ?? SITE_NAME;
$title_suffix = $module_name === SITE_NAME ? SITE_NAME : sanitize($module_name) . ' - ' . SITE_NAME;

// PWA install target: each module is installable as its own app (own icon,
// name, and start_url), sharing one PHP backend/session underneath. Pages
// with no module set (hub, login, change_password) fall back to the JMC
// Digital manifest/icon set.
if ($module_name === 'JMC Foodies Wellness') {
    $pwa_manifest_url = WELLNESS_URL . '/manifest.php';
    $pwa_icon_dir = BASE_URL . '/assets/img/wellness/icons';
    $pwa_theme_color = '#14532d';
} elseif ($module_name === 'JMC Foodies Basics') {
    $pwa_manifest_url = BASICS_URL . '/manifest.php';
    $pwa_icon_dir = BASE_URL . '/assets/img/basics/icons';
    $pwa_theme_color = '#e8720c';
} else {
    $pwa_manifest_url = BASE_URL . '/manifest.php';
    $pwa_icon_dir = BASE_URL . '/assets/img/icons';
    $pwa_theme_color = '#14532d';
}
?>
<title><?= isset($page_title) ? sanitize($page_title) . ' - ' . $title_suffix : $title_suffix ?></title>
<link rel="manifest" href="<?= $pwa_manifest_url ?>">
<meta name="theme-color" content="<?= $pwa_theme_color ?>">
<link rel="apple-touch-icon" href="<?= $pwa_icon_dir ?>/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= sanitize($module_name === SITE_NAME ? SITE_NAME : $module_name) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;500;600;700&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/theme.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
/* Page-header banner (.inner-hero) uses the same photo as the homepage
   hero, instead of a flat color. A light scrim keeps the existing dark
   heading text legible over the photo. */
.inner-hero {
  background-image: linear-gradient(rgba(255, 255, 255, 0.88), rgba(255, 255, 255, 0.88)), url('<?= BASE_URL ?>/assets/img/wellness/head_bg.jpg');
  background-size: cover;
  background-position: center;
}
<?php if (!empty($module_primary_color) || !empty($module_secondary_color)): ?>
/* Per-module brand palette override — every shared component (buttons,
   badges, active nav states, etc.) already reads --primary/--secondary, so
   re-declaring them here re-themes the whole page for this module without
   duplicating any component CSS. */
:root {
  <?php if (!empty($module_primary_color)): ?>--primary: <?= sanitize($module_primary_color) ?>;<?php endif; ?>
  <?php if (!empty($module_secondary_color)): ?>--secondary: <?= sanitize($module_secondary_color) ?>;<?php endif; ?>
}
<?php endif; ?>
<?php if (!empty($module_header_dark)): ?>
/* Page-header banner (.inner-hero) uses the same dark green as the footer,
   instead of the default photo background — swaps the photo for a flat
   var(--dark) fill and flips the heading text (normally dark, meant for the
   light photo scrim) to light colors so it stays legible. */
.inner-hero {
  background-image: none !important;
  background-color: var(--dark);
}
.inner-hero .stitle {
  color: #fff;
}
.inner-hero .stitle span {
  color: #fff;
}
.inner-hero .slbl {
  color: #fff;
  /* Was the script "Your Overview" cursive — unified to the same font as
     the rest of the banner (still its own smaller size, just not a
     different typeface). */
  font-family: "Poppins", sans-serif;
  font-weight: 600;
}
<?php endif; ?>
<?php if (!empty($module_squared_ui)): ?>
/* Basics gets its own interface personality, not just a recolor: bold
   sans-serif headings (vs Wellness's elegant serif) and squared-off,
   left-accented components (vs Wellness's soft, fully-rounded "premium"
   cards/buttons) — a more utilitarian, dashboard-like feel to match a
   grocery credit-line tool instead of a wellness storefront. */
h1, h2, h3, h4, h5,
.htitle, .stitle {
  font-family: "Poppins", sans-serif !important;
  font-weight: 800 !important;
}
.panel-card {
  border-radius: 10px;
}
.btn-red {
  border-radius: 8px;
}
.table-theme {
  border-radius: 10px;
}
.stat-tile {
  border-radius: 8px;
  border-left: 4px solid var(--primary);
  text-align: left;
  padding-left: 20px;
}
.stat-tile .stat-num,
.stat-tile .stat-lbl {
  text-align: left;
}
.catalog-card {
  border-left: 4px solid var(--primary);
}
<?php endif; ?>
</style>
</head>
<body>
