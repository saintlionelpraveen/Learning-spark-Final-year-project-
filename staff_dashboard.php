<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['name'];

$requested_staff = isset($_GET['staff']) ? $_GET['staff'] : $staff_name;
if ($requested_staff !== $staff_name) {
    die("Access denied: You can only view your own dashboard!");
}

$conn = new mysqli("127.0.0.1", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch staff stats
$stmt = $conn->prepare("SELECT * FROM staff_stats WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Count unread user messages for sidebar
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_to_staff_messages WHERE staff_id = ? AND is_read = 0");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();

// Fetch uploaded content
$stmt = $conn->prepare("SELECT * FROM staff_content WHERE staff_id = ? ORDER BY upload_date DESC");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$content_result = $stmt->get_result();

// Fetch profile details
$stmt = $conn->prepare("SELECT name, email, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch feedback count for real-time stats
$stmt = $conn->prepare("SELECT COUNT(*) as feedback_count FROM staff_feedback WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$feedback_data = $stmt->get_result()->fetch_assoc();
$feedback_count = $feedback_data['feedback_count'] ?? 0;
$stmt->close();

// Fetch feedback (for display)
$feedback_result = null;
$stmt = $conn->prepare("SHOW TABLES LIKE 'staff_feedback'");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt = $conn->prepare("SELECT u.name, sf.feedback_text, sf.created_at FROM staff_feedback sf JOIN users u ON sf.user_id = u.id WHERE sf.staff_id = ? ORDER BY sf.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $feedback_result = $stmt->get_result();
}
$stmt->close();

// Handle profile update (including cropped photo)
if (isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $profile_photo = $profile['profile_photo'];

    if (!empty($_POST['cropped_image'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . "staff_" . $staff_id . "_" . time() . ".png";
        $image_data = $_POST['cropped_image'];
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);
        if (file_put_contents($target_file, $decoded_image) !== false) {
            $profile_photo = $target_file;
        } else {
            $error = "Error saving cropped photo.";
        }
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, profile_photo = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $profile_photo, $staff_id);
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        $profile['name'] = $name;
        $profile['email'] = $email;
        $profile['profile_photo'] = $profile_photo;
        $success = "Profile updated successfully!";
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

// Handle content deletion
if (isset($_POST['delete_content'])) {
    $content_id = intval($_POST['content_id']);
    $stmt = $conn->prepare("DELETE FROM staff_content WHERE content_id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $content_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

// Handle blog post submission
if (isset($_POST['post_blog'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content_text = $conn->real_escape_string($_POST['content']);
    $image_path = null;

    if (isset($_FILES['blog_image']) && $_FILES['blog_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $image_name = time() . '_' . basename($_FILES['blog_image']['name']);
        $image_path = $upload_dir . $image_name;
        move_uploaded_file($_FILES['blog_image']['tmp_name'], $image_path);
    }

    $stmt = $conn->prepare("INSERT INTO staff_content (staff_id, title, type, content_text, image_path) VALUES (?, ?, 'blog', ?, ?)");
    $stmt->bind_param("isss", $staff_id, $title, $content_text, $image_path);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

// Simulated real-time stats (user access count and feedback count)
$user_access_count = rand(50, 200);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff_name); ?>'s Dashboard - Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
            --background: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --shadow: rgba(0, 0, 0, 0.1);
            --accent: #f59e0b;
            --gradient: linear-gradient(45deg, #4f46e5, #7c3aed);
            --error: #ef4444;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
            display: flex;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 70px;
            height: 100vh;
            background: var(--card-bg);
            box-shadow: 2px 0 15px var(--shadow);
            transition: width 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }

        .sidebar:hover {
            width: 250px;
        }

        .sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar .logo i {
            font-size: 1.8em;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .sidebar:hover .logo i {
            transform: rotate(360deg);
        }

        .sidebar .logo span {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--text);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .logo span {
            opacity: 1;
        }

        .sidebar .user-photo {
            padding: 15px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .sidebar .user-photo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .sidebar:hover .user-photo img {
            width: 60px;
            height: 60px;
            transform: scale(1.1);
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar a i {
            font-size: 1.3em;
            min-width: 30px;
        }

        .sidebar a span {
            opacity: 0;
            margin-left: 15px;
            font-weight: 500;
            transition: opacity 0.2s ease;
        }

        .sidebar:hover a span {
            opacity: 1;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--primary);
            color: white;
        }

        .sidebar a:hover i,
        .sidebar a.active i {
            color: white;
        }

        .notification-bell::after {
            content: '<?php echo $unread_count; ?>';
            position: absolute;
            top: 5px;
            right: 15px;
            background: var(--accent);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: <?php echo $unread_count > 0 ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }

        .main-content {
            margin-left: 70px;
            padding: 40px;
            flex: 1;
            transition: margin-left 0.3s ease;
        }

        .sidebar:hover ~ .main-content {
            margin-left: 250px;
        }

        .dashboard-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .dashboard-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .dashboard-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
        }

        .dashboard-header .title-icon {
            position: relative;
            font-size: 1.5em;
            color: var(--primary);
        }

        .dashboard-header .title-icon .fa-book {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1em;
            color: var(--secondary);
        }

        .dashboard-section {
            margin-bottom: 40px;
        }

        .dashboard-section h3 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 1em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--gradient);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .content-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary);
        }

        .content-item button,
        .content-item a {
            margin-left: 10px;
            padding: 8px 15px;
            font-size: 14px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
        }

        .content-item .btn-delete {
            background: var(--error);
        }

        .content-item .btn-delete:hover {
            background: #dc2626;
        }

        .content-item .btn-view {
            background: var(--success);
        }

        .content-item .btn-view:hover {
            background: #059669;
        }

        .content-item .btn-update {
            background: var(--accent);
        }

        .content-item .btn-update:hover {
            background: #d97706;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            text-align: center;
            border-left: 4px solid var(--primary);
        }

        .stat-item i {
            font-size: 1.5em;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-item p {
            font-size: 1em;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-item span {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary);
        }

        .feedback-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }

        .feedback-item p {
            margin: 5px 0;
            color: var(--text);
        }

        .feedback-item small {
            color: var(--text-light);
            font-size: 0.9em;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100;
        }

        .modal-content {
            background: var(--card-bg);
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: var(--shadow);
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }

        .crop-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .crop-modal-content {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px var(--shadow);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .crop-modal-content img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }

        .crop-modal-content .btn {
            width: auto;
            padding: 10px 20px;
            margin: 0 10px;
            display: inline-block;
        }

        .crop-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .sidebar:hover {
                width: 200px;
            }

            .main-content {
                margin-left: 60px;
                padding: 20px;
            }

            .sidebar:hover ~ .main-content {
                margin-left: 200px;
            }

            .dashboard-card {
                padding: 20px;
            }

            .content-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .crop-modal-content {
                max-width: 90%;
                max-height: 70vh;
            }

            .crop-modal-content img {
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-fire"></i>
            <span>LEARNING SPARK</span>
        </div>
        <div class="user-photo">
            <?php if ($profile['profile_photo']): ?>
                <img src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile Photo">
            <?php else: ?>
                <i class="fas fa-user-circle" style="font-size: 40px; color: var(--primary);"></i>
            <?php endif; ?>
        </div>
        <a href="#profile" class="active"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="#upload-content"><i class="fas fa-upload"></i><span>Upload Content</span></a>
        <a href="#content-preview"><i class="fas fa-eye"></i><span>Content Preview</span></a>
        <a href="#post-blog"><i class="fas fa-blog"></i><span>Post Blog</span></a>
        <a href="staff_messages.php" class="notification-bell"><i class="fas fa-bell"></i><span>Messages</span></a>
        <a href="#stats"><i class="fas fa-chart-bar"></i><span>View Stats</span></a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>

            <!-- Profile Section -->
            <div id="profile" class="dashboard-section">
                <h3>Manage Profile</h3>
                <?php if (isset($error)): ?>
                    <div style="color: var(--error); text-align: center;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div style="color: var(--success); text-align: center;"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($profile['profile_photo']): ?>
                    <img src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile Photo" class="profile-photo">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 150px; color: var(--primary); margin-bottom: 20px;"></i>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="profile-form">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Profile Photo</label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
                        <input type="hidden" id="cropped_image" name="cropped_image">
                    </div>
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>

            <!-- Upload Content -->
            <div id="upload-content" class="dashboard-section">
                <h3>Upload Content</h3>
                <form action="upload_content.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" required>
                            <option value="video">Video</option>
                            <option value="document">Document</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>File</label>
                        <input type="file" name="file" required>
                    </div>
                    <button type="submit" class="btn">Upload</button>
                </form>
            </div>

            <!-- Content Preview -->
            <div id="content-preview" class="dashboard-section">
                <h3>Content Preview</h3>
                <?php while ($content = $content_result->fetch_assoc()): ?>
                    <div class="content-item">
                        <div>
                            <strong><?php echo htmlspecialchars($content['title']); ?></strong> 
                            (<?php echo $content['type']; ?>) - 
                            <small><?php echo $content['upload_date']; ?></small>
                        </div>
                        <div>
                            <a href="#" class="btn btn-view" onclick="showModal('<?php echo htmlspecialchars($content['title']); ?>', '<?php echo $content['type']; ?>', '<?php echo htmlspecialchars($content['file_path'] ?? $content['content_text']); ?>')">View</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                <button type="submit" name="delete_content" class="btn btn-delete">Delete</button>
                            </form>
                            <a href="update_content.php?id=<?php echo $content['content_id']; ?>" class="btn btn-update">Update</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Post Blog -->
            <div id="post-blog" class="dashboard-section">
                <h3>Post Blog</h3>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="content" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Blog Image (optional)</label>
                        <input type="file" name="blog_image" accept="image/*">
                    </div>
                    <button type="submit" name="post_blog" class="btn">Post Blog</button>
                </form>
            </div>

            <!-- Stats -->
            <div id="stats" class="dashboard-section">
                <h3>Your Stats</h3>
                <div class="stats">
                    <div class="stat-item">
                        <i class="fas fa-upload"></i>
                        <p>Total Content Uploaded</p>
                        <span><?php echo $stats['total_content'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-blog"></i>
                        <p>Total Blogs Posted</p>
                        <span><?php echo $stats['total_blogs'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-bell"></i>
                        <p>Total Admin Messages</p>
                        <span><?php echo $stats['total_admin_messages'] ?? 0; ?></span>
                    </div>
                </div>
                <h3>Real-Time Statistics</h3>
                <canvas id="statsChart" height="200"></canvas>
            </div>

            <!-- Feedback Section -->
            <div id="feedback" class="dashboard-section">
                <h3>Feedback from Users</h3>
                <?php if ($feedback_result && $feedback_result->num_rows > 0): ?>
                    <?php while ($feedback = $feedback_result->fetch_assoc()): ?>
                        <div class="feedback-item">
                            <p><strong><?php echo htmlspecialchars($feedback['name']); ?> said:</strong></p>
                            <p><?php echo htmlspecialchars($feedback['feedback_text']); ?></p>
                            <small>Posted on: <?php echo date('F d, Y H:i', strtotime($feedback['created_at'])); ?></small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No feedback available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for content view -->
    <div id="contentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <h3>Content Details</h3>
            <p><strong>Title:</strong> <span id="modalTitle"></span></p>
            <p><strong>Type:</strong> <span id="modalType"></span></p>
            <p><strong>File Path/Content:</strong> <span id="modalFilePath"></span></p>
        </div>
    </div>

    <!-- Crop Modal -->
    <div id="cropModal" class="crop-modal">
        <div class="crop-modal-content">
            <h3>Crop Your Photo</h3>
            <img id="imageToCrop" src="" alt="Image to crop">
            <div class="crop-buttons">
                <button id="cropButton" class="btn">Crop & Save</button>
                <button id="cancelCrop" class="btn" style="background: #e2e8f0; color: var(--text);">Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        document.querySelectorAll('.sidebar a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        function showModal(title, type, filePath) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalType').textContent = type;
            document.getElementById('modalFilePath').textContent = filePath;
            document.getElementById('contentModal').style.display = 'block';
        }
        function closeModal() {
            document.getElementById('contentModal').style.display = 'none';
        }

        // Initial Chart Setup
        const ctx = document.getElementById('statsChart').getContext('2d');
        let statsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['User Access', 'Feedback Count'],
                datasets: [{
                    label: 'Statistics',
                    data: [<?php echo $user_access_count; ?>, <?php echo $feedback_count; ?>],
                    backgroundColor: ['#6366f1', '#10b981'],
                    borderColor: ['#4f46e5', '#059669'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // Simulated Real-Time Update (every 5 seconds)
        setInterval(() => {
            fetch('get_feedback_count.php?staff_id=<?php echo $staff_id; ?>')
                .then(response => response.json())
                .then(data => {
                    const newUserAccessCount = Math.floor(Math.random() * (200 - 50 + 1)) + 50; // Random between 50 and 200
                    statsChart.data.datasets[0].data = [newUserAccessCount, data.feedback_count];
                    statsChart.update();
                })
                .catch(error => console.error('Error fetching real-time data:', error));
        }, 5000);

        // Cropper.js integration
        let cropper;
        const imageInput = document.getElementById('profile_photo');
        const cropModal = document.getElementById('cropModal');
        const imageToCrop = document.getElementById('imageToCrop');
        const cropButton = document.getElementById('cropButton');
        const cancelCrop = document.getElementById('cancelCrop');
        const croppedImageInput = document.getElementById('cropped_image');
        const form = document.getElementById('profile-form');

        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    imageToCrop.src = event.target.result;
                    cropModal.style.display = 'flex';
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 0.8,
                        responsive: true,
                        minContainerHeight: 400,
                        minCanvasHeight: 300
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        cropButton.addEventListener('click', function() {
            const canvas = cropper.getCroppedCanvas({ width: 150, height: 150 });
            const croppedImageData = canvas.toDataURL('image/png');
            croppedImageInput.value = croppedImageData;
            cropModal.style.display = 'none';
            cropper.destroy();
        });

        cancelCrop.addEventListener('click', function() {
            cropModal.style.display = 'none';
            cropper.destroy();
            imageInput.value = '';
        });

        form.addEventListener('submit', function(e) {
            if (!croppedImageInput.value && imageInput.files.length > 0) {
                e.preventDefault();
                alert('Please crop your photo before submitting.');
            }
        });
    </script>
</body>
</html>