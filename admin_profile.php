<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Update profile
if (isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    $sql = "UPDATE users SET name = '$name', email = '$email', password = '$password' WHERE email = '{$_SESSION['email']}'";
    if ($conn->query($sql) === TRUE) {
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        echo "<script>alert('Profile updated successfully!');</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// Fetch current admin details
$sql = "SELECT name, email, password FROM users WHERE email = '{$_SESSION['email']}'";
$result = $conn->query($sql);
$admin = $result->fetch_assoc();

// Fetch unread notification count
$admin_id = $conn->query("SELECT id FROM users WHERE email = '{$_SESSION['email']}'")->fetch_assoc()['id'];
$unread_count = $conn->query("SELECT COUNT(*) as count FROM staff_to_admin_messages WHERE admin_id = '$admin_id' AND is_read = 0")->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile — Learning Spark</title>
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
                <a href="admin_profile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="admin_notifications.php">
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
        <div style="max-width:640px;margin:0 auto">

            <!-- Profile Hero -->
            <div class="glass-card" style="padding:0;overflow:hidden">
                <div style="background:var(--gradient-hero);height:100px;position:relative"></div>
                <div class="profile-hero" style="margin-top:-50px;padding-top:0">
                    <div class="profile-avatar"><?php echo strtoupper(substr($admin['name'], 0, 1)); ?></div>
                    <div class="profile-name"><?php echo htmlspecialchars($admin['name']); ?></div>
                    <span class="profile-role"><i class="fas fa-shield-alt"></i> Administrator</span>
                </div>
            </div>

            <!-- Personal Info Card -->
            <form method="POST">
                <div class="glass-card" style="animation-delay:.1s">
                    <div class="profile-section-title"><i class="fas fa-id-card"
                            style="margin-right:8px;color:var(--primary)"></i> Personal Information</div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" name="name" id="name" class="form-input"
                                value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" class="form-input"
                                value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Security Card -->
                <div class="glass-card" style="animation-delay:.15s">
                    <div class="profile-section-title"><i class="fas fa-lock"
                            style="margin-right:8px;color:var(--amber)"></i> Security</div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="form-input-icon-wrapper password-wrapper">
                            <i class="fas fa-key"></i>
                            <input type="password" name="password" id="password" class="form-input"
                                value="<?php echo htmlspecialchars($admin['password']); ?>" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <button type="submit" name="update_profile" class="btn btn-primary btn-lg"
                    style="width:100%;justify-content:center;margin-bottom:40px">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>

        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>