<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$staff_name = isset($_GET['staff']) ? $_GET['staff'] : '';

include 'config.php';

// Fetch staff info
$stmt = $conn->prepare("SELECT * FROM users WHERE name = ? AND role = 'staff' AND pending_approval = 0");
$stmt->bind_param("s", $staff_name);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    echo "<script>alert('Staff member not found.'); window.location.href='user_dashboard.php?id=$user_id';</script>";
    exit();
}

$staff_id = $staff['id'];

// Fetch content by this staff
$content_list = $conn->query("SELECT * FROM staff_content WHERE staff_id = $staff_id ORDER BY upload_date DESC");
$video_count = 0;
$doc_count = 0;
$blog_count = 0;
$all_content = [];
while ($c = $content_list->fetch_assoc()) {
    $all_content[] = $c;
    if ($c['type'] === 'video')
        $video_count++;
    elseif ($c['type'] === 'document')
        $doc_count++;
    elseif ($c['type'] === 'blog')
        $blog_count++;
}

// Fetch staff's average rating
$rating_result = $conn->query("SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total_ratings FROM staff_ratings WHERE staff_id = $staff_id");
$rating_data = $rating_result->fetch_assoc();

// Handle message to staff
if (isset($_POST['send_message'])) {
    $message = $conn->real_escape_string($_POST['message']);
    $stmt = $conn->prepare("INSERT INTO user_to_staff_messages (user_id, staff_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $staff_id, $message);
    if ($stmt->execute()) {
        echo "<script>alert('Message sent!'); window.location.href='staff_content.php?staff=" . urlencode($staff_name) . "&id=$user_id';</script>";
    }
    $stmt->close();
}

// Handle feedback
if (isset($_POST['send_feedback'])) {
    $feedback = $conn->real_escape_string($_POST['feedback']);
    $stmt = $conn->prepare("INSERT INTO staff_feedback (user_id, staff_id, feedback_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $staff_id, $feedback);
    if ($stmt->execute()) {
        echo "<script>alert('Feedback sent!'); window.location.href='staff_content.php?staff=" . urlencode($staff_name) . "&id=$user_id';</script>";
    }
    $stmt->close();
}

// Handle rating
if (isset($_POST['rate_staff'])) {
    $rating = intval($_POST['rating']);
    if ($rating >= 1 && $rating <= 5) {
        // Upsert rating
        $check = $conn->query("SELECT * FROM staff_ratings WHERE user_id = $user_id AND staff_id = $staff_id");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE staff_ratings SET rating = $rating WHERE user_id = $user_id AND staff_id = $staff_id");
        } else {
            $conn->query("INSERT INTO staff_ratings (user_id, staff_id, rating) VALUES ($user_id, $staff_id, $rating)");
        }
        echo "<script>alert('Rating submitted!'); window.location.href='staff_content.php?staff=" . urlencode($staff_name) . "&id=$user_id';</script>";
    }
}

// Unread replies for nav badge
$result = $conn->query("SELECT COUNT(*) as c FROM user_to_staff_messages WHERE user_id = $user_id AND staff_reply IS NOT NULL AND is_read = 0");
$unread_replies = $result ? $result->fetch_assoc()['c'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff_name); ?>'s Content — Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="portal_styles.css">
</head>

<body>
    <!-- Top Navigation -->
    <header class="top-nav">
        <div class="top-nav-inner">
            <a href="user_dashboard.php?id=<?php echo $user_id; ?>" class="top-nav-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Learning Spark</span>
            </a>
            <nav class="top-nav-links">
                <a href="user_dashboard.php?id=<?php echo $user_id; ?>"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="user_profile.php?id=<?php echo $user_id; ?>"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="user_messages.php?id=<?php echo $user_id; ?>">
                    <i class="fas fa-comments"></i> Messages
                    <?php if ($unread_replies > 0): ?>
                        <span class="nav-badge"><?php echo $unread_replies; ?></span>
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
        <!-- Staff Profile Header -->
        <div class="glass-card mb-24" style="animation-delay:.1s">
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                <div class="avatar xl indigo">
                    <?php if ($staff['profile_photo'] && file_exists($staff['profile_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($staff['profile_photo']); ?>" alt="">
                    <?php else: ?>
                        <?php echo strtoupper(substr($staff_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div style="flex:1">
                    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:4px">
                        <?php echo htmlspecialchars($staff_name); ?>
                    </h1>
                    <div style="font-size:.85rem;color:var(--text-secondary);margin-bottom:8px">
                        <i class="fas fa-envelope" style="margin-right:4px"></i>
                        <?php echo htmlspecialchars($staff['email']); ?>
                    </div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <span class="badge badge-primary"><i class="fas fa-video" style="margin-right:4px"></i>
                            <?php echo $video_count; ?> Videos</span>
                        <span class="badge badge-amber"><i class="fas fa-file-pdf" style="margin-right:4px"></i>
                            <?php echo $doc_count; ?> Docs</span>
                        <span class="badge badge-emerald"><i class="fas fa-blog" style="margin-right:4px"></i>
                            <?php echo $blog_count; ?> Blogs</span>
                        <?php if ($rating_data['avg_rating']): ?>
                            <span class="badge badge-amber"><i class="fas fa-star" style="margin-right:4px"></i>
                                <?php echo $rating_data['avg_rating']; ?> (<?php echo $rating_data['total_ratings']; ?>
                                ratings)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="user_dashboard.php?id=<?php echo $user_id; ?>" class="btn btn-ghost">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="grid-2">
            <!-- Left: Content -->
            <div>
                <!-- Content Tabs -->
                <div class="tabs">
                    <button class="tab-btn active" onclick="showType('all', this)">All
                        (<?php echo count($all_content); ?>)</button>
                    <button class="tab-btn" onclick="showType('video', this)">Videos
                        (<?php echo $video_count; ?>)</button>
                    <button class="tab-btn" onclick="showType('document', this)">Docs
                        (<?php echo $doc_count; ?>)</button>
                    <button class="tab-btn" onclick="showType('blog', this)">Blogs (<?php echo $blog_count; ?>)</button>
                </div>

                <?php if (count($all_content) > 0): ?>
                    <?php foreach ($all_content as $item): ?>
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
                        <div class="glass-card mb-16 content-card" data-type="<?php echo $item['type']; ?>"
                            style="cursor:pointer" onclick="showPreview(<?php echo $item['content_id']; ?>)">
                            <div style="display:flex;align-items:center;gap:14px">
                                <div class="content-icon"
                                    style="background:<?php echo $type_bg; ?>;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem">
                                    <i class="<?php echo $type_icon; ?>"></i>
                                </div>
                                <div style="flex:1">
                                    <div style="font-weight:600;font-size:.92rem">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </div>
                                    <div class="text-xs text-muted"><?php echo ucfirst($item['type']); ?> ·
                                        <?php echo date('M d, Y', strtotime($item['upload_date'])); ?>
                                    </div>
                                </div>
                                <span class="badge badge-primary">View</span>
                            </div>

                            <!-- Hidden preview -->
                            <div class="content-preview" id="preview-<?php echo $item['content_id']; ?>"
                                style="display:none;margin-top:16px">
                                <?php if ($item['type'] === 'video' && $item['file_path']): ?>
                                    <div class="preview-container">
                                        <video controls>
                                            <source src="<?php echo htmlspecialchars($item['file_path']); ?>">
                                        </video>
                                    </div>
                                <?php elseif ($item['type'] === 'document' && $item['file_path']): ?>
                                    <div class="preview-container" style="background:#f1f5f9">
                                        <iframe src="<?php echo htmlspecialchars($item['file_path']); ?>"></iframe>
                                    </div>
                                <?php elseif ($item['type'] === 'blog'): ?>
                                    <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt=""
                                            style="width:100%;border-radius:var(--radius-sm);margin-bottom:12px">
                                    <?php endif; ?>
                                    <div style="font-size:.9rem;line-height:1.7;color:var(--text-secondary)">
                                        <?php echo nl2br(htmlspecialchars($item['content_text'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="glass-card">
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>This staff member hasn't uploaded any content yet.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Actions -->
            <div>
                <!-- Rate Staff -->
                <div class="glass-card mb-24" style="animation-delay:.2s">
                    <div class="section-title"><i class="fas fa-star"></i> Rate This Staff</div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Your Rating</label>
                            <div style="display:flex;gap:8px;margin-bottom:12px" id="starRating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" class="star-btn" onclick="setRating(<?php echo $i; ?>)"
                                        style="background:none;border:none;font-size:1.6rem;cursor:pointer;color:var(--border);transition:color .2s">
                                        <i class="fas fa-star"></i>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="0">
                        </div>
                        <button type="submit" name="rate_staff" class="btn btn-primary"
                            style="width:100%;justify-content:center">
                            <i class="fas fa-star"></i> Submit Rating
                        </button>
                    </form>
                </div>

                <!-- Send Message -->
                <div class="glass-card mb-24" style="animation-delay:.25s">
                    <div class="section-title"><i class="fas fa-paper-plane"></i> Send Message</div>
                    <form method="POST">
                        <div class="form-group">
                            <textarea name="message" class="form-input" rows="4"
                                placeholder="Type your message to <?php echo htmlspecialchars($staff_name); ?>..."
                                required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary"
                            style="width:100%;justify-content:center">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>

                <!-- Send Feedback -->
                <div class="glass-card" style="animation-delay:.3s">
                    <div class="section-title"><i class="fas fa-comment-dots"></i> Submit Feedback</div>
                    <form method="POST">
                        <div class="form-group">
                            <textarea name="feedback" class="form-input" rows="4"
                                placeholder="Share your thoughts about this staff's content..." required></textarea>
                        </div>
                        <button type="submit" name="send_feedback" class="btn btn-success"
                            style="width:100%;justify-content:center">
                            <i class="fas fa-comment-dots"></i> Submit Feedback
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Star Rating
        function setRating(value) {
            document.getElementById('ratingValue').value = value;
            document.querySelectorAll('#starRating .star-btn').forEach((btn, index) => {
                btn.style.color = index < value ? '#f59e0b' : 'var(--border)';
            });
        }

        // Content Preview Toggle
        function showPreview(id) {
            const preview = document.getElementById('preview-' + id);
            const isVisible = preview.style.display !== 'none';
            // Close all
            document.querySelectorAll('.content-preview').forEach(p => p.style.display = 'none');
            if (!isVisible) {
                preview.style.display = 'block';
            }
        }

        // Content Filter
        function showType(type, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.content-card').forEach(card => {
                if (type === 'all' || card.dataset.type === type) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>