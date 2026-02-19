<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_name = $_SESSION['name'];
$staff_id = $_SESSION['user_id'];

include 'config.php';

// Fetch staff profile info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Messages count
$msg_result = $conn->query("SELECT COUNT(*) as count FROM user_to_staff_messages WHERE staff_id = $staff_id");
$msg_count = $msg_result->fetch_assoc()['count'];

$unread_result = $conn->query("SELECT COUNT(*) as count FROM user_to_staff_messages WHERE staff_id = $staff_id AND is_read = 0");
$unread_msg = $unread_result->fetch_assoc()['count'];

// Staff content count
$content_result = $conn->query("SELECT COUNT(*) as count FROM staff_content WHERE staff_id = $staff_id");
$content_count = $content_result->fetch_assoc()['count'];

// Feedback count
$feedback_result = $conn->query("SELECT COUNT(*) as count FROM staff_feedback WHERE staff_id = $staff_id");
$feedback_count = $feedback_result->fetch_assoc()['count'];

// Fetch content list
$content_list = $conn->query("SELECT * FROM staff_content WHERE staff_id = $staff_id ORDER BY upload_date DESC");

// Fetch feedback
$feedback_list = $conn->query("SELECT sf.*, u.name as user_name FROM staff_feedback sf JOIN users u ON sf.user_id = u.id WHERE sf.staff_id = $staff_id ORDER BY sf.created_at DESC");

// Handle profile update
if (isset($_POST['update_profile'])) {
    $new_name = $conn->real_escape_string($_POST['name']);
    $new_email = $conn->real_escape_string($_POST['email']);

    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = "uploads/profiles/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_filename = "staff_" . $staff_id . "_" . time() . "." . $file_ext;
        $target_path = $upload_dir . $new_filename;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_path)) {
            $conn->query("UPDATE users SET profile_photo = '$target_path' WHERE id = $staff_id");
        }
    }

    $conn->query("UPDATE users SET name = '$new_name', email = '$new_email' WHERE id = $staff_id");
    $_SESSION['name'] = $new_name;
    $_SESSION['email'] = $new_email;
    echo "<script>alert('Profile updated!'); window.location.href='staff_dashboard.php?staff=" . urlencode($new_name) . "';</script>";
}

// Handle content upload
if (isset($_POST['upload_content'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $type = $conn->real_escape_string($_POST['type']);
    $content_text = isset($_POST['content_text']) ? $conn->real_escape_string($_POST['content_text']) : '';

    $file_path = '';
    $image_path = '';

    if (isset($_FILES['content_file']) && $_FILES['content_file']['error'] == 0) {
        $upload_dir = "uploads/content/$staff_id/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $file_path = $upload_dir . basename($_FILES['content_file']['name']);
        move_uploaded_file($_FILES['content_file']['tmp_name'], $file_path);
    }

    if (isset($_FILES['content_image']) && $_FILES['content_image']['error'] == 0) {
        $upload_dir = "uploads/content/$staff_id/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $image_path = $upload_dir . basename($_FILES['content_image']['name']);
        move_uploaded_file($_FILES['content_image']['tmp_name'], $image_path);
    }

    $stmt = $conn->prepare("INSERT INTO staff_content (staff_id, title, type, file_path, content_text, image_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $staff_id, $title, $type, $file_path, $content_text, $image_path);
    if ($stmt->execute()) {
        echo "<script>alert('Content uploaded!'); window.location.href='staff_dashboard.php?staff=" . urlencode($staff_name) . "';</script>";
    }
    $stmt->close();
}

// Handle content delete
if (isset($_POST['delete_content'])) {
    $cid = intval($_POST['content_id']);
    // Delete file if exists
    $stmt = $conn->prepare("SELECT file_path, image_path FROM staff_content WHERE content_id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $cid, $staff_id);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($c) {
        if ($c['file_path'] && file_exists($c['file_path']))
            unlink($c['file_path']);
        if ($c['image_path'] && file_exists($c['image_path']))
            unlink($c['image_path']);
        $conn->query("DELETE FROM blog_ratings WHERE content_id = $cid");
        $conn->query("DELETE FROM content_ratings WHERE content_id = $cid");
        $conn->query("DELETE FROM staff_content WHERE content_id = $cid AND staff_id = $staff_id");
    }
    echo "<script>alert('Content deleted!'); window.location.href='staff_dashboard.php?staff=" . urlencode($staff_name) . "';</script>";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard — Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="portal_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="staff_dashboard.php?staff=<?php echo urlencode($staff_name); ?>" class="active"><i
                        class="fas fa-th-large"></i> Dashboard</a>
                <a href="staff_messages.php?staff=<?php echo urlencode($staff_name); ?>">
                    <i class="fas fa-comments"></i> Messages
                    <?php if ($unread_msg > 0): ?>
                        <span class="nav-badge"><?php echo $unread_msg; ?></span>
                    <?php endif; ?>
                </a>
                <a href="login.php" class="nav-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            <button class="mobile-menu-btn" onclick="document.querySelector('.top-nav-links').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="main-content">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>Welcome, <?php echo htmlspecialchars($staff_name); ?> 👋</h1>
            <p>Your staff dashboard — manage your content, profile, and view feedback.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" style="animation-delay:.1s">
                <div class="stat-icon" style="background:var(--gradient-primary)"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="stat-value"><?php echo $content_count; ?></div>
                    <div class="stat-label">Content Uploaded</div>
                </div>
            </div>
            <div class="stat-card" style="animation-delay:.15s">
                <div class="stat-icon" style="background:var(--gradient-emerald)"><i class="fas fa-comments"></i></div>
                <div>
                    <div class="stat-value"><?php echo $msg_count; ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
            </div>
            <div class="stat-card" style="animation-delay:.2s">
                <div class="stat-icon" style="background:var(--gradient-amber)"><i class="fas fa-star"></i></div>
                <div>
                    <div class="stat-value"><?php echo $feedback_count; ?></div>
                    <div class="stat-label">Feedback Received</div>
                </div>
            </div>
            <div class="stat-card" style="animation-delay:.25s">
                <div class="stat-icon" style="background:var(--gradient-rose)"><i class="fas fa-envelope-open-text"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $unread_msg; ?></div>
                    <div class="stat-label">Unread Messages</div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Left Column -->
            <div>
                <!-- Profile Section -->
                <div class="glass-card mb-24" style="animation-delay:.3s">
                    <div class="section-title"><i class="fas fa-user-circle"></i> My Profile</div>
                    <div class="profile-hero" style="padding:20px 0">
                        <div class="profile-avatar" style="margin:0 auto 12px">
                            <?php if ($staff['profile_photo'] && file_exists($staff['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($staff['profile_photo']); ?>" alt="Profile">
                            <?php else: ?>
                                <?php echo strtoupper(substr($staff_name, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($staff_name); ?></div>
                        <span class="profile-role"><i class="fas fa-chalkboard-teacher"></i> Staff</span>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" class="form-input"
                                value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-input"
                                value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-input" accept="image/*">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary"
                            style="width:100%;justify-content:center">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <!-- Upload Content -->
                <div class="glass-card mb-24" style="animation-delay:.35s">
                    <div class="section-title"><i class="fas fa-cloud-upload-alt"></i> Upload Content</div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-input" placeholder="Content title" required>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type" class="form-input" required>
                                <option value="video">Video</option>
                                <option value="document">Document (PDF)</option>
                                <option value="blog">Blog Post</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>File (Video/PDF)</label>
                            <input type="file" name="content_file" class="form-input" accept="video/*,.pdf">
                        </div>
                        <div class="form-group">
                            <label>Thumbnail Image (optional)</label>
                            <input type="file" name="content_image" class="form-input" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Blog Content (if type is Blog)</label>
                            <textarea name="content_text" class="form-input" rows="4"
                                placeholder="Write your blog content here..."></textarea>
                        </div>
                        <button type="submit" name="upload_content" class="btn btn-success"
                            style="width:100%;justify-content:center">
                            <i class="fas fa-upload"></i> Upload Content
                        </button>
                    </form>
                </div>

                <!-- Send to Admin -->
                <div class="glass-card" style="animation-delay:.4s">
                    <div class="section-title"><i class="fas fa-paper-plane"></i> Message Admin</div>
                    <form action="send_admin_message.php" method="POST">
                        <div class="form-group">
                            <textarea name="message" class="form-input" rows="3"
                                placeholder="Type a message to the admin..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                            <i class="fas fa-paper-plane"></i> Send to Admin
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Content List -->
                <div class="glass-card mb-24" style="animation-delay:.3s">
                    <div class="section-title"><i class="fas fa-folder-open"></i> My Content
                        (<?php echo $content_count; ?>)</div>
                    <?php if ($content_list->num_rows > 0): ?>
                        <?php while ($item = $content_list->fetch_assoc()): ?>
                            <?php
                            $type_icon = 'fas fa-file';
                            $type_bg = 'var(--gradient-primary)';
                            if ($item['type'] === 'video') {
                                $type_icon = 'fas fa-video';
                                $type_bg = 'var(--gradient-rose)';
                            } elseif ($item['type'] === 'document') {
                                $type_icon = 'fas fa-file-pdf';
                                $type_bg = 'var(--gradient-amber)';
                            } elseif ($item['type'] === 'blog') {
                                $type_icon = 'fas fa-blog';
                                $type_bg = 'var(--gradient-emerald)';
                            }
                            ?>
                            <div class="content-item">
                                <div class="content-icon" style="background:<?php echo $type_bg; ?>"><i
                                        class="<?php echo $type_icon; ?>"></i></div>
                                <div style="flex:1">
                                    <div class="content-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="text-xs text-muted"><?php echo ucfirst($item['type']); ?> ·
                                        <?php echo date('M d, Y', strtotime($item['upload_date'])); ?>
                                    </div>
                                </div>
                                <div class="content-actions">
                                    <a href="update_content.php?id=<?php echo $item['content_id']; ?>"
                                        class="btn btn-ghost btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline"
                                        onsubmit="return confirm('Delete this content?')">
                                        <input type="hidden" name="content_id" value="<?php echo $item['content_id']; ?>">
                                        <button type="submit" name="delete_content" class="btn btn-danger btn-sm"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>No content uploaded yet. Start by uploading your first content!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Charts -->
                <div class="glass-card mb-24" style="animation-delay:.35s">
                    <div class="section-title"><i class="fas fa-chart-bar"></i> Activity Overview</div>
                    <canvas id="staffChart" style="max-height:260px"></canvas>
                </div>

                <!-- Feedback -->
                <div class="glass-card" style="animation-delay:.4s">
                    <div class="section-title"><i class="fas fa-star"></i> Feedback from Users</div>
                    <?php if ($feedback_list->num_rows > 0): ?>
                        <?php while ($fb = $feedback_list->fetch_assoc()): ?>
                            <div class="feedback-item">
                                <div class="feedback-user">
                                    <i class="fas fa-user" style="color:var(--primary);margin-right:6px"></i>
                                    <?php echo htmlspecialchars($fb['user_name']); ?>
                                    <span class="text-xs text-muted"
                                        style="margin-left:8px"><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></span>
                                </div>
                                <div class="feedback-text"><?php echo htmlspecialchars($fb['feedback_text']); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <p>No feedback received yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Activity Chart
        const ctx = document.getElementById('staffChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Content', 'Messages', 'Feedback', 'Unread'],
                datasets: [{
                    label: 'Count',
                    data: [<?php echo $content_count; ?>, <?php echo $msg_count; ?>, <?php echo $feedback_count; ?>, <?php echo $unread_msg; ?>],
                    backgroundColor: [
                        'rgba(99,102,241,.8)',
                        'rgba(16,185,129,.8)',
                        'rgba(245,158,11,.8)',
                        'rgba(244,63,94,.8)'
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.04)' },
                        ticks: { font: { family: 'Inter', size: 12 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 12, weight: '500' } }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>

</html>