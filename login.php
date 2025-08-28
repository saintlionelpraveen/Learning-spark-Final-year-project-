<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle login
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    $sql = "SELECT * FROM users WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: admin_dashboard.php");
        } elseif ($user['role'] === 'staff') {
            header("Location: staff_dashboard.php?staff=" . urlencode($user['name']));
        } else { // 'user'
            header("Location: user_dashboard.php?id=" . $user['id']);
        }
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
            --tertiary: #8b5cf6;
            --accent: #f59e0b;
            --success: #10b981;
            --background: #f0f4f8;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --shadow: rgba(0, 0, 0, 0.1);
            --gradient: linear-gradient(135deg, #4f46e5, #7c3aed);
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
            display: flex;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            filter: blur(30px);
            animation: float 20s infinite alternate ease-in-out;
        }

        .circle:nth-child(1) {
            width: 500px;
            height: 500px;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .circle:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -100px;
            left: -100px;
            animation-delay: -5s;
            background: linear-gradient(45deg, rgba(245, 158, 11, 0.1), rgba(99, 102, 241, 0.1));
        }

        .circle:nth-child(3) {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 50%;
            animation-delay: -10s;
            background: linear-gradient(45deg, rgba(139, 92, 246, 0.1), rgba(245, 158, 11, 0.1));
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(50px, 50px) scale(1.2);
            }
            100% {
                transform: translate(0, 0) scale(1);
            }
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 80px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 5px 0 20px rgba(99, 102, 241, 0.1);
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow: hidden;
            border-right: 1px solid rgba(99, 102, 241, 0.1);
        }

        .sidebar:hover {
            width: 260px;
        }

        .sidebar .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(99, 102, 241, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .sidebar .logo i {
            font-size: 2em;
            color: var(--primary);
            filter: drop-shadow(0 2px 5px rgba(99, 102, 241, 0.3));
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .sidebar:hover .logo i {
            transform: rotate(360deg) scale(1.1);
        }

        .sidebar .logo span {
            font-size: 1.4em;
            font-weight: 700;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.4s ease;
            transform: translateX(-20px);
        }

        .sidebar:hover .logo span {
            opacity: 1;
            transform: translateX(0);
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 10px 15px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .sidebar a i {
            font-size: 1.5em;
            min-width: 30px;
            transition: transform 0.3s ease;
        }

        .sidebar a span {
            opacity: 0;
            margin-left: 15px;
            font-weight: 500;
            transition: opacity 0.2s ease, transform 0.3s ease;
            transform: translateX(-10px);
        }

        .sidebar:hover a span {
            opacity: 1;
            transform: translateX(0);
        }

        .sidebar a:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .sidebar a:hover i {
            transform: scale(1.1);
        }

        .sidebar a.active {
            background: var(--gradient);
            color: white;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .sidebar a.active i {
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 80px;
            padding: 30px;
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar:hover ~ .main-content {
            margin-left: 260px;
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            height: min-content;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(99, 102, 241, 0.15);
            background: var(--card-bg);
            animation: fadeIn 0.8s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-left {
            flex: 1;
            padding: 40px;
            background: var(--gradient);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 6s infinite linear;
            z-index: 1;
        }

        @keyframes shine {
            0% {
                transform: rotate(45deg) translateY(-100%);
            }
            100% {
                transform: rotate(45deg) translateY(100%);
            }
        }

        .login-left-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-logo .logo-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .brand-logo .logo-icon i {
            font-size: 1.8em;
            color: var(--primary);
        }

        .brand-logo .logo-text {
            font-size: 1.5em;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .welcome-text {
            margin-bottom: 30px;
        }

        .welcome-text h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .welcome-text p {
            font-size: 1.1em;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .feature-list {
            margin-top: 30px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .feature-item i {
            margin-right: 10px;
            font-size: 1.2em;
            color: var(--accent);
        }

        .login-right {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .login-header h2 {
            font-size: 2em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        .login-header p {
            color: var(--text-light);
            font-size: 1.1em;
        }

        .error {
            text-align: center;
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
            font-size: 0.95em;
            margin-bottom: 25px;
            font-weight: 500;
            padding: 12px;
            border-radius: 10px;
            border-left: 4px solid var(--error);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 1em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        .form-group .input-container {
            position: relative;
        }

        .form-group .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1em;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            outline: none;
        }

        .form-group input:focus + .input-icon {
            color: var(--primary);
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 25px;
        }

        .forgot-password a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95em;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 1.1em
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-fire"></i>
            <span>LEARNING SPARK</span>
        </div>
        <a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i><span>Login</span></a>
        <a href="register.php"><i class="fas fa-user-plus"></i><span>Register</span></a>
    </div>

    <div class="main-content">
        <div class="login-card">
            <div class="login-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" name="login" class="btn">Login Now</button>
            </form>
            <div class="link">
                <p>New to the portal? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>