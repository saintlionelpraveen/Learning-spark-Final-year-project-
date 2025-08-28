<?php
session_start();
if ($_SESSION['role'] !== 'staff') die("Access denied");

$staff_id = $_SESSION['user_id'];
$conn = new mysqli("127.0.0.1", "root", "", "learning");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$message = $conn->real_escape_string($_POST['message']);
$admin_id = 1; // Assuming admin ID 1; adjust as needed

$stmt = $conn->prepare("INSERT INTO staff_to_admin_messages (staff_id, admin_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $staff_id, $admin_id, $message);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: staff_dashboard.php?staff=" . urlencode($_SESSION['name']));
exit();
?>