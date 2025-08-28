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

// Fetch active staff
$stmt = $conn->prepare("SELECT DISTINCT u.id, u.name, u.email, u.profile_photo 
                        FROM users u 
                        JOIN staff_content sc ON u.id = sc.staff_id 
                        WHERE u.role = 'staff'");
if ($stmt === false) die("Prepare failed for active_staff: " . $conn->error);
$stmt->execute();
$staff_result = $stmt->get_result();
$stmt->close();

// Count unread staff replies for notification bell
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_to_staff_messages WHERE user_id = ? AND staff_reply IS NOT NULL AND is_read = 0");
if ($stmt === false) die("Prepare failed for unread_count: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();

// Fetch profile details
$stmt = $conn->prepare("SELECT name, email, profile_photo FROM users WHERE id = ?");
if ($stmt === false) die("Prepare failed for users: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user_name); ?>'s Dashboard - Learning Spark</title>
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

        .dashboard-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .dashboard-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .dashboard-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
        }

        .dashboard-header .title-icon {
            position: relative;
            font-size: 1.5em;
            color: var(--primary);
        }

        .dashboard-header .title-icon .fa-book {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1em;
            color: var(--secondary);
        }

        .dashboard-section {
            margin-bottom: 40px;
        }

        .dashboard-section h3 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .staff-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .staff-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            width: 200px;
            border-left: 4px solid var(--primary);
        }

        .staff-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .staff-card i.fa-user-circle {
            font-size: 100px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .staff-card p {
            margin-bottom: 10px;
            color: var(--text);
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

            .dashboard-card {
                padding: 20px;
            }

            .staff-card {
                width: 100%;
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
        <a href="user_profile.php"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="user_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="user_messages.php" class="notification-bell"><i class="fas fa-bell"></i><span>Messages</span></a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>

            <!-- Profile Section -->
            <div class="dashboard-section">
                <h3>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h3>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                <?php if ($profile['profile_photo']): ?>
                    <img src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile Photo" style="max-width: 150px; border-radius: 8px; margin-top: 10px;">
                <?php endif; ?>
            </div>

            <!-- Active Staff Section -->
            <div class="dashboard-section">
                <h3>Active Staff in Your Project</h3>
                <div class="staff-list">
                    <?php while ($staff = $staff_result->fetch_assoc()): ?>
                        <div class="staff-card">
                            <?php if ($staff['profile_photo']): ?>
                                <img src="<?php echo htmlspecialchars($staff['profile_photo']); ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <p><strong><?php echo htmlspecialchars($staff['name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($staff['email']); ?></p>
                            <a href="staff_content.php?staff_id=<?php echo $staff['id']; ?>" class="action-link">View Content</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>