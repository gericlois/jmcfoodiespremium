<?php
// Legacy path — Wellness registration moved to /wellness/register.php.
// Kept here (301, query string preserved) so already-shared referral links
// like register.php?ref=CODE keep working.
require __DIR__ . '/config/constants.php';
header('Location: ' . WELLNESS_URL . '/register.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
