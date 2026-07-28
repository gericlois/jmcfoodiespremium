<?php
/*
 * DATABASE CONFIGURATION
 * =============================================
 * Auto-detects environment:
 *   - Accessed via localhost / 127.0.0.1  -> local XAMPP database
 *   - Anywhere else (deployed)            -> live database (fill in before deploy)
 */

$host_header = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($host_header, 'localhost') !== false)
         || (strpos($host_header, '127.0.0.1') !== false);

if ($is_local) {
    // Local XAMPP
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'referral_platform';
} else {
    // Live (fill in with real host/credentials before deploying)
    $db_host = 'localhost';
    $db_user = '';
    $db_pass = '';
    $db_name = '';
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");
