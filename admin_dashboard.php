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
include 'config.php';

// Fetch stats
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND pending_approval = 0");
$total_users = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND pending_approval = 0");
$total_staff = $result->fetch_assoc()['count'];

// Fetch all users and staff (approved only)
$users = $conn->query("SELECT id, name, email, role FROM users WHERE role IN ('user', 'staff') AND pending_approval = 0 ORDER BY role, name");

// Fetch ALL pending registration requests (any role)
$pending_registrations = $conn->query("SELECT id, name, email, created_at FROM users WHERE pending_approval = 1 AND role != 'admin' ORDER BY created_at DESC");
$pending_count = $pending_registrations->num_rows;

// Fetch admin ID and name
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$admin_id = $row['id'];
$admin_name = $row['name'];
$stmt->close();

// Fetch unread notification count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_to_admin_messages WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];
$stmt->close();

// Handle approve registration — admin assigns role and approves
if (isset($_POST['approve_registration'])) {
    $id = intval($_POST['reg_id']);
    $assigned_role = $_POST['assigned_role'];
    // Only allow user or staff roles
    if (!in_array($assigned_role, ['user', 'staff'])) {
        $assigned_role = 'user';
    }
    $stmt = $conn->prepare("UPDATE users SET role = ?, pending_approval = 0 WHERE id = ? AND pending_approval = 1");
    $stmt->bind_param("si", $assigned_role, $id);
    if ($stmt->execute()) {
        echo "<script>alert('Registration approved as " . ucfirst($assigned_role) . "!'); window.location.href='admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error approving registration.'); window.location.href='admin_dashboard.php';</script>";
    }
    $stmt->close();
}

// Handle reject registration
if (isset($_POST['reject_registration'])) {
    $id = intval($_POST['reg_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND pending_approval = 1");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Registration rejected and removed!'); window.location.href='admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error rejecting registration.'); window.location.href='admin_dashboard.php';</script>";
    }
    $stmt->close();
}

// =============================================
// PRODUCTION-READY DELETE — clears ALL FK refs
// =============================================
if (isset($_POST['confirm_delete'])) {
    $id = intval($_POST['delete_id']);
    $conn->begin_transaction();
    try {
        // Verify user exists and is not an admin
        $stmt = $conn->prepare("SELECT role, profile_photo FROM users WHERE id = ? AND role != 'admin' AND pending_approval = 0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new Exception("User not found or is an admin.");
        }

        // 1. staff_ratings (NO CASCADE — must delete manually, both columns reference users.id)
        $stmt = $conn->prepare("DELETE FROM staff_ratings WHERE user_id = ? OR staff_id = ?");
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $stmt->close();

        // 2. blog_ratings (user_id has NO CASCADE)
        $stmt = $conn->prepare("DELETE FROM blog_ratings WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // 3. content_ratings (CASCADE exists, but clean up explicitly for safety)
        $stmt = $conn->prepare("DELETE FROM content_ratings WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // 4. staff_feedback (both user_id and staff_id reference users)
        $stmt = $conn->prepare("DELETE FROM staff_feedback WHERE user_id = ? OR staff_id = ?");
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $stmt->close();

        // 5. user_ratings (both user_id and staff_id reference users)
        $stmt = $conn->prepare("DELETE FROM user_ratings WHERE user_id = ? OR staff_id = ?");
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $stmt->close();

        // 6. user_preferences
        $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Also clear favorite_staff_id references
        $stmt = $conn->prepare("UPDATE user_preferences SET favorite_staff_id = NULL WHERE favorite_staff_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // 7. user_to_staff_messages (both columns reference users)
        $stmt = $conn->prepare("DELETE FROM user_to_staff_messages WHERE user_id = ? OR staff_id = ?");
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $stmt->close();

        // 8. staff_to_admin_messages
        $stmt = $conn->prepare("DELETE FROM staff_to_admin_messages WHERE staff_id = ? OR admin_id = ?");
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
        $stmt->close();

        // 9. If staff, delete content files and blog_ratings referencing their content
        if ($user['role'] === 'staff') {
            // Get content IDs for this staff to clean blog_ratings on content_id
            $stmt = $conn->prepare("SELECT content_id, file_path, image_path FROM staff_content WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $content_result = $stmt->get_result();
            while ($content = $content_result->fetch_assoc()) {
                // Delete blog_ratings that reference this content
                $cid = $content['content_id'];
                $conn->query("DELETE FROM blog_ratings WHERE content_id = $cid");
                $conn->query("DELETE FROM content_ratings WHERE content_id = $cid");

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

            // Delete staff blogs
            $stmt = $conn->prepare("DELETE FROM staff_blogs WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        // 10. Delete profile photo file
        if ($user['profile_photo'] && file_exists($user['profile_photo'])) {
            unlink($user['profile_photo']);
        }

        // 11. Finally delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo "<script>alert('User and all related data deleted successfully!'); window.location.href='admin_dashboard.php';</script>";

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
    <title>Admin Dashboard — Learning Spark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="admin_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <!-- ===== Horizontal Top Navigation ===== -->
    <header class="top-nav">
        <div class="top-nav-inner">
            <a href="admin_dashboard.php" class="top-nav-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Learning Spark</span>
            </a>
            <nav class="top-nav-links">
                <a href="admin_dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="admin_profile.php"><i class="fas fa-user-circle"></i> Profile</a>
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

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>Welcome back, <?php echo htmlspecialchars($admin_name); ?> 👋</h1>
            <p>Here's what's happening with your learning portal today.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon indigo"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value" data-count="<?php echo $total_users; ?>">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon emerald"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Staff</div>
                    <div class="stat-value" data-count="<?php echo $total_staff; ?>">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-user-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-value" data-count="<?php echo $pending_count; ?>">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rose"><i class="fas fa-envelope"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Unread Messages</div>
                    <div class="stat-value" data-count="<?php echo $unread_count; ?>">0</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="glass-card" style="animation-delay:.15s">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-pie"></i> Analytics Overview</div>
            </div>
            <div class="chart-grid">
                <div class="chart-container">
                    <canvas id="doughnutChart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Pending Registration Requests -->
        <?php if ($pending_count > 0): ?>
            <div class="glass-card" style="animation-delay:.2s">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-clock"></i> Pending Registrations</div>
                    <span class="role-badge staff"><?php echo $pending_count; ?> pending</span>
                </div>
                <?php while ($reg = $pending_registrations->fetch_assoc()): ?>
                    <div class="request-card">
                        <div class="avatar amber"><?php echo strtoupper(substr($reg['name'], 0, 1)); ?></div>
                        <div class="request-info">
                            <div class="request-name"><?php echo htmlspecialchars($reg['name']); ?></div>
                            <div class="request-email"><?php echo htmlspecialchars($reg['email']); ?></div>
                            <div style="font-size:.72rem;color:#94a3b8;margin-top:2px">Registered
                                <?php echo date('M d, Y h:i A', strtotime($reg['created_at'])); ?>
                            </div>
                        </div>
                        <div class="request-actions">
                            <form method="POST" style="display:inline-flex;align-items:center;gap:6px">
                                <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
                                <select name="assigned_role" class="form-input"
                                    style="padding:6px 32px 6px 10px;font-size:.82rem;min-width:90px;border-radius:8px"
                                    required>
                                    <option value="user">User</option>
                                    <option value="staff">Staff</option>
                                </select>
                                <button type="submit" name="approve_registration" class="btn btn-success btn-sm"><i
                                        class="fas fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Reject and delete this registration?')">
                                <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
                                <button type="submit" name="reject_registration" class="btn btn-danger btn-sm"><i
                                        class="fas fa-times"></i> Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- User Management -->
        <div class="glass-card" style="animation-delay:.25s">
            <div class="card-header">
                <div>
                    <div class="card-title"><i class="fas fa-users-cog"></i> Manage Users & Staff</div>
                    <div class="card-subtitle">Edit roles, update details, or remove accounts</div>
                </div>
                <button class="btn btn-primary" onclick="showAddModal()"><i class="fas fa-plus"></i> Add User</button>
            </div>

            <!-- Search -->
            <div class="filter-bar">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Search by name or email..." onkeyup="filterTable()">
                </div>
            </div>

            <div style="overflow-x:auto">
                <table class="admin-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <?php
                            $initials = strtoupper(substr($user['name'], 0, 1));
                            $avatarClass = $user['role'] === 'staff' ? 'emerald' : 'indigo';
                            ?>
                            <tr data-id="<?php echo $user['id']; ?>">
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar <?php echo $avatarClass; ?>"><?php echo $initials; ?></div>
                                        <div>
                                            <input type="text" name="name" class="editable-input"
                                                value="<?php echo htmlspecialchars($user['name']); ?>"
                                                data-original="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        </div>
                                    </div>
                                </td>
                                <td><input type="email" name="email" class="editable-input"
                                        value="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-original="<?php echo htmlspecialchars($user['email']); ?>" required></td>
                                <td>
                                    <select name="role" class="editable-input" data-original="<?php echo $user['role']; ?>"
                                        required>
                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User
                                        </option>
                                        <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button class="btn btn-icon btn-primary btn-sm"
                                            onclick="saveUser(<?php echo $user['id']; ?>)" title="Save">
                                            <i class="fas fa-save"></i>
                                        </button>
                                        <button class="btn btn-icon btn-ghost btn-sm" onclick="cancelEdit(this)"
                                            title="Cancel">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button class="btn btn-icon btn-danger btn-sm"
                                            onclick="showDeleteModal(<?php echo $user['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="hideAddModal()"><i class="fas fa-times"></i></button>
            <div class="modal-title">Add New User</div>
            <div class="modal-desc">Create a new user or staff account</div>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <div class="form-input-icon-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" class="form-input" placeholder="Enter full name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="form-input-icon-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-input" placeholder="Enter email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="form-input-icon-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-input" placeholder="Enter password" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-input" required>
                        <option value="user">User</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div style="display:flex;gap:10px;margin-top:24px">
                    <button type="submit" name="add_user" class="btn btn-primary btn-lg" style="flex:1"><i
                            class="fas fa-plus"></i> Add User</button>
                    <button type="button" onclick="hideAddModal()" class="btn btn-ghost btn-lg">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box" style="max-width:400px;text-align:center">
            <button class="modal-close" onclick="hideDeleteModal()"><i class="fas fa-times"></i></button>
            <div
                style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px">
                <i class="fas fa-exclamation-triangle" style="font-size:1.4rem;color:#ef4444"></i>
            </div>
            <div class="modal-title">Delete User?</div>
            <div class="modal-desc">This action cannot be undone. All related data will be permanently removed.</div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_id" id="deleteId">
                <div style="display:flex;gap:10px;margin-top:20px">
                    <button type="button" onclick="hideDeleteModal()" class="btn btn-ghost btn-lg"
                        style="flex:1">Cancel</button>
                    <button type="submit" name="confirm_delete" class="btn btn-danger btn-lg" style="flex:1"><i
                            class="fas fa-trash-alt"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ——— Animated counters ———
        document.querySelectorAll('.stat-value[data-count]').forEach(el => {
            const target = parseInt(el.dataset.count) || 0;
            const duration = 1200;
            const start = performance.now();
            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target);
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target;
            };
            requestAnimationFrame(step);
        });

        // ——— Charts ———
        const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Users', 'Staff'],
                datasets: [{
                    data: [<?php echo $total_users; ?>, <?php echo $total_staff; ?>],
                    backgroundColor: ['#6366f1', '#10b981'],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                cutout: '68%',
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { family: 'Inter', size: 13, weight: '500' }
                        }
                    }
                }
            }
        });

        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Users', 'Staff', 'Pending', 'Messages'],
                datasets: [{
                    label: 'Count',
                    data: [<?php echo $total_users; ?>, <?php echo $total_staff; ?>, <?php echo $pending_count; ?>, <?php echo $unread_count; ?>],
                    backgroundColor: [
                        'rgba(99,102,241,.8)',
                        'rgba(16,185,129,.8)',
                        'rgba(245,158,11,.8)',
                        'rgba(244,63,94,.8)'
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.04)' },
                        ticks: { font: { family: 'Inter', size: 12 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 12, weight: '500' } }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });

        // ——— Modals ———
        function showAddModal() { document.getElementById('addModal').classList.add('active'); }
        function hideAddModal() { document.getElementById('addModal').classList.remove('active'); }
        function showDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }
        function hideDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });
        });

        // ——— User CRUD ———
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
            row.querySelectorAll('[name]').forEach(f => f.value = f.getAttribute('data-original'));
        }

        // ——— Table Search ———
        function filterTable() {
            const q = document.getElementById('userSearch').value.toLowerCase();
            document.querySelectorAll('#usersTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
    </script>
</body>

</html>