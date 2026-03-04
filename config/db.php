<?php
// Timezone setting - Việt Nam (GMT+7)
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Session security settings
require_once __DIR__ . '/../includes/security.php';

// Initialize secure session before session_start()
if (session_status() === PHP_SESSION_NONE) {
    initSecureSession();
}


define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'challenge5a');
define('DB_PORT', 3306);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Ket noi that bai.");
}

// Set timezone for MySQL connection
$conn->query("SET time_zone = '+07:00'");
    
?>