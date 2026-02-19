<?php
// Production-ready database configuration
// Update these settings when deploying to a live server (e.g., InfinityFree)

$db_host = 'sql203.infinityfree.com';
$db_user = 'if0_41193976';
$db_pass = 'tebi1328';
$db_name = 'if0_41193976_learning';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable mysqli exception handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4"); // Good practice to set charset
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to safely close connection (optional usage)
function close_db_connection()
{
    global $conn;
    if ($conn) {
        $conn->close();
    }
}
?>