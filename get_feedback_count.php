<?php
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

$conn = new mysqli("127.0.0.1", "root", "", "learning");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as feedback_count FROM staff_feedback WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode($data);
?>