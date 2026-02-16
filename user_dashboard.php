<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch all approved staff with their content count
$staff_list = $conn->query("SELECT u.id, u.name, u.email, u.profile_photo,
    (SELECT COUNT(*) FROM staff_content WHERE staff_id = u.id) as content_count,
    (SELECT ROUND(AVG(rating), 1) FROM staff_ratings WHERE staff_id = u.id) as avg_rating
    FROM users u WHERE u.role = 'staff' AND u.pending_approval = 0 ORDER BY u.name");

// Unread messages count
$msg_result = $conn->query("SELECT COUNT(*) as c FROM user_to_staff_messages WHERE user_id = $user_id AND staff_reply IS NOT NULL AND is_read = 0");
$unread_replies = $msg_result ? $msg_result->fetch_assoc()['c'] : 0;

$staff_count = $staff_list->num_rows;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Learning Spark</title>
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
                <a href="user_dashboard.php?id=<?php echo $user_id; ?>" class="active"><i class="fas fa-th-large"></i>
                    Dashboard</a>
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
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
            <p>Browse staff members and access their learning content.</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="animation-delay:.1s">
                <div class="stat-icon" style="background:var(--gradient-primary)"><i
                        class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <div class="stat-value"><?php echo $staff_count; ?></div>
                    <div class="stat-label">Staff Members</div>
                </div>
            </div>
            <div class="stat-card" style="animation-delay:.15s">
                <div class="stat-icon" style="background:var(--gradient-emerald)"><i class="fas fa-comments"></i></div>
                <div>
                    <div class="stat-value"><?php echo $unread_replies; ?></div>
                    <div class="stat-label">Unread Replies</div>
                </div>
            </div>
        </div>

        <!-- Staff Grid -->
        <div class="glass-card-static mb-24">
            <div class="section-title"><i class="fas fa-chalkboard-teacher"></i> Staff Members</div>
        </div>

        <?php if ($staff_count > 0): ?>
            <div class="grid-auto">
                <?php $delay = 0.2;
                while ($s = $staff_list->fetch_assoc()): ?>
                    <div class="staff-card" style="animation:fadeInUp .4s var(--ease-out) <?php echo $delay; ?>s both"
                        onclick="window.location.href='staff_content.php?staff=<?php echo urlencode($s['name']); ?>&id=<?php echo $user_id; ?>'">
                        <div class="staff-avatar">
                            <?php if ($s['profile_photo'] && file_exists($s['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($s['profile_photo']); ?>" alt="">
                            <?php else: ?>
                                <?php echo strtoupper(substr($s['name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="staff-name"><?php echo htmlspecialchars($s['name']); ?></div>
                        <div class="staff-email"><?php echo htmlspecialchars($s['email']); ?></div>
                        <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:14px">
                            <span class="badge badge-primary"><i class="fas fa-file-alt" style="margin-right:4px"></i>
                                <?php echo $s['content_count']; ?> Content</span>
                            <?php if ($s['avg_rating']): ?>
                                <span class="badge badge-amber"><i class="fas fa-star" style="margin-right:4px"></i>
                                    <?php echo $s['avg_rating']; ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="view-content-btn">
                            <i class="fas fa-eye"></i> View Content
                        </button>
                    </div>
                    <?php $delay += 0.05; endwhile; ?>
            </div>
        <?php else: ?>
            <div class="glass-card">
                <div class="empty-state">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <p>No staff members available at the moment.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>