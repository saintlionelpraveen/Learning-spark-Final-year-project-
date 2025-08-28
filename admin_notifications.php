<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    die("Session email not set or empty.");
}

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch admin ID first (needed for all operations)
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if ($stmt === false) die("Prepare failed (admin_id): " . $conn->error);
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row === null) {
    die("No user found with email: " . htmlspecialchars($_SESSION['email']));
}
$admin_id = $row['id'];
$stmt->close();

// Handle actions before fetching messages
// Clear message history
if (isset($_POST['clear_history'])) {
    $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: admin_notifications.php");
    exit();
}

// Delete selected messages
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

// Mark notification as read
if (isset($_GET['read'])) {
    $id = $conn->real_escape_string($_GET['read']);
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_notifications.php");
    exit();
}

// Mark all messages as read and send default reply
if (isset($_POST['mark_all_read'])) {
    $default_reply = "We will make it";
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET is_read = 1, admin_reply = ?, admin_replied_at = NOW() WHERE admin_id = ? AND is_read = 0");
    $stmt->bind_param("si", $default_reply, $admin_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_notifications.php");
    exit();
}

// Send reply and mark as read
if (isset($_POST['reply'])) {
    $id = $conn->real_escape_string($_POST['notification_id']);
    $reply = $conn->real_escape_string($_POST['reply_text']);
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET admin_reply = ?, admin_replied_at = NOW(), is_read = 1 WHERE id = ?");
    $stmt->bind_param("si", $reply, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_notifications.php");
    exit();
}

// Fetch staff messages to admin (after all actions)
$stmt = $conn->prepare("SELECT n.*, u.name AS staff_name FROM staff_to_admin_messages n JOIN users u ON n.staff_id = u.id WHERE n.admin_id = ? ORDER BY n.created_at DESC");
if ($stmt === false) die("Prepare failed (notifications): " . $conn->error);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$staff_messages = $stmt->get_result();
$stmt->close();

// Fetch unread count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_to_admin_messages WHERE admin_id = ? AND is_read = 0");
if ($stmt === false) die("Prepare failed (unread_count): " . $conn->error);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - Learning Portal</title>
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
        }

        /* Sidebar */
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
        }

        .sidebar .logo i {
            font-size: 1.8em;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .sidebar:hover .logo i {
            transform: rotate(360deg);
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

        .notification-bell {
            position: relative;
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

        /* Main Content */
        .main-content {
            margin-left: 70px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }

        .sidebar:hover ~ .main-content {
            margin-left: 250px;
        }

        .notification-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .notification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .notification-header {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        .notification-header p {
            color: var(--text-light);
            font-size: 1.1em;
        }

        .message-section {
            margin-bottom: 40px;
        }

        .message-section h2 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .message-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            display: flex;
            flex-direction: column;
        }

        .message-item.unread {
            background: #eef2ff;
            border-left-color: var(--accent);
        }

        .message-item p {
            margin-bottom: 10px;
            color: var(--text);
        }

        .message-item .timestamp {
            color: var(--text-light);
            font-size: 0.9em;
        }

        .message-item .checkbox {
            margin-bottom: 10px;
        }

        .form-group {
            margin-top: 20px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            resize: vertical;
            transition: all 0.3s ease;
        }

        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .reply-btn {
            background: var(--gradient);
            color: white;
        }

        .reply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .read-btn {
            background: #10b981;
            color: white;
        }

        .read-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        .all-read-btn {
            background: #8b5cf6;
            color: white;
            padding: 12px 24px;
        }

        .all-read-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);
        }

        .clear-history-btn {
            background: #ef4444;
            color: white;
            padding: 12px 24px;
        }

        .clear-history-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }

        .delete-selected-btn {
            background: #f97316;
            color: white;
            padding: 12px 24px;
        }

        .delete-selected-btn:hover {
            background: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.3);
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        /* Responsive Design */
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

            .notification-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .button-group {
                flex-direction: column;
                width: 100%;
                margin-top: 20px;
            }

            .all-read-btn,
            .clear-history-btn,
            .delete-selected-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <a href="admin_profile.php"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="admin_notifications.php" class="notification-bell active"><i class="fas fa-bell"></i><span>Notifications</span></a>
        <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="notification-card">
            <div class="notification-header">
                <div>
                    <h1>Notifications</h1>
                    <p>Manage your messages</p>
                </div>
                <div class="button-group">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="mark_all_read" class="btn all-read-btn">Mark All as Read</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($staff_messages->num_rows > 0): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="clear_history" class="btn clear-history-btn">Clear History</button>
                        </form>
                        <button type="submit" form="delete-selected-form" name="delete_selected" class="btn delete-selected-btn">Delete Selected</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="message-section">
                <h2>Messages from Staff</h2>
                <form id="delete-selected-form" method="POST">
                    <?php if ($staff_messages->num_rows > 0): ?>
                        <?php while ($message = $staff_messages->fetch_assoc()): ?>
                            <div class="message-item <?php echo !$message['is_read'] ? 'unread' : ''; ?>">
                                <div class="checkbox">
                                    <input type="checkbox" name="selected_messages[]" value="<?php echo $message['id']; ?>">
                                </div>
                                <p><strong><?php echo htmlspecialchars($message['staff_name']); ?>:</strong> <?php echo htmlspecialchars($message['message']); ?></p>
                                <p class="timestamp">Sent on: <?php echo $message['created_at']; ?></p>
                                <?php if ($message['staff_reply']): ?>
                                    <p><strong><?php echo htmlspecialchars($message['staff_name']); ?> replied:</strong> <?php echo htmlspecialchars($message['staff_reply']); ?></p>
                                    <p class="timestamp">Replied on: <?php echo $message['staff_replied_at']; ?></p>
                                <?php endif; ?>
                                <?php if (!$message['admin_reply']): ?>
                                    <form method="POST">
                                        <div class="form-group">
                                            <textarea name="reply_text" placeholder="Type your reply..." required></textarea>
                                        </div>
                                        <input type="hidden" name="notification_id" value="<?php echo $message['id']; ?>">
                                        <button type="submit" name="reply" class="btn reply-btn">Send Reply</button>
                                        <?php if (!$message['is_read']): ?>
                                            <a href="?read=<?php echo $message['id']; ?>" class="btn read-btn">Mark as Read</a>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No messages from staff at this time.</p>
                    <?php endif; ?>
                </form>
            </div>

            <div class="message-section">
                <h2>Your Replies to Staff</h2>
                <?php 
                $staff_messages->data_seek(0); // Reset pointer to beginning
                $has_replies = false;
                while ($message = $staff_messages->fetch_assoc()) {
                    if ($message['admin_reply']) {
                        $has_replies = true;
                        ?>
                        <div class="message-item">
                            <p><strong>To <?php echo htmlspecialchars($message['staff_name']); ?>:</strong> <?php echo htmlspecialchars($message['admin_reply']); ?></p>
                            <p class="timestamp">Replied on: <?php echo $message['admin_replied_at']; ?></p>
                            <p><strong>Original Message:</strong> <?php echo htmlspecialchars($message['message']); ?></p>
                            <p class="timestamp">Sent on: <?php echo $message['created_at']; ?></p>
                        </div>
                        <?php
                    }
                }
                if (!$has_replies) {
                    echo "<p>No replies sent to staff yet.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>