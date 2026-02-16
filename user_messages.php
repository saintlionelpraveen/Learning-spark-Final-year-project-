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

// Fetch messages to staff with replies
$messages = $conn->query("SELECT m.*, u.name as staff_name, u.profile_photo as staff_photo 
    FROM user_to_staff_messages m 
    JOIN users u ON m.staff_id = u.id 
    WHERE m.user_id = $user_id 
    ORDER BY m.created_at DESC");

$total_msgs = $messages->num_rows;

// Unread replies count
$result = $conn->query("SELECT COUNT(*) as c FROM user_to_staff_messages WHERE user_id = $user_id AND staff_reply IS NOT NULL AND is_read = 0");
$unread_count = $result->fetch_assoc()['c'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Learning Spark</title>
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
                <a href="user_messages.php?id=<?php echo $user_id; ?>" class="active">
                    <i class="fas fa-comments"></i> Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="nav-badge"><?php echo $unread_count; ?></span>
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
        <div class="page-header">
            <h1><i class="fas fa-comments" style="color:var(--primary);margin-right:8px"></i> My Messages</h1>
            <p>Messages you've sent to staff and their replies</p>
        </div>

        <!-- Stats -->
        <div class="notif-stats">
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-primary)"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="ns-value"><?php echo $total_msgs; ?></div>
                    <div class="ns-label">Total Messages</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-rose)"><i class="fas fa-reply"></i></div>
                <div>
                    <div class="ns-value"><?php echo $unread_count; ?></div>
                    <div class="ns-label">Unread Replies</div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($total_msgs > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="glass-card mb-16" style="padding:0;overflow:hidden">
                    <!-- Chat Header -->
                    <div
                        style="padding:14px 20px;background:var(--surface-alt);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                        <div class="avatar emerald">
                            <?php if ($msg['staff_photo'] && file_exists($msg['staff_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($msg['staff_photo']); ?>" alt="">
                            <?php else: ?>
                                <?php echo strtoupper(substr($msg['staff_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:.92rem"><?php echo htmlspecialchars($msg['staff_name']); ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--text-muted)">Staff</div>
                        </div>
                        <?php if ($msg['staff_reply']): ?>
                            <span class="badge badge-emerald"><i class="fas fa-check" style="margin-right:3px"></i> Replied</span>
                        <?php else: ?>
                            <span class="badge badge-amber">Awaiting Reply</span>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Bubbles -->
                    <div class="chat-area">
                        <!-- Your message (outgoing) -->
                        <div class="chat-bubble outgoing">
                            <div class="bubble-body">
                                <div class="bubble-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <div class="bubble-meta">
                                    <span class="bubble-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                                    <span class="bubble-read"><i class="fas fa-check-double"></i></span>
                                </div>
                            </div>
                            <div class="bubble-sender-label" style="text-align:right">
                                You · <?php echo date('M d, Y', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>

                        <!-- Staff's reply (incoming) if exists -->
                        <?php if ($msg['staff_reply']): ?>
                            <div class="chat-bubble incoming">
                                <div class="bubble-body">
                                    <div class="bubble-text"><?php echo htmlspecialchars($msg['staff_reply']); ?></div>
                                    <div class="bubble-meta">
                                        <span class="bubble-time">Staff Reply</span>
                                    </div>
                                </div>
                                <div class="bubble-sender-label">
                                    <span class="sender-role">Staff</span> · <?php echo htmlspecialchars($msg['staff_name']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="glass-card">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No messages yet. Send a message to a staff member from their content page.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>