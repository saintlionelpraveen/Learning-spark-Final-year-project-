<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    die("Session email not set or empty.");
}

include 'config.php';

// Fetch admin ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row)
    die("No user found.");
$admin_id = $row['id'];
$stmt->close();

// Handle actions
if (isset($_POST['clear_history'])) {
    $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: admin_notifications.php");
    exit();
}

if (isset($_POST['delete_selected']) && !empty($_POST['selected_messages'])) {
    $selected_ids = implode(',', array_map('intval', $_POST['selected_messages']));
    $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE admin_id = ? AND id IN ($selected_ids)");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: admin_notifications.php");
    exit();
}

if (isset($_GET['read'])) {
    $id = intval($_GET['read']);
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_notifications.php");
    exit();
}

if (isset($_POST['mark_all_read'])) {
    $default_reply = "We will make it";
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET is_read = 1, admin_reply = ?, admin_replied_at = NOW() WHERE admin_id = ? AND is_read = 0");
    $stmt->bind_param("si", $default_reply, $admin_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_notifications.php");
    exit();
}

if (isset($_POST['reply'])) {
    $id = intval($_POST['notification_id']);
    $reply = $conn->real_escape_string($_POST['reply_text']);
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET admin_reply = ?, admin_replied_at = NOW(), is_read = 1 WHERE id = ?");
    $stmt->bind_param("si", $reply, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_notifications.php");
    exit();
}

// Fetch messages
$stmt = $conn->prepare("SELECT n.*, u.name AS staff_name, u.role AS staff_role FROM staff_to_admin_messages n JOIN users u ON n.staff_id = u.id WHERE n.admin_id = ? ORDER BY n.created_at DESC");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$staff_messages = $stmt->get_result();
$total_messages = $staff_messages->num_rows;
$stmt->close();

// Fetch unread count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_to_admin_messages WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count replied
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_to_admin_messages WHERE admin_id = ? AND admin_reply IS NOT NULL");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$replied_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="admin_styles.css">
</head>

<body>

    <!-- Horizontal Top Navigation -->
    <header class="top-nav">
        <div class="top-nav-inner">
            <a href="admin_dashboard.php" class="top-nav-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Learning Spark</span>
            </a>
            <nav class="top-nav-links">
                <a href="admin_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="admin_profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="admin_notifications.php" class="active">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="nav-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_logout.php" class="nav-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            <button class="mobile-menu-btn" onclick="document.querySelector('.top-nav-links').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content top-nav-layout">

        <!-- Page Header -->
        <div class="page-header">
            <h1>Notifications</h1>
            <p>Manage messages from your staff members</p>
        </div>

        <!-- Mini Stats -->
        <div class="notif-stats">
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-primary)"><i class="fas fa-inbox"></i></div>
                <div>
                    <div class="ns-value"><?php echo $total_messages; ?></div>
                    <div class="ns-label">Total Messages</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-amber)"><i class="fas fa-envelope-open"></i></div>
                <div>
                    <div class="ns-value"><?php echo $unread_count; ?></div>
                    <div class="ns-label">Unread</div>
                </div>
            </div>
            <div class="notif-stat-card">
                <div class="ns-icon" style="background:var(--gradient-emerald)"><i class="fas fa-reply-all"></i></div>
                <div>
                    <div class="ns-value"><?php echo $replied_count; ?></div>
                    <div class="ns-label">Replied</div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <?php if ($total_messages > 0): ?>
            <div class="chat-actions-bar">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="display:inline">
                        <button type="submit" name="mark_all_read" class="btn btn-primary btn-sm"><i
                                class="fas fa-check-double"></i> Mark All Read</button>
                    </form>
                <?php endif; ?>
                <form method="POST" style="display:inline">
                    <button type="submit" name="clear_history" class="btn btn-danger btn-sm"
                        onclick="return confirm('Clear ALL message history?')"><i class="fas fa-trash"></i> Clear
                        History</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- WhatsApp-Style Chat Area -->
        <?php if ($total_messages > 0): ?>
            <?php while ($message = $staff_messages->fetch_assoc()): ?>
                <div class="glass-card" style="padding:0;overflow:hidden;margin-bottom:20px">
                    <!-- Chat header with staff info -->
                    <div
                        style="padding:14px 20px;background:var(--surface-alt);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                        <div class="avatar violet"><?php echo strtoupper(substr($message['staff_name'], 0, 1)); ?></div>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:.92rem">
                                <?php echo htmlspecialchars($message['staff_name']); ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?php echo ucfirst($message['staff_role']); ?>
                            </div>
                        </div>
                        <?php if (!$message['is_read']): ?>
                            <span class="bubble-unread-strip" title="Unread"></span>
                            <a href="?read=<?php echo $message['id']; ?>" class="btn btn-ghost btn-sm" style="font-size:.72rem"><i
                                    class="fas fa-check"></i> Mark Read</a>
                        <?php else: ?>
                            <span style="font-size:.7rem;color:var(--emerald)"><i class="fas fa-check-double"></i> Read</span>
                        <?php endif; ?>
                    </div>

                    <!-- Chat bubbles area -->
                    <div class="chat-area">
                        <!-- Staff's incoming message -->
                        <div class="chat-bubble incoming">
                            <div class="bubble-body">
                                <div class="bubble-text"><?php echo htmlspecialchars($message['message']); ?></div>
                                <div class="bubble-meta">
                                    <span
                                        class="bubble-time"><?php echo date('h:i A', strtotime($message['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="bubble-sender-label">
                                <span class="sender-role"><?php echo ucfirst($message['staff_role']); ?></span> ·
                                <?php echo htmlspecialchars($message['staff_name']); ?> ·
                                <?php echo date('M d, Y', strtotime($message['created_at'])); ?>
                            </div>
                        </div>

                        <?php if ($message['staff_reply']): ?>
                            <!-- Staff follow-up -->
                            <div class="chat-bubble incoming">
                                <div class="bubble-body">
                                    <div class="bubble-text"><?php echo htmlspecialchars($message['staff_reply']); ?></div>
                                    <div class="bubble-meta">
                                        <span
                                            class="bubble-time"><?php echo date('h:i A', strtotime($message['staff_replied_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="bubble-sender-label">
                                    <span class="sender-role"><?php echo ucfirst($message['staff_role']); ?></span> ·
                                    <?php echo htmlspecialchars($message['staff_name']); ?> · Follow-up
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($message['admin_reply']): ?>
                            <!-- Admin's outgoing reply -->
                            <div class="chat-bubble outgoing">
                                <div class="bubble-body">
                                    <div class="bubble-text"><?php echo htmlspecialchars($message['admin_reply']); ?></div>
                                    <div class="bubble-meta">
                                        <span
                                            class="bubble-time"><?php echo date('h:i A', strtotime($message['admin_replied_at'])); ?></span>
                                        <span class="bubble-read"><i class="fas fa-check-double"></i></span>
                                    </div>
                                </div>
                                <div class="bubble-sender-label" style="text-align:right;padding-right:4px">
                                    You · <span class="sender-role">Admin</span> ·
                                    <?php echo date('M d, Y', strtotime($message['admin_replied_at'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Reply input (if not yet replied) -->
                    <?php if (!$message['admin_reply']): ?>
                        <div style="padding:12px 20px;background:var(--surface);border-top:1px solid var(--border)">
                            <form method="POST" class="chat-reply-form">
                                <input type="hidden" name="notification_id" value="<?php echo $message['id']; ?>">
                                <textarea name="reply_text" placeholder="Type a message..." required></textarea>
                                <button type="submit" name="reply" class="send-btn" title="Send Reply">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="glass-card">
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No messages from staff at this time.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>