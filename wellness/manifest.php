<?php
// PWA manifest for the JMC Foodies Wellness module. See manifest.php (hub)
// for why this is a PHP script instead of a static .json file. `scope` is
// the whole site (not just /wellness/) so shared pages like /login.php
// still stay inside the installed app window instead of breaking out to a
// normal browser tab.
require __DIR__ . '/../config/constants.php';
header('Content-Type: application/manifest+json');
echo json_encode([
    'name' => 'JMC Foodies Wellness',
    'short_name' => 'JMC Wellness',
    'description' => 'Earn a personal rebate on your own purchases and a referral override on every purchase your friends make.',
    'start_url' => WELLNESS_URL . '/index.php',
    'scope' => BASE_URL . '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#14532d',
    'icons' => [
        ['src' => BASE_URL . '/assets/img/wellness/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/wellness/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/wellness/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
