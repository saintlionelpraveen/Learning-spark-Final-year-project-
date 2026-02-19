<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['name'];

include 'config.php';

$content_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing content
$stmt = $conn->prepare("SELECT * FROM staff_content WHERE content_id = ? AND staff_id = ?");
$stmt->bind_param("ii", $content_id, $staff_id);
$stmt->execute();
$content = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$content) {
    echo "<script>alert('Content not found.'); window.location.href='staff_dashboard.php?staff=" . urlencode($staff_name) . "';</script>";
    exit();
}

// Handle update
if (isset($_POST['update_content'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $type = $conn->real_escape_string($_POST['type']);
    $content_text = isset($_POST['content_text']) ? $conn->real_escape_string($_POST['content_text']) : '';

    $file_path = $content['file_path'];
    $image_path = $content['image_path'];

    // Handle new file upload
    if (isset($_FILES['content_file']) && $_FILES['content_file']['error'] == 0) {
        $upload_dir = "uploads/content/$staff_id/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        // Delete old file
        if ($file_path && file_exists($file_path))
            unlink($file_path);
        $file_path = $upload_dir . basename($_FILES['content_file']['name']);
        move_uploaded_file($_FILES['content_file']['tmp_name'], $file_path);
    }

    // Handle new image upload
    if (isset($_FILES['content_image']) && $_FILES['content_image']['error'] == 0) {
        $upload_dir = "uploads/content/$staff_id/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        if ($image_path && file_exists($image_path))
            unlink($image_path);
        $image_path = $upload_dir . basename($_FILES['content_image']['name']);
        move_uploaded_file($_FILES['content_image']['tmp_name'], $image_path);
    }

    $stmt = $conn->prepare("UPDATE staff_content SET title = ?, type = ?, file_path = ?, content_text = ?, image_path = ? WHERE content_id = ? AND staff_id = ?");
    $stmt->bind_param("sssssii", $title, $type, $file_path, $content_text, $image_path, $content_id, $staff_id);
    if ($stmt->execute()) {
        echo "<script>alert('Content updated!'); window.location.href='staff_dashboard.php?staff=" . urlencode($staff_name) . "';</script>";
    } else {
        echo "<script>alert('Error updating content.');</script>";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Content — Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="portal_styles.css">
</head>

<body>
    <!-- Top Navigation -->
    <header class="top-nav">
        <div class="top-nav-inner">
            <a href="staff_dashboard.php?staff=<?php echo urlencode($staff_name); ?>" class="top-nav-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Learning Spark</span>
            </a>
            <nav class="top-nav-links">
                <a href="staff_dashboard.php?staff=<?php echo urlencode($staff_name); ?>"><i
                        class="fas fa-th-large"></i> Dashboard</a>
                <a href="staff_messages.php?staff=<?php echo urlencode($staff_name); ?>"><i class="fas fa-comments"></i>
                    Messages</a>
                <a href="login.php" class="nav-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            <button class="mobile-menu-btn" onclick="document.querySelector('.top-nav-links').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="main-content" style="max-width:640px">
        <div class="page-header">
            <h1><i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i> Update Content</h1>
            <p>Edit your content details and files</p>
        </div>

        <div class="glass-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Title</label>
                    <div class="form-input-icon-wrapper">
                        <i class="fas fa-heading"></i>
                        <input type="text" name="title" class="form-input"
                            value="<?php echo htmlspecialchars($content['title']); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-input" required>
                        <option value="video" <?php echo $content['type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                        <option value="document" <?php echo $content['type'] === 'document' ? 'selected' : ''; ?>>Document
                            (PDF)</option>
                        <option value="blog" <?php echo $content['type'] === 'blog' ? 'selected' : ''; ?>>Blog Post
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Replace File (optional)</label>
                    <input type="file" name="content_file" class="form-input" accept="video/*,.pdf">
                    <?php if ($content['file_path']): ?>
                        <div class="text-xs text-muted mt-8">Current: <?php echo basename($content['file_path']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Replace Image (optional)</label>
                    <input type="file" name="content_image" class="form-input" accept="image/*">
                    <?php if ($content['image_path']): ?>
                        <div class="text-xs text-muted mt-8">Current: <?php echo basename($content['image_path']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Blog Content (if Blog type)</label>
                    <textarea name="content_text" class="form-input"
                        rows="6"><?php echo htmlspecialchars($content['content_text'] ?? ''); ?></textarea>
                </div>
                <div style="display:flex;gap:10px">
                    <button type="submit" name="update_content" class="btn btn-primary btn-lg"
                        style="flex:1;justify-content:center">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="staff_dashboard.php?staff=<?php echo urlencode($staff_name); ?>"
                        class="btn btn-ghost btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>