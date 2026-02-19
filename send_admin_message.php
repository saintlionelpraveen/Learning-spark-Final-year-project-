<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff')
    die("Access denied");

$staff_id = $_SESSION['user_id'];
include 'config.php';

$message = $conn->real_escape_string($_POST['message']);

// Find the admin user dynamically
$admin_result = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($admin_result->num_rows === 0)
    die("No admin found.");
$admin_id = $admin_result->fetch_assoc()['id'];

$stmt = $conn->prepare("INSERT INTO staff_to_admin_messages (staff_id, admin_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $staff_id, $admin_id, $message);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: staff_dashboard.php?staff=" . urlencode($_SESSION['name']));
exit();
?>