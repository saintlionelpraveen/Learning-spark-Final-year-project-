<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

if (!isset($_GET['staff_id'])) {
    header("Location: user_dashboard.php");
    exit();
}

$staff_id = intval($_GET['staff_id']);
$selected_content_id = isset($_GET['content_id']) ? intval($_GET['content_id']) : null;

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch staff details
$stmt = $conn->prepare("SELECT name, email, profile_photo FROM users WHERE id = ? AND role = 'staff'");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$staff) {
    die("Staff member not found or not a staff role.");
}

// Fetch content (videos, documents, blogs)
$stmt = $conn->prepare("SELECT content_id, title, file_path, type FROM staff_content WHERE staff_id = ? AND type IN ('video', 'document') ORDER BY upload_date DESC");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$content_result = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT content_id, title, content_text, image_path FROM staff_content WHERE staff_id = ? AND type = 'blog' ORDER BY upload_date DESC");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$blog_result = $stmt->get_result();
$stmt->close();

// Fetch selected content for preview
$selected_content = null;
if ($selected_content_id) {
    $stmt = $conn->prepare("SELECT title, type, file_path, content_text, image_path FROM staff_content WHERE content_id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $selected_content_id, $staff_id);
    $stmt->execute();
    $selected_content = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch unread messages and user profile
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_to_staff_messages WHERE user_id = ? AND staff_reply IS NOT NULL AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();

$stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle feedback submission
if (isset($_POST['submit_feedback'])) {
    $feedback_text = $conn->real_escape_string($_POST['feedback_text']);
    $stmt = $conn->prepare("INSERT INTO staff_feedback (user_id, staff_id, feedback_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $staff_id, $feedback_text);
    $stmt->execute();
    $stmt->close();
    header("Location: staff_content.php?staff_id=$staff_id" . ($selected_content_id ? "&content_id=$selected_content_id" : ""));
    exit();
}

// Send message to staff
if (isset($_POST['send_message'])) {
    $message = $conn->real_escape_string($_POST['message']);
    $stmt = $conn->prepare("INSERT INTO user_to_staff_messages (user_id, staff_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $staff_id, $message);
    $stmt->execute();
    $stmt->close();
    header("Location: staff_content.php?staff_id=$staff_id" . ($selected_content_id ? "&content_id=$selected_content_id" : ""));
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff['name']); ?>'s Content - Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
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

        .content-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .content-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .content-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
        }

        .content-header .title-icon {
            position: relative;
            font-size: 1.5em;
            color: var(--primary);
        }

        .content-header .title-icon .fa-book {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1em;
            color: var(--secondary);
        }

        .content-section {
            margin-bottom: 40px;
        }

        .content-section h3 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .staff-profile img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }

        .content-list {
            list-style: none;
            padding: 0;
        }

        .content-list li {
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary);
        }

        .content-list li a {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .content-list li a:hover {
            color: var(--primary);
        }

        .content-preview video {
            width: 100%;
            max-width: 800px;
            height: 450px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .content-preview iframe {
            width: 100%;
            max-width: 800px;
            height: 600px;
            border-radius: 12px;
            margin-top: 20px;
            border: none;
        }

        .content-preview .fullscreen-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .content-preview .fullscreen-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .content-preview .download-btn {
            display: inline-block;
            margin-top: 10px;
            margin-left: 10px;
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .content-preview .download-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .notes-section textarea {
            width: 100%;
            height: 150px;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            resize: vertical;
            transition: all 0.3s ease;
        }

        .notes-section textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .notes-section .download-notes-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .notes-section .download-notes-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.2);
            outline: none;
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

        .blog-item img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin-top: 10px;
        }

        .blog-item p {
            margin-top: 10px;
            font-size: 1em;
            color: var(--text);
        }

        .feedback-section {
            margin-top: 20px;
        }

        .feedback-section textarea {
            width: 100%;
            height: 100px;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            resize: vertical;
            transition: all 0.3s ease;
        }

        .feedback-section textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .feedback-section .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            background: var(--gradient);
            color: white;
            transition: all 0.3s ease;
        }

        .feedback-section .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar:hover { width: 200px; }
            .main-content { margin-left: 60px; padding: 20px; }
            .sidebar:hover ~ .main-content { margin-left: 200px; }
            .content-card { padding: 20px; }
            .content-preview video { max-width: 100%; height: 300px; }
            .content-preview iframe { max-width: 100%; height: 400px; }
            .content-list li { flex-direction: column; align-items: flex-start; }
        }
    </style>
    <script>
        function toggleFullscreen(elementId) {
            const element = document.getElementById(elementId);
            if (!document.fullscreenElement) {
                element.requestFullscreen().catch(err => {
                    console.error(`Error attempting to enable fullscreen: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        }
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-fire"></i>
            <span>LEARNING SPARK</span>
        </div>
        <div class="user-photo">
            <?php if ($user_profile['profile_photo']): ?>
                <img src="<?php echo htmlspecialchars($user_profile['profile_photo']); ?>" alt="User Photo">
            <?php else: ?>
                <i class="fas fa-user-circle" style="font-size: 40px; color: var(--primary);"></i>
            <?php endif; ?>
        </div>
        <a href="user_profile.php"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="user_messages.php" class="notification-bell"><i class="fas fa-bell"></i><span>Messages</span></a>
        <a href="staff_content.php?staff_id=<?php echo $staff_id; ?>" class="active"><i class="fas fa-book"></i><span>Staff Content</span></a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="content-card">
            <div class="content-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>

            <div class="content-section staff-profile">
                <h3><?php echo htmlspecialchars($staff['name']); ?>'s Content</h3>
                <?php if ($staff['profile_photo']): ?>
                    <img src="<?php echo htmlspecialchars($staff['profile_photo']); ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 150px; color: var(--primary); margin-bottom: 20px;"></i>
                <?php endif; ?>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
            </div>

            <div class="content-section">
                <h3>Videos</h3>
                <?php if ($content_result->num_rows > 0): ?>
                    <ul class="content-list">
                        <?php while ($content = $content_result->fetch_assoc()): if ($content['type'] === 'video'): ?>
                            <li><a href="?staff_id=<?php echo $staff_id; ?>&content_id=<?php echo $content['content_id']; ?>"><?php echo htmlspecialchars($content['title']); ?></a></li>
                        <?php endif; endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No videos available from this staff member.</p>
                <?php endif; ?>
            </div>

            <div class="content-section">
                <h3>Documents</h3>
                <?php if ($content_result->num_rows > 0): ?>
                    <ul class="content-list">
                        <?php $content_result->data_seek(0); while ($content = $content_result->fetch_assoc()): if ($content['type'] === 'document'): ?>
                            <li><a href="?staff_id=<?php echo $staff_id; ?>&content_id=<?php echo $content['content_id']; ?>"><?php echo htmlspecialchars($content['title']); ?></a></li>
                        <?php endif; endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No documents available from this staff member.</p>
                <?php endif; ?>
            </div>

            <div class="content-section">
                <h3>Blogs</h3>
                <?php if ($blog_result->num_rows > 0): ?>
                    <ul class="content-list">
                        <?php $blog_result->data_seek(0); while ($blog = $blog_result->fetch_assoc()): ?>
                            <li><a href="?staff_id=<?php echo $staff_id; ?>&content_id=<?php echo $blog['content_id']; ?>"><?php echo htmlspecialchars($blog['title']); ?></a></li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No blogs available from this staff member.</p>
                <?php endif; ?>
            </div>

            <?php if ($selected_content): ?>
                <div class="content-section">
                    <h3>Preview: <?php echo htmlspecialchars($selected_content['title']); ?></h3>
                    <div class="content-preview">
                        <?php if ($selected_content['type'] === 'video'): ?>
                            <video controls id="video-<?php echo $selected_content_id; ?>"><source src="<?php echo htmlspecialchars($selected_content['file_path']); ?>" type="video/mp4">Your browser does not support the video tag.</video>
                            <button class="fullscreen-btn" onclick="toggleFullscreen('video-<?php echo $selected_content_id; ?>')">Toggle Full Screen</button>
                            <div class="notes-section">
                                <form method="POST" action="download_notes.php">
                                    <textarea name="notes" placeholder="Take notes here..." required></textarea>
                                    <input type="hidden" name="content_id" value="<?php echo $selected_content_id; ?>">
                                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($selected_content['title']); ?>">
                                    <button type="submit" class="download-notes-btn">Download Notes</button>
                                </form>
                            </div>
                        <?php elseif ($selected_content['type'] === 'document'): ?>
                            <iframe src="view_pdf.php?content_id=<?php echo $selected_content_id; ?>" title="Document Preview" id="iframe-<?php echo $selected_content_id; ?>"></iframe>
                            <button class="fullscreen-btn" onclick="toggleFullscreen('iframe-<?php echo $selected_content_id; ?>')">Toggle Full Screen</button>
                            <a href="view_pdf.php?content_id=<?php echo $selected_content_id; ?>&download=1" class="download-btn">Download PDF</a>
                            <div class="notes-section">
                                <form method="POST" action="download_notes.php">
                                    <textarea name="notes" placeholder="Take notes here..." required></textarea>
                                    <input type="hidden" name="content_id" value="<?php echo $selected_content_id; ?>">
                                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($selected_content['title']); ?>">
                                    <button type="submit" class="download-notes-btn">Download Notes</button>
                                </form>
                            </div>
                        <?php elseif ($selected_content['type'] === 'blog'): ?>
                            <div class="blog-item">
                                <?php if ($selected_content['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($selected_content['image_path']); ?>" alt="Blog Image">
                                <?php endif; ?>
                                <p><?php echo nl2br(htmlspecialchars($selected_content['content_text'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="content-section">
                <h3>Send a Message to <?php echo htmlspecialchars($staff['name']); ?></h3>
                <form method="POST">
                    <div class="form-group">
                        <textarea name="message" placeholder="Type your message..." required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn">Send Message</button>
                </form>
            </div>

            <div class="content-section feedback-section">
                <h3>Provide Feedback for <?php echo htmlspecialchars($staff['name']); ?></h3>
                <form method="POST">
                    <textarea name="feedback_text" placeholder="Share your feedback here..." required></textarea>
                    <button type="submit" name="submit_feedback" class="btn">Submit Feedback</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>