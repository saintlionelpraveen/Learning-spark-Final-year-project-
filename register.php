<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle registration
if (isset($_POST['register'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $role = $conn->real_escape_string($_POST['role']);
    $pending_approval = ($role === 'staff') ? 1 : 0; // Set pending_approval for staff

    // Check if email already exists
    $check_email = "SELECT email FROM users WHERE email = '$email'";
    $result = $conn->query($check_email);
    if ($result->num_rows > 0) {
        echo "<script>alert('Email already registered!');</script>";
    } elseif ($role === 'admin') {
        echo "<script>alert('Admin cannot be registered here!');</script>";
    } else {
        // Insert new user/staff into the database
        $sql = "INSERT INTO users (name, email, password, role, pending_approval) 
                VALUES ('$name', '$email', '$password', '$role', $pending_approval)";
        if ($conn->query($sql) === TRUE) {
            if ($role === 'staff') {
                echo "<script>alert('Registration successful! Your staff role is pending admin approval. You’ll be notified once approved.'); window.location.href='login.php';</script>";
            } else {
                echo "<script>alert('Registration successful! Please proceed to login.'); window.location.href='login.php';</script>";
            }
        } else {
            echo "<script>alert('Error: " . $conn->error . "');</script>";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Same styles as before, no changes needed here */
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
            display: flex;
        }

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

        .main-content {
            margin-left: 70px;
            padding: 40px;
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: margin-left 0.3s ease;
        }

        .sidebar:hover ~ .main-content {
            margin-left: 250px;
        }

        .register-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--gradient);
        }

        .register-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .register-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
        }

        .register-header .title-icon {
            position: relative;
            font-size: 1.5em;
            color: var(--primary);
        }

        .register-header .title-icon .fa-book {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1em;
            color: var(--secondary);
        }

        .register-header p {
            color: var(--text-light);
            font-size: 1.1em;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
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

        .link {
            text-align: center;
            margin-top: 20px;
        }

        .link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .link a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

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

            .register-card {
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
        <a href="login.php"><i class="fas fa-sign-in-alt"></i><span>Login</span></a>
        <a href="register.php" class="active"><i class="fas fa-user-plus"></i><span>Register</span></a>
    </div>

    <div class="main-content">
        <div class="register-card">
            <div class="register-header">
                <span class="title-icon">
                    <i class="fas fa-bolt"></i>
                    <i class="fas fa-book"></i>
                </span>
                <h1>LEARNING SPARK</h1>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="user">User</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <button type="submit" name="register" class="btn">Register Now</button>
            </form>
            <div class="link">
                <p>Already a member? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
</body>
</html>