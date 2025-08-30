<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Only super admins can manage users
if ($_SESSION['admin_role'] !== 'super_admin') {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check maintenance mode - only allow Emma to access during maintenance
if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== 'emma') {
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
        if ($table_check && $table_check->num_rows > 0) {
            $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'admin_maintenance_mode' LIMIT 1";
            $maintenance_result = $conn->query($maintenance_sql);
            if ($maintenance_result && $maintenance_result->num_rows > 0) {
                $maintenance_row = $maintenance_result->fetch_assoc();
                if ($maintenance_row['setting_value'] === '1') {
                    header("Location: maintenance.php");
                    exit();
                }
            }
        }
    } catch (Exception $e) {
        // Continue if there's a database error
    }
}

// Include navbar component
include('navbar.php');

$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if (!empty($username) && !empty($password)) {
            // Check if username already exists
            $check_sql = "SELECT id FROM admin_users WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_sql = "INSERT INTO admin_users (username, password, role, active, created_by) VALUES (?, ?, ?, 1, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssi", $username, $hashed_password, $role, $_SESSION['admin_id']);
                
                if ($insert_stmt->execute()) {
                    $message = "User '$username' created successfully!";
                } else {
                    $error = "Error creating user: " . $conn->error;
                }
                $insert_stmt->close();
            } else {
                $error = "Username already exists!";
            }
            $check_stmt->close();
        } else {
            $error = "Username and password are required!";
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        // Don't allow disabling self
        if ($user_id != $_SESSION['admin_id']) {
            $update_sql = "UPDATE admin_users SET active = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_status, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "User status updated successfully!";
            } else {
                $error = "Error updating user status: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            $error = "You cannot disable your own account!";
        }
    }
}

// Get all users
$users_sql = "SELECT u.id, u.username, u.role, u.active, u.created_at, u.last_login, 
                     creator.username as created_by_name
              FROM admin_users u 
              LEFT JOIN admin_users creator ON u.created_by = creator.id 
              ORDER BY u.created_at DESC";
$users_result = $conn->query($users_sql);

// Don't close connection here - navbar needs it later
// $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <style>
        :root {
            --bg-color: #ffc0cb;
            --container-bg: #fff0f5;
            --text-color: #333;
            --primary-pink: #ff1493;
            --secondary-pink: #ff69b4;
            --border-color: #ccc;
            --shadow-color: rgba(0,0,0,0.1);
            --input-bg: #fff0f5;
            --success-color: #2ed573;
            --danger-color: #ff4757;
        }

        [data-theme="dark"] {
            --bg-color: #2d1b2e;
            --container-bg: #3d2b3e;
            --text-color: #e0d0e0;
            --primary-pink: #ff6bb3;
            --secondary-pink: #d147a3;
            --border-color: #666;
            --shadow-color: rgba(0,0,0,0.3);
            --input-bg: #4a3a4a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            line-height: 1.6;
        }

        <?= getNavbarCSS() ?>

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .btn-primary { background-color: var(--primary-pink); color: white; }
        .btn-secondary { background-color: var(--secondary-pink); color: white; }
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .message {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .add-user-form {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        input, select {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 0.9rem;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
        }

        .users-table {
            background-color: var(--container-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--primary-pink);
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background-color: rgba(255, 20, 147, 0.05);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-align: center;
            min-width: 60px;
            display: inline-block;
        }

        .status-active { background-color: var(--success-color); color: white; }
        .status-inactive { background-color: var(--danger-color); color: white; }

        .role-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-align: center;
            min-width: 80px;
            display: inline-block;
        }

        .role-super_admin { background-color: var(--primary-pink); color: white; }
        .role-admin { background-color: var(--secondary-pink); color: white; }

        /* Theme Switcher */
        .theme-switcher {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background-color: var(--container-bg);
            border: 2px solid var(--secondary-pink);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px var(--shadow-color);
        }

        .theme-switcher:hover {
            transform: scale(1.1);
            background-color: var(--secondary-pink);
            color: white;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 8px;
            }

            .users-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('users.php'); ?>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="add-user-form">
            <h3 style="color: var(--primary-pink); margin-bottom: 15px;">➕ Add New User</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role:</label>
                        <select id="role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">➕ Add User</button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Login</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td>
                                <?= htmlspecialchars($user['username']) ?>
                                <?php if ($user['id'] == $_SESSION['admin_id']): ?>
                                    <span style="color: var(--primary-pink); font-weight: bold;">(You)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="role-badge role-<?= htmlspecialchars($user['role']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $user['active'] ? 'active' : 'inactive' ?>">
                                    <?= $user['active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never' ?>
                            </td>
                            <td><?= htmlspecialchars($user['created_by_name'] ?: 'System') ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $user['active'] ? 0 : 1 ?>">
                                        <button type="submit" name="toggle_status" 
                                                class="btn btn-sm <?= $user['active'] ? 'btn-danger' : 'btn-success' ?>"
                                                onclick="return confirm('Are you sure you want to <?= $user['active'] ? 'disable' : 'enable' ?> this user?')">
                                            <?= $user['active'] ? '🚫 Disable' : '✅ Enable' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--border-color); font-style: italic;">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Theme Switcher
        const themeSwitcher = document.getElementById("themeSwitcher");
        const body = document.body;

        const currentTheme = localStorage.getItem("theme") || "light";
        if (currentTheme === "dark") {
            body.setAttribute("data-theme", "dark");
            themeSwitcher.textContent = "☀️";
        }

        themeSwitcher.addEventListener("click", () => {
            const isDark = body.getAttribute("data-theme") === "dark";
            
            if (isDark) {
                body.removeAttribute("data-theme");
                themeSwitcher.textContent = "🌙";
                localStorage.setItem("theme", "light");
            } else {
                body.setAttribute("data-theme", "dark");
                themeSwitcher.textContent = "☀️";
                localStorage.setItem("theme", "dark");
            }
        });
    </script>
    
    <?php
    // Close database connection at the end
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
