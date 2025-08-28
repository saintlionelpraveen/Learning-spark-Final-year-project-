<?php
session_start();
if ($_SESSION['role'] !== 'staff') die("Access denied");

$conn = new mysqli("127.0.0.1", "root", "", "learning");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$content_id = intval($_GET['id']);
$staff_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM staff_content WHERE content_id = ? AND staff_id = ?");
$stmt->bind_param("ii", $content_id, $staff_id);
$stmt->execute();
$content = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $file_path = $content['file_path'];

    if (isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
        $target_dir = "content/" . $staff_id . "/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_path = $target_dir . basename($_FILES["file"]["name"]);
        move_uploaded_file($_FILES["file"]["tmp_name"], $file_path);
    }

    $stmt = $conn->prepare("UPDATE staff_content SET title = ?, file_path = ? WHERE content_id = ? AND staff_id = ?");
    $stmt->bind_param("ssii", $title, $file_path, $content_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Location: staff_dashboard.php?staff=" . urlencode($_SESSION['name']));
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Content</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f7f9fc; font-family: 'Inter', Arial, sans-serif; padding: 40px; }
        .card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); }
        .card h3 { font-size: 22px; font-weight: 600; color: #3b82f6; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 8px; opacity: 0.8; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid rgba(0, 0, 0, 0.1); border-radius: 8px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 8px rgba(59, 130, 246, 0.3); }
        .btn { background: #3b82f6; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <h3>Update Content</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($content['title']); ?>" required>
            </div>
            <div class="form-group">
                <label>New File (optional)</label>
                <input type="file" name="file">
                <small>Current: <?php echo htmlspecialchars($content['file_path']); ?></small>
            </div>
            <button type="submit" class="btn">Update</button>
        </form>
    </div>
</body>
</html>