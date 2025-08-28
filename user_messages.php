<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mark message as read
if (isset($_GET['read'])) {
    $id = $conn->real_escape_string($_GET['read']);
    $stmt = $conn->prepare("UPDATE user_to_staff_messages SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: user_messages.php");
    exit();
}

// Fetch user-to-staff messages
$stmt = $conn->prepare("SELECT m.*, u.name AS staff_name FROM user_to_staff_messages m JOIN users u ON m.staff_id = u.id WHERE m.user_id = ? ORDER BY m.created_at DESC");
if ($stmt === false) die("Prepare failed for user_to_staff_messages: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

// Count unread staff replies
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_to_staff_messages WHERE user_id = ? AND staff_reply IS NOT NULL AND is_read = 0");
if ($stmt === false) die("Prepare failed for unread_count: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();

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

        .messages-section h3 {
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

        .action-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .action-link:hover {
            color: var(--secondary);
            text-decoration: underline;
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
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-fire"></i>
            <span>LEARNING SPARK</span>
        </div>
        <a href="user_profile.php"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="user_messages.php" class="active notification-bell"><i class="fas fa-bell"></i><span>Messages</span></a>
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

            <!-- Messages Section -->
            <div class="messages-section">
                <h3>Your Messages</h3>
                <?php if ($messages->num_rows > 0): ?>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-item <?php echo !$msg['is_read'] && $msg['staff_reply'] ? 'unread' : ''; ?>">
                            <p><strong>To <?php echo htmlspecialchars($msg['staff_name']); ?>:</strong> <?php echo htmlspecialchars($msg['message']); ?></p>
                            <p class="timestamp">Sent on: <?php echo $msg['created_at']; ?></p>
                            <?php if ($msg['staff_reply']): ?>
                                <p><strong><?php echo htmlspecialchars($msg['staff_name']); ?> replied:</strong> <?php echo htmlspecialchars($msg['staff_reply']); ?></p>
                                <p class="timestamp">Replied on: <?php echo $msg['replied_at']; ?></p>
                                <?php if (!$msg['is_read']): ?>
                                    <a href="?read=<?php echo $msg['id']; ?>" class="action-link">Mark as Read</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No messages sent yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>