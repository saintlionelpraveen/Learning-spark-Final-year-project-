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

// Fetch current profile details
$stmt = $conn->prepare("SELECT name, email, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Count unread staff replies for notification bell
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_to_staff_messages WHERE user_id = ? AND staff_reply IS NOT NULL AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
$stmt->close();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);

    // Handle photo upload (cropped image from hidden input)
    $profile_photo = $profile['profile_photo'];
    if (!empty($_POST['cropped_image'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . "user_" . $user_id . "_" . time() . ".png"; // Unique filename
        $image_data = $_POST['cropped_image'];
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);
        if (file_put_contents($target_file, $decoded_image) !== false) {
            $profile_photo = $target_file;
        } else {
            $error = "Error saving cropped photo.";
        }
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        // Fallback for non-cropped upload (optional)
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["profile_photo"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed_types) && $_FILES["profile_photo"]["size"] <= 5000000) {
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                $profile_photo = $target_file;
            } else {
                $error = "Error uploading photo.";
            }
        } else {
            $error = "Invalid file type or size exceeds 5MB.";
        }
    }

    // Update database (password only if provided)
    if (!empty($_POST['password'])) {
        $password = $conn->real_escape_string($_POST['password']);
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, profile_photo = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $password, $profile_photo, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, profile_photo = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $profile_photo, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['name'] = $name; // Update session name
        $success = "Profile updated successfully!";
        $profile['name'] = $name;
        $profile['email'] = $email;
        $profile['profile_photo'] = $profile_photo;
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Cropper.js CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
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

        .profile-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .profile-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .profile-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
        }

        .profile-header .title-icon {
            position: relative;
            font-size: 1.5em;
            color: var(--primary);
        }

        .profile-header .title-icon .fa-book {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1em;
            color: var(--secondary);
        }

        .profile-section {
            margin-bottom: 40px;
        }

        .profile-section h3 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .profile-details p {
            margin-bottom: 15px;
            color: var(--text);
        }

        .profile-details img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
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

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
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

        .error {
            color: var(--error);
            font-size: 0.9em;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            color: var(--success);
            font-size: 0.9em;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Crop Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px var(--shadow);
            width: 500px;
            max-width: 90%;
            text-align: center;
        }

        .modal-content img {
            max-width: 100%;
            margin-bottom: 20px;
        }

        .modal-content .btn {
            width: auto;
            padding: 10px 20px;
            margin: 0 10px;
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

            .profile-card {
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
        <div class="user-photo">
            <?php if ($profile['profile_photo']): ?>
                <img src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile Photo">
            <?php else: ?>
                <i class="fas fa-user-circle" style="font-size: 40px; color: var(--primary);"></i>
            <?php endif; ?>
        </div>
        <a href="user_profile.php" class="active"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="user_messages.php" class="notification-bell"><i class="fas fa-bell"></i><span>Messages</span></a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="profile-card">
            <div class="profile-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>

            <!-- Profile Details -->
            <div class="profile-section">
                <h3>Your Profile</h3>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <div class="profile-details">
                    <?php if ($profile['profile_photo']): ?>
                        <img src="<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile Photo">
                    <?php else: ?>
                        <i class="fas fa-user-circle" style="font-size: 150px; color: var(--primary); margin-bottom: 20px;"></i>
                    <?php endif; ?>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($profile['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="profile-section">
                <h3>Edit Profile</h3>
                <form method="POST" enctype="multipart/form-data" id="profile-form">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label for="profile_photo">Profile Photo</label>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                        <input type="hidden" id="cropped_image" name="cropped_image">
                    </div>
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Crop Modal -->
    <div id="cropModal" class="modal">
        <div class="modal-content">
            <h3>Crop Your Photo</h3>
            <img id="imageToCrop" src="" alt="Image to crop">
            <button id="cropButton" class="btn">Crop & Save</button>
            <button id="cancelCrop" class="btn" style="background: #e2e8f0; color: var(--text);">Cancel</button>
        </div>
    </div>

    <!-- Cropper.js Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        let cropper;
        const imageInput = document.getElementById('profile_photo');
        const cropModal = document.getElementById('cropModal');
        const imageToCrop = document.getElementById('imageToCrop');
        const cropButton = document.getElementById('cropButton');
        const cancelCrop = document.getElementById('cancelCrop');
        const croppedImageInput = document.getElementById('cropped_image');
        const form = document.getElementById('profile-form');

        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    imageToCrop.src = event.target.result;
                    cropModal.style.display = 'flex';

                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1, // Square crop
                        viewMode: 1,
                        autoCropArea: 0.8,
                        responsive: true,
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        cropButton.addEventListener('click', function() {
            const canvas = cropper.getCroppedCanvas({
                width: 150, // Match profile photo display size
                height: 150,
            });
            const croppedImageData = canvas.toDataURL('image/png');
            croppedImageInput.value = croppedImageData;
            cropModal.style.display = 'none';
            cropper.destroy();
        });

        cancelCrop.addEventListener('click', function() {
            cropModal.style.display = 'none';
            cropper.destroy();
            imageInput.value = ''; // Reset file input
        });

        form.addEventListener('submit', function(e) {
            if (!croppedImageInput.value && imageInput.files.length > 0) {
                e.preventDefault();
                alert('Please crop your photo before submitting.');
            }
        });
    </script>
</body>
</html>