<?php
session_start();

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$success = '';
$error = '';

if (isset($_POST['register'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    // Check duplicate email
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "This email is already registered.";
    } else {
        // Register as pending — admin will assign role later
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, pending_approval) VALUES (?, ?, ?, 'user', 1)");
        $stmt->bind_param("sss", $name, $email, $password);
        if ($stmt->execute()) {
            $success = "Registration successful! Your account is pending admin approval. You'll be able to log in once approved.";
        } else {
            $error = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
    $check->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="portal_styles.css">
</head>

<body class="auth-page">
    <!-- Animated Background -->
    <div class="auth-bg-circle"></div>
    <div class="auth-bg-circle"></div>
    <div class="auth-bg-circle"></div>

    <div class="auth-container">
        <!-- Left Hero Panel -->
        <div class="auth-hero">
            <div class="hero-content">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:28px">
                    <div
                        style="width:48px;height:48px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-graduation-cap" style="font-size:1.5rem;color:#6366f1"></i>
                    </div>
                    <span style="font-size:1.2rem;font-weight:700;letter-spacing:.5px">LEARNING SPARK</span>
                </div>
                <h1>Join the Learning<br>Community</h1>
                <p>Create your account and start your journey. An admin will review your registration and assign your
                    role.</p>
                <div class="feature"><i class="fas fa-check-circle"></i> Free access to all learning content</div>
                <div class="feature"><i class="fas fa-check-circle"></i> Interactive courses with videos & PDFs</div>
                <div class="feature"><i class="fas fa-check-circle"></i> Direct messaging with staff members</div>
                <div class="feature"><i class="fas fa-check-circle"></i> Personalized learning dashboard</div>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="auth-form-panel">
            <h2>Create Account</h2>
            <p class="subtitle">Fill in your details to get started</p>

            <?php if ($error): ?>
                <div class="auth-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-info"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" name="name" id="name" class="form-input" placeholder="Enter your full name"
                                required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" class="form-input" placeholder="Enter your email"
                                required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="form-input-icon-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="form-input"
                                placeholder="Create a password" required>
                        </div>
                    </div>
                    <button type="submit" name="register" class="btn btn-primary btn-lg"
                        style="width:100%;justify-content:center">
                        <i class="fas fa-user-plus"></i> Register Now
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
</body>

</html>