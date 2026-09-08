<?php
// PWA manifest for the JMC Digital hub. Served as JSON (not a static
// manifest.json) because BASE_URL is only known at request time — same
// reason UPLOAD_URL etc. are built dynamically elsewhere in this app.
require __DIR__ . '/config/constants.php';
header('Content-Type: application/manifest+json');
echo json_encode([
    'name' => 'JMC Digital',
    'short_name' => 'JMC Digital',
    'description' => 'JMC Foodies Wellness and JMC Foodies Basics — two ways to save, earn, and shop with JMC Foodies.',
    'start_url' => BASE_URL . '/index.php',
    'scope' => BASE_URL . '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#14532d',
    'icons' => [
        ['src' => BASE_URL . '/assets/img/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
