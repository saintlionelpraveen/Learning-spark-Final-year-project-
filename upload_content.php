<?php
session_start();
if ($_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['name'];
$title = $_POST['title'];
$type = $_POST['type'];
$file = $_FILES['file'];

// Create staff folder
$staff_folder = "content/" . $staff_name;
if (!file_exists($staff_folder)) {
    mkdir($staff_folder, 0777, true);
}

$target_dir = $staff_folder . "/";
$target_file = $target_dir . basename($file['name']);

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    include 'config.php';

    $stmt = $conn->prepare("INSERT INTO staff_content (staff_id, title, type, file_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $staff_id, $title, $type, $target_file);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: staff_dashboard.php?staff=" . urlencode($staff_name) . "&msg=Content uploaded successfully");
} else {
    header("Location: staff_dashboard.php?staff=" . urlencode($staff_name) . "&msg=Upload failed");
}
?>