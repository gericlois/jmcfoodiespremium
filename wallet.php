<?php
// Legacy path — moved to /wellness/wallet.php.
require __DIR__ . '/config/constants.php';
header('Location: ' . WELLNESS_URL . '/wallet.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
