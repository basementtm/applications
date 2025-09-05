<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include auth functions for user status checking
require_once 'auth_functions.php';
// Include action logging functions
require_once 'action_logger.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Check if user is still active (not disabled)
checkUserStatus();

// Only allow Emma access to this page
if ($_SESSION['admin_username'] !== 'emma') {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include navbar component
include('navbar.php');

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle global maintenance mode toggle
    if (isset($_POST['action']) && $_POST['action'] === 'global_maintenance') {
        // Check if site_settings table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
        if ($table_check->num_rows === 0) {
            $create_table_sql = "CREATE TABLE site_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_name VARCHAR(255) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(100)
            )";
            $conn->query($create_table_sql);
        }
        
        // Set both maintenance modes to ON
        $admin_username = $_SESSION['admin_username'];
        
        // Update admin_maintenance_mode
        $stmt1 = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, updated_by) 
                               VALUES ('admin_maintenance_mode', '1', ?) 
                               ON DUPLICATE KEY UPDATE setting_value = '1', updated_by = ?");
        $stmt1->bind_param("ss", $admin_username, $admin_username);
        $success1 = $stmt1->execute();
        $stmt1->close();
        
        // Update maintenance_mode
        $stmt2 = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, updated_by) 
                               VALUES ('maintenance_mode', '1', ?) 
                               ON DUPLICATE KEY UPDATE setting_value = '1', updated_by = ?");
        $stmt2->bind_param("ss", $admin_username, $admin_username);
        $success2 = $stmt2->execute();
        $stmt2->close();
        
        if ($success1 && $success2) {
            $message = "Global maintenance mode enabled successfully. Both public site and admin panel are now in maintenance mode.";
            
            // Log the maintenance mode change
            logAction('MAINTENANCE_GLOBAL_ENABLED', "Admin enabled global maintenance mode", 'maintenance_system', null, [
                'maintenance_type' => 'global',
                'new_status' => '1'
            ]);
        } else {
            $error = "Error updating global maintenance mode: " . $conn->error;
        }
    }
    
    // Handle wipe applicants table
    if (isset($_POST['wipe_applicants'])) {
        $confirm_text = trim($_POST['confirm_text']);
        if ($confirm_text === 'DELETE ALL APPLICATIONS') {
            // Get count before deletion for logging
            $count_result = $conn->query("SELECT COUNT(*) as total FROM applicants");
            $app_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;
            
            $wipe_sql = "DELETE FROM applicants";
            if ($conn->query($wipe_sql)) {
                $message = "All applications have been permanently deleted! Count: " . $conn->affected_rows;
                
                // Log the mass deletion action
                logAction('ADMIN_MASS_DELETE', "Admin deleted all applications ($app_count total)", 'applications', null, ['deleted_count' => $app_count]);
            } else {
                $error = "Error wiping applications: " . $conn->error;
            }
        } else {
            $error = "Confirmation text doesn't match. Applications were NOT deleted.";
        }
    }
    
    // Handle maintenance mode toggle
    if (isset($_POST['toggle_maintenance'])) {
        $new_status = $_POST['new_maintenance_status'];
        $maintenance_type = $_POST['maintenance_type'];
        
        // Check if site_settings table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
        if ($table_check->num_rows === 0) {
            $create_table_sql = "CREATE TABLE site_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_name VARCHAR(255) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(100)
            )";
            $conn->query($create_table_sql);
        }
        
        // Update or insert maintenance setting
        $admin_username = $_SESSION['admin_username'];
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, updated_by) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
        $stmt->bind_param("sssss", $setting_name, $new_status, $admin_username, $new_status, $admin_username);
        $setting_name = $maintenance_type;
        
        if ($stmt->execute()) {
            $type_labels = [
                'admin_maintenance_mode' => 'Admin Panel',
                'maintenance_mode' => 'Site',
                'form_maintenance_mode' => 'Form'
            ];
            $type_label = $type_labels[$maintenance_type] ?? ucfirst(str_replace('_', ' ', $maintenance_type));
            $message = "$type_label maintenance mode " . ($new_status === '1' ? "enabled" : "disabled") . " successfully!";
            
            // Log the maintenance mode change
            if ($maintenance_type === 'admin_maintenance_mode') {
                // Use the dedicated function for admin maintenance
                logMaintenanceAction($new_status === '1');
            } else {
                // For other maintenance types, use the general logAction function
                $action_type = 'MAINTENANCE_' . strtoupper($type_label) . '_' . ($new_status === '1' ? 'ENABLED' : 'DISABLED');
                $description = "Admin " . ($new_status === '1' ? 'enabled' : 'disabled') . " $type_label maintenance mode";
                logAction($action_type, $description, 'maintenance_system', null, [
                    'maintenance_type' => $maintenance_type,
                    'new_status' => $new_status
                ]);
            }
        } else {
            $error = "Error updating maintenance mode: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Handle user management actions
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
                    $new_user_id = $conn->insert_id;
                    $message = "User '$username' created successfully!";
                    
                    // Log the user creation action
                    logUserAction('created', $new_user_id, $username);
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
    
    // Handle user disable/enable functionality
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $user_id = (int)$_POST['user_id'];
        
        // Get current user status and username
        $check_sql = "SELECT active, username FROM admin_users WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $user_data = $result->fetch_assoc();
        $check_stmt->close();
        
        if ($user_data) {
            // Don't allow disabling emma or self
            if ($user_data['username'] !== 'emma' && $user_id != $_SESSION['admin_id']) {
                $new_status = $user_data['active'] ? 0 : 1; // Toggle status
                $update_sql = "UPDATE admin_users SET active = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $new_status, $user_id);
                
                if ($update_stmt->execute()) {
                    $status_text = $new_status ? "enabled" : "disabled";
                    $message = "User has been {$status_text} successfully!";
                    
                    // Log the user status change action
                    logUserAction($status_text, $user_id, $user_data['username']);
                } else {
                    $error = "Error updating user status: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $error = "You cannot disable this user account!";
            }
        } else {
            $error = "User not found!";
        }
    }
    
    // Add functionality to change user roles and delete users
    if (isset($_POST['action']) && $_POST['action'] === 'change_role') {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['new_role'];
        $update_role_sql = "UPDATE admin_users SET role = ? WHERE id = ?";
        $stmt = $conn->prepare($update_role_sql);
        $stmt->bind_param("si", $new_role, $user_id);
        if ($stmt->execute()) {
            $message = "User role updated successfully!";
        } else {
            $error = "Error updating user role: " . $conn->error;
        }
        $stmt->close();
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $user_id = (int)$_POST['user_id'];
        $delete_user_sql = "DELETE FROM admin_users WHERE id = ?";
        $stmt = $conn->prepare($delete_user_sql);
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = "User deleted successfully!";
        } else {
            $error = "Error deleting user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get current maintenance status for both site and admin panel
$site_maintenance_active = false;
$admin_maintenance_active = false;

$site_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
$site_maintenance_result = $conn->query($site_maintenance_sql);
if ($site_maintenance_result && $site_maintenance_result->num_rows > 0) {
    $site_maintenance_row = $site_maintenance_result->fetch_assoc();
    $site_maintenance_active = ($site_maintenance_row['setting_value'] === '1');
}

$admin_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'admin_maintenance_mode' LIMIT 1";
$admin_maintenance_result = $conn->query($admin_maintenance_sql);
if ($admin_maintenance_result && $admin_maintenance_result->num_rows > 0) {
    $admin_maintenance_row = $admin_maintenance_result->fetch_assoc();
    $admin_maintenance_active = ($admin_maintenance_row['setting_value'] === '1');
}

$form_maintenance_active = false;
$form_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'form_maintenance_mode' LIMIT 1";
$form_maintenance_result = $conn->query($form_maintenance_sql);
if ($form_maintenance_result && $form_maintenance_result->num_rows > 0) {
    $form_maintenance_row = $form_maintenance_result->fetch_assoc();
    $form_maintenance_active = ($form_maintenance_row['setting_value'] === '1');
}

// Get application count
$count_sql = "SELECT COUNT(*) as total FROM applicants";
$count_result = $conn->query($count_sql);
$app_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// Get all users
$users_sql = "SELECT u.id, u.username, u.role, u.active, u.created_at, u.last_login, 
                     creator.username as created_by_name
              FROM admin_users u 
              LEFT JOIN admin_users creator ON u.created_by = creator.id 
              ORDER BY u.created_at DESC";
$users_result = $conn->query($users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Panel - Admin Dashboard</title>
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
            --warning-color: #ffa502;
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
        .btn-warning { background-color: var(--warning-color); color: white; }
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

        .section {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .section h3 {
            color: var(--primary-pink);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .danger-section {
            border: 2px solid var(--danger-color);
            background-color: rgba(255, 71, 87, 0.1);
        }
        
        [data-theme="dark"] .danger-section {
            background-color: rgba(255, 71, 87, 0.1);
        }
        
        [data-theme="light"] .danger-section {
            background-color: #fff;
        }

        .danger-section h3 {
            color: var(--danger-color);
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

        input, select, textarea {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 0.9rem;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px var(--shadow-color);
            border: 2px solid var(--primary-pink);
            transition: all 0.3s ease;
        }

        .stat-card.green-card {
            border: 2px solid var(--success-color);
        }

        .stat-card.green-card .stat-number {
            color: var(--success-color);
        }

        .stat-card.maintenance-on {
            border-color: var(--danger-color);
            background-color: rgba(255, 71, 87, 0.1);
        }

        .stat-card.maintenance-off {
            border-color: var(--success-color);
            background-color: rgba(46, 213, 115, 0.1);
        }
        
        [data-theme="light"] .stat-card.maintenance-on {
            background-color: #fff;
            border-color: var(--danger-color);
        }
        
        [data-theme="light"] .stat-card.maintenance-off {
            background-color: #fff;
            border-color: var(--success-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-pink);
            transition: color 0.3s ease;
        }

        .stat-card.maintenance-on .stat-number {
            color: var(--danger-color);
        }

        .stat-card.maintenance-off .stat-number {
            color: var(--success-color);
        }

        .stat-label {
            color: var(--text-color);
            font-size: 0.9rem;
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
        .role-owner { 
            background: linear-gradient(135deg, #8B008B, #FF1493); 
            color: white; 
            border: 2px solid #FFD700;
            box-shadow: 0 2px 8px rgba(139, 0, 139, 0.3);
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-admin { background-color: var(--secondary-pink); color: white; }
        .role-readonly_admin { background-color: var(--warning-color); color: white; }

        .warning-text {
            color: var(--danger-color);
            font-weight: bold;
            background-color: rgba(255, 71, 87, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .confirm-input {
            border: 2px solid var(--danger-color) !important;
            background-color: rgba(255, 71, 87, 0.1) !important;
        }

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

        #rolePopup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            background: var(--container-bg);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 4px 10px var(--shadow-color);
            animation: fadeScale 0.3s forwards;
        }

        #blurOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 999;
        }

        @keyframes fadeScale {
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }

            .form-grid, .stats-grid {
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
    
    <?php renderAdminNavbar('owner.php'); ?>

    <div class="container">
        <div class="section">
            <h3>👑 Owner's Panel</h3>
            <p>This panel contains dangerous operations that can only be performed by the owner. Use with extreme caution.</p>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($app_count) ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card <?= $site_maintenance_active ? 'maintenance-on' : 'maintenance-off' ?>">
                <div class="stat-number"><?= $site_maintenance_active ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Site Maintenance</div>
            </div>
            <div class="stat-card <?= $admin_maintenance_active ? 'maintenance-on' : 'maintenance-off' ?>">
                <div class="stat-number"><?= $admin_maintenance_active ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Admin Panel Maintenance</div>
            </div>
            <div class="stat-card <?= ($form_maintenance_active || $site_maintenance_active) ? 'maintenance-on' : 'maintenance-off' ?>">
                <div class="stat-number"><?= ($form_maintenance_active || $site_maintenance_active) ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Form Maintenance</div>
            </div>
        </div>

        <!-- Maintenance Mode Control -->
        <div class="section">
            <h3>🚧 Maintenance Mode Control</h3>
            <p>Control maintenance modes separately for the site and admin panel.</p>
            
            <!-- Form Maintenance -->
            <div style="margin: 20px 0; padding: 15px; background-color: rgba(46, 213, 115, 0.1); border-radius: 8px; border: 1px solid var(--success-color);">
                <h4 style="color: var(--success-color); margin-bottom: 10px;">📝 Form Maintenance Mode</h4>
                <p style="margin-bottom: 15px; font-size: 0.9rem;">Blocks public access to the application form only, but allows logged-in admins to access it</p>
                <form method="POST" action="maintenance-control.php" style="display: inline;">
                    <input type="hidden" name="new_maintenance_status" value="<?= $form_maintenance_active ? '0' : '1' ?>">
                    <input type="hidden" name="maintenance_type" value="form_maintenance_mode">
                    <button type="submit" name="toggle_maintenance" 
                            class="btn <?= $form_maintenance_active ? 'btn-success' : '' ?>"
                            style="<?= !$form_maintenance_active ? 'background-color: var(--success-color); color: white;' : '' ?>"
                            onclick="return confirm('Are you sure you want to <?= $form_maintenance_active ? 'disable' : 'enable' ?> form maintenance mode?')">
                        <?= $form_maintenance_active ? '✅ Disable Form Maintenance' : '📝 Enable Form Maintenance' ?>
                    </button>
                </form>
            </div>
            
            <!-- Site Maintenance -->
            <div style="margin: 20px 0; padding: 15px; background-color: rgba(255, 165, 2, 0.1); border-radius: 8px; border: 1px solid var(--warning-color);">
                <h4 style="color: var(--warning-color); margin-bottom: 10px;">🌐 Site Maintenance Mode</h4>
                <p style="margin-bottom: 15px; font-size: 0.9rem;">Closes the entire site for public users (status checker, applications, etc.)</p>
                <form method="POST" action="maintenance-control.php" style="display: inline;">
                    <input type="hidden" name="new_maintenance_status" value="<?= $site_maintenance_active ? '0' : '1' ?>">
                    <input type="hidden" name="maintenance_type" value="maintenance_mode">
                    <button type="submit" name="toggle_maintenance" 
                            class="btn <?= $site_maintenance_active ? 'btn-success' : 'btn-warning' ?>"
                            onclick="return confirm('Are you sure you want to <?= $site_maintenance_active ? 'disable' : 'enable' ?> site maintenance mode?')">
                        <?= $site_maintenance_active ? '✅ Disable Site Maintenance' : '🚧 Enable Site Maintenance' ?>
                    </button>
                </form>
            </div>
            
            <!-- Admin Panel Maintenance -->
            <div style="margin: 20px 0; padding: 15px; background-color: rgba(0, 123, 255, 0.1); border-radius: 8px; border: 1px solid #007bff;">
                <h4 style="color: #007bff; margin-bottom: 10px;">👥 Admin Panel Maintenance Mode</h4>
                <p style="margin-bottom: 15px; font-size: 0.9rem;">Closes the admin panel for all admins except Emma</p>
                <form method="POST" action="maintenance-control.php" style="display: inline;">
                    <input type="hidden" name="new_maintenance_status" value="<?= $admin_maintenance_active ? '0' : '1' ?>">
                    <input type="hidden" name="maintenance_type" value="admin_maintenance_mode">
                    <button type="submit" name="toggle_maintenance" 
                            class="btn <?= $admin_maintenance_active ? 'btn-success' : '' ?>"
                            style="<?= !$admin_maintenance_active ? 'background-color: #007bff; color: white;' : '' ?>"
                            onclick="return confirm('Are you sure you want to <?= $admin_maintenance_active ? 'disable' : 'enable' ?> admin panel maintenance mode?')">
                        <?= $admin_maintenance_active ? '✅ Disable Admin Maintenance' : '🚧 Enable Admin Maintenance' ?>
                    </button>
                </form>
            </div>
            
            <!-- Global Maintenance -->
            <div style="margin: 20px 0; padding: 15px; background-color: rgba(255, 71, 87, 0.1); border-radius: 8px; border: 1px solid var(--danger-color);">
                <h4 style="color: var(--danger-color); margin-bottom: 10px;">🌍 Global Maintenance Mode</h4>
                <p style="margin-bottom: 15px; font-size: 0.9rem;"><strong>WARNING:</strong> This will put the entire site (public site and admin panel) into maintenance mode. Only Emma will be able to access the admin panel.</p>
                <?php if ($site_maintenance_active && $admin_maintenance_active): ?>
                <form method="POST" action="maintenance-control.php" style="display: inline;">
                    <input type="hidden" name="action" value="disable_global_maintenance">
                    <button type="submit" 
                            class="btn btn-success"
                            onclick="return confirm('Are you sure you want to disable global maintenance mode? This will turn off both site and admin maintenance modes.')">
                        ✅ Disable Global Maintenance
                    </button>
                </form>
                <?php else: ?>
                <form method="POST" action="maintenance-control.php" style="display: inline;">
                    <input type="hidden" name="action" value="global_maintenance">
                    <button type="submit" 
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to enable global maintenance mode? This will lock out all users except Emma from both the public site and admin panel.')">
                        🔒 Enable Global Maintenance
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Privacy Notifications -->
        <div class="section">
            <h3>🔔 Privacy Policy Notifications</h3>
            <p>Manage privacy policy update notifications that will be shown to users.</p>
            
            <div style="margin: 20px 0; padding: 15px; background-color: rgba(46, 213, 115, 0.1); border-radius: 8px; border: 1px solid var(--success-color);">
                <h4 style="color: var(--success-color); margin-bottom: 10px;">📝 Privacy Policy Notifications Management</h4>
                <p style="margin-bottom: 15px; font-size: 0.9rem;">Create and manage notifications that appear when the privacy policy is updated</p>
                <a href="privacy-notifications.php" class="btn" style="background-color: var(--success-color); color: white;">
                    🔔 Manage Privacy Notifications
                </a>
            </div>
        </div>

        <!-- Dangerous Operations -->
        <div class="section danger-section">
            <h3>⚠️ Dangerous Operations</h3>
            <div class="warning-text">
                ⚠️ WARNING: This action will permanently delete ALL applications and cannot be undone!
            </div>
            
            <form method="POST" onsubmit="return confirmWipe()">
                <div class="form-group">
                    <label for="confirm_text">Type "DELETE ALL APPLICATIONS" to confirm:</label>
                    <input type="text" id="confirm_text" name="confirm_text" 
                           class="confirm-input" placeholder="DELETE ALL APPLICATIONS" required>
                </div>
                <button type="submit" name="wipe_applicants" class="btn btn-danger">
                    💥 WIPE ALL APPLICATIONS (<?= number_format($app_count) ?> total)
                </button>
            </form>
        </div>

        <!-- User Management -->
        <div class="section">
            <h3>👥 User Management</h3>
            
            <!-- Add User Form -->
            <div style="background-color: rgba(255, 20, 147, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="color: var(--primary-pink); margin-bottom: 15px;">➕ Add New User</h4>
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
                                <option value="readonly_admin">Read-Only Admin</option>
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
                                    <?php if ($user['username'] === 'emma'): ?>
                                        <span style="color: var(--primary-pink); font-weight: bold;">(Owner)</span>
                                    <?php elseif ($user['id'] == $_SESSION['admin_id']): ?>
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
                                    <?php if ($user['username'] !== 'emma'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $user['active'] ? 'btn-danger' : 'btn-success' ?>" 
                                                    onclick="return confirm('Are you sure you want to <?= $user['active'] ? 'disable' : 'enable' ?> this user?')">
                                                <?= $user['active'] ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-primary" onclick="openRolePopup(<?= $user['id'] ?>, '<?= $user['role'] ?>')">Change Role</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Cannot modify</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="blurOverlay"></div>
    <div id="rolePopup">
      <h3 style="margin-bottom: 15px;">Change User Role</h3>
      <form id="roleForm" method="POST">
        <input type="hidden" name="action" value="change_role">
        <input type="hidden" name="user_id" id="popupUserId">
        <label for="popupRole">Select Role:</label>
        <select name="new_role" id="popupRole" required>
          <option value="readonly_admin">Read-Only Admin</option>
          <option value="admin">Admin</option>
          <option value="super_admin">Super Admin</option>
        </select>
        <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" id="cancelRole" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>
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

        // Confirm wipe operation
        function confirmWipe() {
            const confirmText = document.getElementById('confirm_text').value;
            if (confirmText !== 'DELETE ALL APPLICATIONS') {
                alert('You must type "DELETE ALL APPLICATIONS" exactly to confirm.');
                return false;
            }
            
            return confirm('This will permanently delete ALL applications. This action cannot be undone. Are you absolutely sure?');
        }

        const rolePopup = document.getElementById('rolePopup');
        const blurOverlay = document.getElementById('blurOverlay');
        const cancelRole = document.getElementById('cancelRole');
        const roleForm = document.getElementById('roleForm');
        const popupUserId = document.getElementById('popupUserId');
        const popupRole = document.getElementById('popupRole');

        function openRolePopup(userId, currentRole) {
          popupUserId.value = userId;
          popupRole.value = currentRole;
          rolePopup.style.display = 'block';
          blurOverlay.style.display = 'block';
        }

        cancelRole.addEventListener('click', () => {
          rolePopup.style.display = 'none';
          blurOverlay.style.display = 'none';
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
