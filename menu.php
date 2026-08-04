<?php
// Legacy path — moved to /wellness/menu.php.
require __DIR__ . '/config/constants.php';
header('Location: ' . WELLNESS_URL . '/menu.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
