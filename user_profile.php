<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

include 'config.php';

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $new_name = $conn->real_escape_string($_POST['name']);
    $new_email = $conn->real_escape_string($_POST['email']);

    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = "uploads/profiles/";
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_ext;
        $target_path = $upload_dir . $new_filename;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_path)) {
            // Delete old photo if exists
            if ($user['profile_photo'] && file_exists($user['profile_photo'])) {
                unlink($user['profile_photo']);
            }
            $conn->query("UPDATE users SET profile_photo = '$target_path' WHERE id = $user_id");
        }
    }

    $conn->query("UPDATE users SET name = '$new_name', email = '$new_email' WHERE id = $user_id");
    $_SESSION['name'] = $new_name;
    $_SESSION['email'] = $new_email;
    echo "<script>alert('Profile updated successfully!'); window.location.href='user_profile.php?id=$user_id';</script>";
}

// Unread replies count for nav badge
$result = $conn->query("SELECT COUNT(*) as c FROM user_to_staff_messages WHERE user_id = $user_id AND staff_reply IS NOT NULL AND is_read = 0");
$unread_replies = $result ? $result->fetch_assoc()['c'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Learning Spark</title>
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
                <a href="user_profile.php?id=<?php echo $user_id; ?>" class="active"><i class="fas fa-user-circle"></i>
                    Profile</a>
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
        <div class="page-header">
            <h1><i class="fas fa-user-circle" style="color:var(--primary);margin-right:8px"></i> My Profile</h1>
            <p>Update your personal information and profile photo</p>
        </div>

        <div class="grid-2">
            <!-- Profile Card -->
            <div class="glass-card" style="animation-delay:.1s">
                <div class="profile-hero">
                    <div class="profile-avatar">
                        <?php if ($user['profile_photo'] && file_exists($user['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile"
                                id="previewPhoto">
                        <?php else: ?>
                            <span id="previewInitial"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <span class="profile-role"><i class="fas fa-user"></i> User</span>
                    <div style="margin-top:12px;font-size:.82rem;color:var(--text-muted)">
                        <i class="fas fa-envelope" style="margin-right:4px"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <div style="margin-top:4px;font-size:.78rem;color:var(--text-muted)">
                        <i class="fas fa-calendar" style="margin-right:4px"></i> Member since
                        <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="glass-card" style="animation-delay:.2s">
                <div class="section-title"><i class="fas fa-edit"></i> Edit Profile</div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Full Name</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" name="name" class="form-input"
                                value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-input"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Profile Photo</label>
                        <input type="file" name="profile_photo" class="form-input" accept="image/*"
                            onchange="previewImage(this)">
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Recommended: Square image,
                            minimum 200x200px</div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary btn-lg"
                        style="width:100%;justify-content:center">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const avatar = document.querySelector('.profile-avatar');
                    avatar.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>