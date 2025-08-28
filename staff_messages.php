<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['name'];

$conn = new mysqli("127.0.0.1", "root", "", "learning");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch staff-to-admin messages
$stmt = $conn->prepare("SELECT * FROM staff_to_admin_messages WHERE staff_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$admin_messages = $stmt->get_result();
$stmt->close();

// Fetch user-to-staff messages
$stmt = $conn->prepare("SELECT * FROM user_to_staff_messages WHERE staff_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$user_messages = $stmt->get_result();
$stmt->close();

// Count unread user messages
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_to_staff_messages WHERE staff_id = ? AND is_read = 0");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();

// Fetch profile details
$stmt = $conn->prepare("SELECT name, email, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle replies
if (isset($_POST['reply_to_admin'])) {
    $message_id = intval($_POST['message_id']);
    $reply = $conn->real_escape_string($_POST['reply']);
    $stmt = $conn->prepare("UPDATE staff_to_admin_messages SET staff_reply = ?, staff_replied_at = NOW() WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("sii", $reply, $message_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}
if (isset($_POST['reply_to_user'])) {
    $message_id = intval($_POST['message_id']);
    $reply = $conn->real_escape_string($_POST['reply']);
    $stmt = $conn->prepare("UPDATE user_to_staff_messages SET staff_reply = ?, replied_at = NOW(), is_read = 1 WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("sii", $reply, $message_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

// Handle delete single admin message
if (isset($_POST['delete_admin_message'])) {
    $message_id = intval($_POST['message_id']);
    $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $message_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

// Handle delete single user message
if (isset($_POST['delete_user_message'])) {
    $message_id = intval($_POST['message_id']);
    $stmt = $conn->prepare("DELETE FROM user_to_staff_messages WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("ii", $message_id, $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

// Handle delete all admin messages
if (isset($_POST['delete_all_admin_messages'])) {
    $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

// Handle delete all user messages
if (isset($_POST['delete_all_user_messages'])) {
    $stmt = $conn->prepare("DELETE FROM user_to_staff_messages WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->close();
    header("Refresh:0");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Learning Spark</title>
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

        /* Main Content */
        .main-content {
            margin-left: 70px;
            padding: 40px;
            flex: 1;
            transition: margin-left 0.3s ease;
        }

        .sidebar:hover ~ .main-content {
            margin-left: 250px;
        }

        .messages-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .messages-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .messages-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .messages-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
        }

        .messages-header .title-icon {
            position: relative;
            font-size: 1.5em;
            color: var(--primary);
        }

        .messages-header .title-icon .fa-book {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1em;
            color: var(--secondary);
        }

        .messages-section {
            margin-bottom: 40px;
        }

        .messages-section h3 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
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

        .btn-delete {
            background: var(--error);
            padding: 8px 15px;
            width: auto;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-delete-all {
            background: var(--error);
            margin-top: 20px;
        }

        .btn-delete-all:hover {
            background: #dc2626;
        }

        .notification {
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
            position: relative;
        }

        .notification.unread {
            border-left-color: var(--error);
            background: rgba(239, 68, 68, 0.05);
        }

        .notification p {
            margin: 8px 0;
            font-size: 1em;
            color: var(--text);
        }

        .notification small {
            color: var(--text-light);
            font-size: 0.9em;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
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

            .messages-card {
                padding: 20px;
            }

            .notification-actions {
                flex-direction: column;
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
        <a href="staff_dashboard.php#profile"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="staff_dashboard.php#upload-content"><i class="fas fa-upload"></i><span>Upload Content</span></a>
        <a href="staff_dashboard.php#content-preview"><i class="fas fa-eye"></i><span>Content Preview</span></a>
        <a href="staff_dashboard.php#post-blog"><i class="fas fa-blog"></i><span>Post Blog</span></a>
        <a href="staff_messages.php" class="active notification-bell"><i class="fas fa-bell"></i><span>Messages</span></a>
        <a href="staff_dashboard.php#stats"><i class="fas fa-chart-bar"></i><span>View Stats</span></a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="messages-card">
            <div class="messages-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>

            <!-- Send Message to Admin -->
            <div class="messages-section">
                <h3>Send Message to Admin</h3>
                <form action="send_admin_message.php" method="POST">
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn">Send</button>
                </form>
            </div>

            <!-- Messages with Admin -->
            <div class="messages-section">
                <h3>Messages with Admin</h3>
                <?php if ($admin_messages->num_rows > 0): ?>
                    <?php while ($msg = $admin_messages->fetch_assoc()): ?>
                        <div class="notification <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                            <p><strong>You:</strong> <?php echo htmlspecialchars($msg['message']); ?></p>
                            <?php if ($msg['admin_reply']): ?>
                                <p><strong>Admin:</strong> <?php echo htmlspecialchars($msg['admin_reply']); ?></p>
                            <?php endif; ?>
                            <?php if ($msg['staff_reply']): ?>
                                <p><strong>You:</strong> <?php echo htmlspecialchars($msg['staff_reply']); ?></p>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <div class="form-group">
                                        <label>Reply to Admin</label>
                                        <textarea name="reply" rows="2" required></textarea>
                                    </div>
                                    <div class="notification-actions">
                                        <button type="submit" name="reply_to_admin" class="btn">Reply</button>
                                        <button type="submit" name="delete_admin_message" class="btn btn-delete">Delete</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <?php if ($msg['staff_reply']): ?>
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <div class="notification-actions">
                                        <button type="submit" name="delete_admin_message" class="btn btn-delete">Delete</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <p><small><?php echo $msg['created_at']; ?></small></p>
                        </div>
                    <?php endwhile; ?>
                    <form method="POST">
                        <button type="submit" name="delete_all_admin_messages" class="btn btn-delete-all">Delete All Admin Messages</button>
                    </form>
                <?php else: ?>
                    <p>No messages with admin yet.</p>
                <?php endif; ?>
            </div>

            <!-- Messages from Users -->
            <div class="messages-section">
                <h3>Messages from Users</h3>
                <?php if ($user_messages->num_rows > 0): ?>
                    <?php while ($msg = $user_messages->fetch_assoc()): ?>
                        <div class="notification <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                            <p><strong>User:</strong> <?php echo htmlspecialchars($msg['message']); ?></p>
                            <?php if ($msg['staff_reply']): ?>
                                <p><strong>You:</strong> <?php echo htmlspecialchars($msg['staff_reply']); ?></p>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <div class="form-group">
                                        <label>Reply to User</label>
                                        <textarea name="reply" rows="2" required></textarea>
                                    </div>
                                    <div class="notification-actions">
                                        <button type="submit" name="reply_to_user" class="btn">Reply</button>
                                        <button type="submit" name="delete_user_message" class="btn btn-delete">Delete</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <?php if ($msg['staff_reply']): ?>
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <div class="notification-actions">
                                        <button type="submit" name="delete_user_message" class="btn btn-delete">Delete</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <p><small><?php echo $msg['created_at']; ?></small></p>
                        </div>
                    <?php endwhile; ?>
                    <form method="POST">
                        <button type="submit" name="delete_all_user_messages" class="btn btn-delete-all">Delete All User Messages</button>
                    </form>
                <?php else: ?>
                    <p>No messages from users yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>