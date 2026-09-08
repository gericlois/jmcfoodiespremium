<?php
// Legacy path — moved to /wellness/orders.php.
require __DIR__ . '/config/constants.php';
header('Location: ' . WELLNESS_URL . '/orders.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
