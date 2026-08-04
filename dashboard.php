<?php
// Legacy path — moved to /wellness/dashboard.php.
require __DIR__ . '/config/constants.php';
header('Location: ' . WELLNESS_URL . '/dashboard.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
