<?php
session_start();
if ($_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['name'];
$message = $_POST['message'];
$admin_id = 1; // Hardcoded as Admin (id=1) from your dump

include 'config.php';

$stmt = $conn->prepare("INSERT INTO notifications (staff_id, admin_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $staff_id, $admin_id, $message);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: staff_dashboard.php?staff=" . urlencode($staff_name) . "&msg=Notification sent successfully");
?>