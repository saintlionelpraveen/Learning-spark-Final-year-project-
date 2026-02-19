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

// Function to sanitize filename
function sanitize_filename($filename)
{
    // Remove special characters, keep alphanumeric, dots, dashes and underscores
    $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $filename);
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    return $filename;
}

// Function to compress image
function compress_image($source, $destination, $quality)
{
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg')
        $image = imagecreatefromjpeg($source);
    elseif ($info['mime'] == 'image/gif')
        $image = imagecreatefromgif($source);
    elseif ($info['mime'] == 'image/png')
        $image = imagecreatefrompng($source);
    else
        return false;

    imagejpeg($image, $destination, $quality);
    return true;
}

$staff_folder = "uploads/staff_" . $staff_id; // Use a dedicated uploads folder outside of source code if possible, but for now stick to structure
if (!file_exists($staff_folder)) {
    mkdir($staff_folder, 0777, true);
}

// Validate file type and size
$allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'mp4', 'webm'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_types)) {
    header("Location: staff_dashboard.php?staff=" . urlencode($staff_name) . "&msg=Invalid file type");
    exit();
}

// Size limits (Bytes)
$max_size_doc = 5 * 1024 * 1024; // 5MB
$max_size_video = 40 * 1024 * 1024; // 40MB

if ($type === 'video' && $file['size'] > $max_size_video) {
    header("Location: staff_dashboard.php?staff=" . urlencode($staff_name) . "&msg=Video too large (Max 40MB)");
    exit();
} elseif ($type !== 'video' && $file['size'] > $max_size_doc) {
    header("Location: staff_dashboard.php?staff=" . urlencode($staff_name) . "&msg=File too large (Max 5MB)");
    exit();
}

$clean_filename = sanitize_filename($file['name']);
$target_file = $staff_folder . "/" . time() . "_" . $clean_filename; // Add timestamp to prevent overwrites

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    // Compress if image
    if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
        compress_image($target_file, $target_file, 75); // 75% quality
    }

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