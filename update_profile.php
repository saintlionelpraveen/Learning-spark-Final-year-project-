<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    die("Access denied");
}

$staff_id = $_SESSION['user_id'];
$conn = new mysqli("127.0.0.1", "root", "", "learning");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$name = $conn->real_escape_string($_POST['name']);
$email = $conn->real_escape_string($_POST['email']);
$profile_photo = null;

if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['size'] > 0) {
    $target_dir = "profile_photos/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $profile_photo = $target_dir . $staff_id . "_" . basename($_FILES["profile_photo"]["name"]);
    move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $profile_photo);
}

$stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, profile_photo = COALESCE(?, profile_photo) WHERE id = ?");
$stmt->bind_param("sssi", $name, $email, $profile_photo, $staff_id);
$stmt->execute();
$stmt->close();
$conn->close();

// Update session name
$_SESSION['name'] = $name;

header("Location: staff_dashboard.php?staff=" . urlencode($name));
exit();
?>