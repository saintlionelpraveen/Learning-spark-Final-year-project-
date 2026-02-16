<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_name = $_SESSION['name'];
$staff_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Handle reply to user
if (isset($_POST['reply_user'])) {
    $msg_id = intval($_POST['message_id']);
    $reply = $conn->real_escape_string($_POST['reply_text']);
    $stmt = $conn->prepare("UPDATE user_to_staff_messages SET staff_reply = ?, is_read = 1 WHERE id = ?");
    $stmt->bind_param("si", $reply, $msg_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>window.location.href='staff_messages.php?staff=" . urlencode($staff_name) . "';</script>";
}

// Handle delete message
if (isset($_POST['delete_message'])) {
    $msg_id = intval($_POST['message_id']);
    $stmt = $conn->prepare("DELETE FROM user_to_staff_messages WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $msg_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>window.location.href='staff_messages.php?staff=" . urlencode($staff_name) . "';</script>";
}

// Mark as read
if (isset($_POST['mark_read'])) {
    $msg_id = intval($_POST['message_id']);
    $stmt = $conn->prepare("UPDATE user_to_staff_messages SET is_read = 1 WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $msg_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>window.location.href='staff_messages.php?staff=" . urlencode($staff_name) . "';</script>";
}

// Fetch user messages
$user_messages = $conn->query("SELECT m.*, u.name as user_name, u.profile_photo as user_photo 
    FROM user_to_staff_messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.staff_id = $staff_id 
    ORDER BY m.created_at DESC");

// Fetch admin messages to staff
$admin_messages = $conn->query("SELECT m.*, u.name as admin_name 
    FROM staff_to_admin_messages m 
    JOIN users u ON m.admin_id = u.id 
    WHERE m.staff_id = $staff_id 
    ORDER BY m.created_at DESC");

// Stats
$total_msgs = $user_messages->num_rows;
$unread_count = 0;
$replied_count = 0;
// Count stats
$conn2 = new mysqli("localhost", "root", "", "learning");
$result = $conn2->query("SELECT COUNT(*) as c FROM user_to_staff_messages WHERE staff_id = $staff_id AND is_read = 0");
$unread_count = $result->fetch_assoc()['c'];
$result = $conn2->query("SELECT COUNT(*) as c FROM user_to_staff_messages WHERE staff_id = $staff_id AND staff_reply IS NOT NULL AND staff_reply != ''");
$replied_count = $result->fetch_assoc()['c'];
$conn2->close();

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
            <a href="staff_dashboard.php?staff=<?php echo urlencode($staff_name); ?>" class="top-nav-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Learning Spark</span>
            </a>
            <nav class="top-nav-links">
                <a href="staff_dashboard.php?staff=<?php echo urlencode($staff_name); ?>"><i
                        class="fas fa-th-large"></i> Dashboard</a>
                <a href="staff_messages.php?staff=<?php echo urlencode($staff_name); ?>" class="active">
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
            <h1><i class="fas fa-comments" style="color:var(--primary);margin-right:8px"></i> Messages</h1>
            <p>View and respond to user messages and admin communications</p>
        </div>

        <!-- Message Stats -->
        <div class="notif-stats">
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-primary)"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="ns-value"><?php echo $total_msgs; ?></div>
                    <div class="ns-label">Total Messages</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-rose)"><i class="fas fa-envelope-open"></i></div>
                <div>
                    <div class="ns-value"><?php echo $unread_count; ?></div>
                    <div class="ns-label">Unread</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-emerald)"><i class="fas fa-reply"></i></div>
                <div>
                    <div class="ns-value"><?php echo $replied_count; ?></div>
                    <div class="ns-label">Replied</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('user-msgs', this)">
                <i class="fas fa-users"></i> User Messages
            </button>
            <button class="tab-btn" onclick="switchTab('admin-msgs', this)">
                <i class="fas fa-user-shield"></i> Admin Messages
            </button>
        </div>

        <!-- User Messages Tab -->
        <div id="user-msgs" class="tab-content active">
            <?php if ($user_messages->num_rows > 0): ?>
                <?php while ($msg = $user_messages->fetch_assoc()): ?>
                    <div class="glass-card mb-16" style="padding:0;overflow:hidden">
                        <!-- Chat Header -->
                        <div
                            style="padding:14px 20px;background:var(--surface-alt);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                            <div class="avatar indigo">
                                <?php if ($msg['user_photo'] && file_exists($msg['user_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($msg['user_photo']); ?>" alt="">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($msg['user_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:.92rem"><?php echo htmlspecialchars($msg['user_name']); ?>
                                </div>
                                <div style="font-size:.72rem;color:var(--text-muted)">User</div>
                            </div>
                            <?php if (!$msg['is_read']): ?>
                                <span class="badge badge-rose">Unread</span>
                            <?php else: ?>
                                <span class="badge badge-emerald">Read</span>
                            <?php endif; ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" name="delete_message" class="btn btn-ghost btn-sm"
                                    onclick="return confirm('Delete this message?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>

                        <!-- Chat Bubbles -->
                        <div class="chat-area">
                            <!-- User's message (incoming) -->
                            <div class="chat-bubble incoming">
                                <div class="bubble-body">
                                    <div class="bubble-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                                    <div class="bubble-meta">
                                        <span
                                            class="bubble-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="bubble-sender-label">
                                    <span class="sender-role">User</span> · <?php echo htmlspecialchars($msg['user_name']); ?> ·
                                    <?php echo date('M d, Y', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>

                            <!-- Staff reply (outgoing) if exists -->
                            <?php if ($msg['staff_reply']): ?>
                                <div class="chat-bubble outgoing">
                                    <div class="bubble-body">
                                        <div class="bubble-text"><?php echo htmlspecialchars($msg['staff_reply']); ?></div>
                                        <div class="bubble-meta">
                                            <span class="bubble-time">Replied</span>
                                            <span class="bubble-read"><i class="fas fa-check-double"></i></span>
                                        </div>
                                    </div>
                                    <div class="bubble-sender-label" style="text-align:right">
                                        You · Staff
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Reply / Actions -->
                        <?php if (!$msg['staff_reply']): ?>
                            <form method="POST" class="chat-reply-form">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <textarea name="reply_text" placeholder="Type your reply..." required></textarea>
                                <button type="submit" name="reply_user" class="send-btn" title="Send Reply">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="glass-card">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No messages from users yet.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Admin Messages Tab -->
        <div id="admin-msgs" class="tab-content">
            <?php if ($admin_messages->num_rows > 0): ?>
                <?php while ($amsg = $admin_messages->fetch_assoc()): ?>
                    <div class="glass-card mb-16" style="padding:0;overflow:hidden">
                        <div
                            style="padding:14px 20px;background:var(--surface-alt);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                            <div class="avatar rose"><?php echo strtoupper(substr($amsg['admin_name'], 0, 1)); ?></div>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:.92rem">
                                    <?php echo htmlspecialchars($amsg['admin_name']); ?></div>
                                <div style="font-size:.72rem;color:var(--text-muted)">Admin</div>
                            </div>
                            <?php if ($amsg['is_read']): ?>
                                <span class="badge badge-emerald">Read</span>
                            <?php else: ?>
                                <span class="badge badge-amber">Sent</span>
                            <?php endif; ?>
                        </div>
                        <div class="chat-area">
                            <!-- Your message to admin (outgoing) -->
                            <div class="chat-bubble outgoing">
                                <div class="bubble-body">
                                    <div class="bubble-text"><?php echo htmlspecialchars($amsg['message']); ?></div>
                                    <div class="bubble-meta">
                                        <span
                                            class="bubble-time"><?php echo date('h:i A', strtotime($amsg['created_at'])); ?></span>
                                        <?php if ($amsg['is_read']): ?>
                                            <span class="bubble-read"><i class="fas fa-check-double"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="bubble-sender-label" style="text-align:right">
                                    You · <?php echo date('M d, Y', strtotime($amsg['created_at'])); ?>
                                </div>
                            </div>

                            <!-- Admin reply if exists -->
                            <?php if ($amsg['admin_reply']): ?>
                                <div class="chat-bubble incoming">
                                    <div class="bubble-body">
                                        <div class="bubble-text"><?php echo htmlspecialchars($amsg['admin_reply']); ?></div>
                                        <div class="bubble-meta">
                                            <span class="bubble-time">Admin Reply</span>
                                        </div>
                                    </div>
                                    <div class="bubble-sender-label">
                                        <span class="sender-role">Admin</span> ·
                                        <?php echo htmlspecialchars($amsg['admin_name']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="glass-card">
                    <div class="empty-state">
                        <i class="fas fa-user-shield"></i>
                        <p>No messages to/from admin.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>

</html>