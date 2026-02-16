<?php
session_start();

$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$error = '';

if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    $sql = "SELECT * FROM users WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check if account is pending approval
        if ($user['pending_approval'] == 1) {
            $error = "Your account is pending admin approval. Please wait until an administrator reviews and approves your registration.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];

            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user['role'] === 'staff') {
                header("Location: staff_dashboard.php?staff=" . urlencode($user['name']));
            } else {
                header("Location: user_dashboard.php?id=" . $user['id']);
            }
            exit();
        }
    } else {
        $error = "Invalid email or password.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Learning Spark</title>
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
                <h1>Welcome Back to<br>Learning Spark</h1>
                <p>Sign in to access your personalized dashboard, courses, and connect with educators.</p>
                <div class="feature"><i class="fas fa-bolt"></i> Interactive learning modules</div>
                <div class="feature"><i class="fas fa-video"></i> HD video content & PDFs</div>
                <div class="feature"><i class="fas fa-comments"></i> Real-time messaging</div>
                <div class="feature"><i class="fas fa-chart-line"></i> Track your progress</div>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="auth-form-panel">
            <h2>Sign In</h2>
            <p class="subtitle">Enter your credentials to continue</p>

            <?php if ($error): ?>
                <div class="auth-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
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
                            placeholder="Enter your password" required>
                    </div>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-lg"
                    style="width:100%;justify-content:center">
                    <i class="fas fa-sign-in-alt"></i> Login Now
                </button>
            </form>

            <div class="auth-link">
                New to Learning Spark? <a href="register.php">Create an account</a>
            </div>
        </div>
    </div>
</body>

</html>