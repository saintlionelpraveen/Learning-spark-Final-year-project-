<?php
// Production-ready database configuration
// Update these settings when deploying to a live server (e.g., InfinityFree)

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'learning';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // In production, you might want to log this error instead of showing it to the user
    die("Connection failed: " . $conn->connect_error);
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