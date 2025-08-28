<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Ensure email is set in session
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    die("Session email not set or empty.");
}

// Database connection
$conn = new mysqli("localhost", "root", "", "learning");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch stats
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND pending_approval = 0");
$total_users = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND pending_approval = 0");
$total_staff = $result->fetch_assoc()['count'];

// Fetch all users and staff (approved only)
$users = $conn->query("SELECT id, name, email, role FROM users WHERE role IN ('user', 'staff') AND pending_approval = 0 ORDER BY role, name");

// Fetch pending staff requests
$pending_staff = $conn->query("SELECT id, name, email FROM users WHERE role = 'staff' AND pending_approval = 1 ORDER BY created_at");

// Fetch admin ID
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$admin_id = $row['id'];
$stmt->close();

// Fetch unread notification count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_to_admin_messages WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];
$stmt->close();

// Handle approve staff
if (isset($_POST['approve_staff'])) {
    $id = intval($_POST['staff_id']);
    $stmt = $conn->prepare("UPDATE users SET pending_approval = 0 WHERE id = ? AND role = 'staff'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Staff approved successfully!'); window.location.href='admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error approving staff: " . $conn->error . "'); window.location.href='admin_dashboard.php';</script>";
    }
    $stmt->close();
}

// Handle reject staff
if (isset($_POST['reject_staff'])) {
    $id = intval($_POST['staff_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'staff' AND pending_approval = 1");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Staff request rejected and removed!'); window.location.href='admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error rejecting staff: " . $conn->error . "'); window.location.href='admin_dashboard.php';</script>";
    }
    $stmt->close();
}

// Handle delete
if (isset($_POST['confirm_delete'])) {
    $id = intval($_POST['delete_id']);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT role, profile_photo FROM users WHERE id = ? AND role != 'admin' AND pending_approval = 0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            if ($user['role'] === 'staff') {
                // Delete staff content and associated files
                $stmt = $conn->prepare("SELECT file_path, image_path FROM staff_content WHERE staff_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $content_result = $stmt->get_result();
                while ($content = $content_result->fetch_assoc()) {
                    if ($content['file_path'] && file_exists($content['file_path'])) {
                        unlink($content['file_path']);
                    }
                    if ($content['image_path'] && file_exists($content['image_path'])) {
                        unlink($content['image_path']);
                    }
                }
                $stmt->close();

                // Delete staff content
                $stmt = $conn->prepare("DELETE FROM staff_content WHERE staff_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                // Delete staff feedback
                $stmt = $conn->prepare("DELETE FROM staff_feedback WHERE staff_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                // Delete staff ratings (both as rated and rater)
                $stmt = $conn->prepare("DELETE FROM staff_ratings WHERE staff_id = ? OR user_id = ?");
                $stmt->bind_param("ii", $id, $id);
                $stmt->execute();
                $stmt->close();

                // Delete staff-to-admin messages
                $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE staff_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                if ($user['profile_photo'] && file_exists($user['profile_photo'])) {
                    unlink($user['profile_photo']);
                }
            }

            // Delete blog ratings
            $stmt = $conn->prepare("DELETE FROM blog_ratings WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete user-to-staff messages
            $stmt = $conn->prepare("DELETE FROM user_to_staff_messages WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo "<script>alert('User and related data deleted successfully!'); window.location.href='admin_dashboard.php';</script>";
        } else {
            throw new Exception("User not found or is an admin.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error deleting user: " . addslashes($e->getMessage()) . "'); window.location.href='admin_dashboard.php';</script>";
    }
}

// Handle update
if (isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ? AND role != 'admin' AND pending_approval = 0");
    $stmt->bind_param("sssi", $name, $email, $role, $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('User updated successfully!'); window.location.href='admin_dashboard.php';</script>";
}

// Handle add new user/staff
if (isset($_POST['add_user'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $role = $conn->real_escape_string($_POST['role']);

    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "<script>alert('Email already exists!');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, pending_approval) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('New user added successfully!'); window.location.href='admin_dashboard.php';</script>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Learning Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Same styles as before with additions for pending staff */
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
            --success: #10b981;
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
        }

        .sidebar .logo {
            font-size: 1.8em;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .sidebar:hover .logo i {
            transform: rotate(360deg);
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

        .main-content {
            margin-left: 70px;
            padding: 40px;
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
            margin-bottom: 40px;
        }

        .dashboard-header h1 {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: var(--text-light);
            font-size: 1.1em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-box {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px var(--shadow);
        }

        .stat-box i {
            font-size: 2em;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .stat-box p {
            color: var(--text-light);
            font-size: 1em;
            margin-bottom: 5px;
        }

        .stat-box span {
            font-size: 1.8em;
            font-weight: 600;
            color: var(--text);
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .user-table th {
            text-align: left;
            padding: 15px;
            font-weight: 600;
            color: var(--text);
            border-bottom: 2px solid #e2e8f0;
        }

        .user-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .user-table tr:hover {
            background: #f1f5f9;
        }

        .user-table input,
        .user-table select {
            width: 100%;
            padding: 8px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9em;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .user-table input:focus,
        .user-table select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .save-btn {
            background: var(--gradient);
            color: white;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .cancel-btn {
            background: #e2e8f0;
            color: var(--text);
            margin-left: 10px;
        }

        .cancel-btn:hover {
            background: #d1d5db;
        }

        .delete-btn {
            background: var(--error);
            color: white;
            margin-left: 10px;
        }

        .delete-btn:hover {
            background: #dc2626;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }

        .approve-btn {
            background: var(--success);
            color: white;
            margin-left: 10px;
        }

        .approve-btn:hover {
            background: #059669;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        .add-btn {
            display: inline-block;
            background: var(--gradient);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

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
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px var(--shadow);
            width: 400px;
            text-align: center;
        }

        .modal-content h2 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
        }

        .modal-content .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .modal-content label {
            display: block;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 5px;
        }

        .modal-content input,
        .modal-content select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1em;
        }

        .modal-content input:focus,
        .modal-content select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        canvas {
            max-width: 100%;
            margin: 20px 0;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <a href="admin_profile.php"><i class="fas fa-user"></i><span>Profile</span></a>
        <a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="admin_notifications.php" class="notification-bell"><i class="fas fa-bell"></i><span>Notifications</span></a>
        <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h1>Admin Dashboard</h1>
                <p>Overview of users and staff</p>
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <i class="fas fa-users"></i>
                    <p>Total Users</p>
                    <span><?php echo $total_users; ?></span>
                </div>
                <div class="stat-box">
                    <i class="fas fa-user-tie"></i>
                    <p>Total Staff</p>
                    <span><?php echo $total_staff; ?></span>
                </div>
            </div>
            <canvas id="statsChart"></canvas>
        </div>

        <!-- Pending Staff Requests -->
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h1>Pending Staff Requests</h1>
            </div>
            <?php if ($pending_staff->num_rows > 0): ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($staff = $pending_staff->fetch_assoc()): ?>
                            <tr data-id="<?php echo $staff['id']; ?>">
                                <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" name="approve_staff" class="btn approve-btn">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                        <button type="submit" name="reject_staff" class="btn delete-btn">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending staff requests.</p>
            <?php endif; ?>
        </div>

        <!-- Approved Users & Staff -->
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h1>Manage Users & Staff</h1>
            </div>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr data-id="<?php echo $user['id']; ?>">
                            <td><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" data-original="<?php echo htmlspecialchars($user['name']); ?>" required></td>
                            <td><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" data-original="<?php echo htmlspecialchars($user['email']); ?>" required></td>
                            <td>
                                <select name="role" data-original="<?php echo $user['role']; ?>" required>
                                    <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                </select>
                            </td>
                            <td>
                                <button class="btn save-btn" onclick="saveUser(<?php echo $user['id']; ?>)">Save</button>
                                <button class="btn cancel-btn" onclick="cancelEdit(this)">Cancel</button>
                                <button class="btn delete-btn" onclick="showDeleteModal(<?php echo $user['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <a href="#" class="add-btn" onclick="showAddModal()">Add New User/Staff</a>
        </div>

        <!-- Add User Modal -->
        <div id="addModal" class="modal">
            <div class="modal-content">
                <h2>Add New User/Staff</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn save-btn">Add User</button>
                    <button type="button" onclick="hideAddModal()" class="btn cancel-btn">Cancel</button>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <h2>Confirm Deletion</h2>
                <p>Are you sure you want to delete this user?</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="delete_id" id="deleteId">
                    <button type="submit" name="confirm_delete" class="btn delete-btn">Yes, Delete</button>
                    <button type="button" onclick="hideDeleteModal()" class="btn cancel-btn">No, Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('statsChart').getContext('2d');
        const statsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Users', 'Total Staff'],
                datasets: [{
                    label: 'User Statistics',
                    data: [<?php echo $total_users; ?>, <?php echo $total_staff; ?>],
                    backgroundColor: ['#6366f1', '#f59e0b'],
                    borderColor: ['#4f46e5', '#d97706'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        function hideAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        function showDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function saveUser(id) {
            const row = document.querySelector(`tr[data-id='${id}']`);
            const name = row.querySelector('input[name="name"]').value;
            const email = row.querySelector('input[name="email"]').value;
            const role = row.querySelector('select[name="role"]').value;

            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="name" value="${name}">
                <input type="hidden" name="email" value="${email}">
                <input type="hidden" name="role" value="${role}">
                <input type="hidden" name="update_user" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function cancelEdit(button) {
            const row = button.closest('tr');
            const fields = row.querySelectorAll('[name]');
            fields.forEach(field => {
                field.value = field.getAttribute('data-original');
            });
        }
    </script>
</body>
</html>