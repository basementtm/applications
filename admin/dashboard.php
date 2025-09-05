<?php
session_start();

// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Helper function to check if user is read-only
function isReadOnlyUser() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'readonly_admin';
}

// Handle maintenance toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_maintenance'])) {
    // Check if user has permission to toggle maintenance
    if (isReadOnlyUser()) {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
    
    // Check if site_settings table exists first
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    
    if ($table_check && $table_check->num_rows > 0) {
        // Table exists, proceed with database operations
        // Check current maintenance status from database
        $check_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
        $check_result = $conn->query($check_sql);
        
        $current_maintenance = false;
        if ($check_result && $check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $current_maintenance = ($row['setting_value'] === '1');
        }
        
        if ($current_maintenance) {
            // Turn off maintenance mode
            $update_sql = "UPDATE site_settings SET setting_value = '0', updated_at = NOW(), updated_by = ? WHERE setting_name = 'maintenance_mode'";
            $stmt = $conn->prepare($update_sql);
            $admin_username = $_SESSION['admin_username'] ?? 'system';
            $stmt->bind_param("s", $admin_username);
            
            if ($stmt->execute()) {
                $maintenance_message = "Maintenance mode has been turned off. Applications are now accepting submissions.";
                $maintenance_type = "success";
            } else {
                $maintenance_message = "Error: Could not turn off maintenance mode.";
                $maintenance_type = "error";
            }
            $stmt->close();
        } else {
            // Turn on maintenance mode
            $insert_sql = "INSERT INTO site_settings (setting_name, setting_value, updated_at, updated_by) 
                           VALUES ('maintenance_mode', '1', NOW(), ?) 
                           ON DUPLICATE KEY UPDATE 
                           setting_value = '1', updated_at = NOW(), updated_by = ?";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ss", $_SESSION['admin_username'], $_SESSION['admin_username']);
            
            if ($stmt->execute()) {
                $maintenance_message = "Maintenance mode has been turned on. Applications are now closed to the public.";
                $maintenance_type = "warning";
            } else {
                $maintenance_message = "Error: Could not turn on maintenance mode.";
                $maintenance_type = "error";
            }
            $stmt->close();
        }
    } else {
        // Table doesn't exist, show setup message
        $maintenance_message = "Database incorrectly setup.";
        $maintenance_type = "error";
    }
    
    // Redirect to prevent form resubmission
    header("Location: dashboard.php?maintenance_updated=1&message=" . urlencode($maintenance_message) . "&type=" . $maintenance_type);
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (isReadOnlyUser()) {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }

    $application_id = $_POST['application_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    $reason = $_POST['reason'] ?? 'N/A';

    if (!empty($application_id) && !empty($new_status)) {
        $current_sql = "SELECT name, status FROM applicants WHERE application_id = ?";
        $current_stmt = $conn->prepare($current_sql);
        $current_stmt->bind_param("s", $application_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current_data = $current_result->fetch_assoc();
        $current_stmt->close();

        if ($current_data) {
            $old_status = $current_data['status'];
            $applicant_name = $current_data['name'];

            $update_sql = "UPDATE applicants SET status = ?, status_change_reason = ? WHERE application_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sss", $new_status, $reason, $application_id);
            $update_stmt->execute();
            $update_stmt->close();

            logApplicationStatusChange($application_id, $application_id, $old_status, $new_status);
        }

        header("Location: dashboard.php?updated=" . urlencode($application_id));
        exit();
    }
}

// Handle bulk status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    // Check if user has permission to bulk update
    if (isReadOnlyUser()) {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
    
    $selected_applications = $_POST['selected_applications'] ?? [];
    $bulk_status = $_POST['bulk_status'] ?? '';
    
    if (!empty($selected_applications) && !empty($bulk_status) && is_array($selected_applications)) {
        // Get current application data for logging
        $placeholders_select = str_repeat('?,', count($selected_applications) - 1) . '?';
        $current_sql = "SELECT application_id, name, status FROM applicants WHERE application_id IN ($placeholders_select)";
        $current_stmt = $conn->prepare($current_sql);
        $types_select = str_repeat('s', count($selected_applications));
        $current_stmt->bind_param($types_select, ...$selected_applications);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        $applications_data = [];
        while ($row = $current_result->fetch_assoc()) {
            $applications_data[$row['application_id']] = $row;
        }
        $current_stmt->close();
        
        // Perform bulk update
        $placeholders = str_repeat('?,', count($selected_applications) - 1) . '?';
        $bulk_sql = "UPDATE applicants SET status = ? WHERE application_id IN ($placeholders)";
        $bulk_stmt = $conn->prepare($bulk_sql);
        
        // Prepare parameters: status first, then all application IDs
        $params = array_merge([$bulk_status], $selected_applications);
        $types = str_repeat('s', count($params));
        $bulk_stmt->bind_param($types, ...$params);
        $bulk_stmt->execute();
        $affected_rows = $bulk_stmt->affected_rows;
        $bulk_stmt->close();
        
        // Log each status change
        foreach ($selected_applications as $app_id) {
            if (isset($applications_data[$app_id])) {
                $app_data = $applications_data[$app_id];
                $old_status = $app_data['status'];
                
                // Only log if status actually changed
                if ($old_status !== $bulk_status) {
                    logApplicationStatusChange($app_id, $app_id, $old_status, $bulk_status);
                }
            }
        }
        
        // Log the bulk action
        logAction('ADMIN_BULK_STATUS_UPDATE', "Admin performed bulk status update on {$affected_rows} applications to status: {$bulk_status}", 'application', null, [
            'new_status' => $bulk_status,
            'affected_count' => $affected_rows,
            'application_ids' => $selected_applications
        ]);
        
        // Redirect with success message
        header("Location: dashboard.php?bulk_updated=" . $affected_rows);
        exit();
    }
}

// Add bulk delete functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    // Check if user has permission to bulk delete
    if (isReadOnlyUser()) {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }

    $selected_applications = $_POST['selected_applications'] ?? [];

    if (!empty($selected_applications) && is_array($selected_applications)) {
        // Perform bulk delete
        $placeholders = str_repeat('?,', count($selected_applications) - 1) . '?';
        $delete_sql = "DELETE FROM applicants WHERE application_id IN ($placeholders)";
        $delete_stmt = $conn->prepare($delete_sql);

        $types = str_repeat('s', count($selected_applications));
        $delete_stmt->bind_param($types, ...$selected_applications);
        $delete_stmt->execute();
        $affected_rows = $delete_stmt->affected_rows;
        $delete_stmt->close();

        // Log the bulk delete action
        logAction('ADMIN_BULK_DELETE', "Admin deleted {$affected_rows} applications", 'application', null, [
            'deleted_count' => $affected_rows,
            'application_ids' => $selected_applications
        ]);

        // Redirect with success message
        header("Location: dashboard.php?bulk_deleted=" . $affected_rows);
        exit();
    }
}

// Get current maintenance statuses for display
$site_maintenance_active = false;
$form_maintenance_active = false;

$site_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
$site_maintenance_result = $conn->query($site_maintenance_sql);
if ($site_maintenance_result && $site_maintenance_result->num_rows > 0) {
    $site_maintenance_row = $site_maintenance_result->fetch_assoc();
    $site_maintenance_active = ($site_maintenance_row['setting_value'] === '1');
}

$form_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'form_maintenance_mode' LIMIT 1";
$form_maintenance_result = $conn->query($form_maintenance_sql);
if ($form_maintenance_result && $form_maintenance_result->num_rows > 0) {
    $form_maintenance_row = $form_maintenance_result->fetch_assoc();
    $form_maintenance_active = ($form_maintenance_row['setting_value'] === '1');
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR application_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM applicants $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_applications = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_applications / $per_page);
$count_stmt->close();

// Get applications
$sql = "SELECT application_id, name, email, status, cage, isCat, preferredLocation, 
               DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as submitted_at 
        FROM applicants 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts
$stats_sql = "SELECT status, COUNT(*) as count FROM applicants GROUP BY status";
$stats_result = $conn->query($stats_sql);
$status_counts = [];
while ($row = $stats_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

// Don't close connection here - navbar needs it later
// $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Basement Applications</title>
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
            --warning-color: #ffa502;
            --danger-color: #ff4757;
            --info-color: #3742fa;
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

        .btn-primary {
            background-color: var(--primary-pink);
            color: white;
        }

        .btn-secondary {
            background-color: var(--secondary-pink);
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .message {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px var(--shadow-color);
            text-align: center;
            border: 2px solid;
            transition: all 0.3s ease;
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

        .stat-card.application-stat {
            border-color: var(--primary-pink);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .stat-card.maintenance-on .stat-number {
            color: var(--danger-color);
        }

        .stat-card.maintenance-off .stat-number {
            color: var(--success-color);
        }

        .stat-card.application-stat .stat-number {
            color: var(--primary-pink);
        }

        .filters {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-group label {
            font-weight: bold;
            min-width: 60px;
        }

        select, input[type="text"] {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        .applications-table {
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
            min-width: 80px;
            display: inline-block;
        }

        .status-unreviewed { background-color: var(--secondary-pink); color: white; }
        .status-stage2 { background-color: var(--warning-color); color: white; }
        .status-stage3 { background-color: var(--info-color); color: white; }
        .status-accepted { background-color: var(--success-color); color: white; }
        .status-denied { background-color: var(--danger-color); color: white; }
        .status-invalid { background-color: #e67e22; color: white; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--text-color);
            border-radius: 5px;
        }

        .pagination .current {
            background-color: var(--primary-pink);
            color: white;
            border-color: var(--primary-pink);
        }

        .pagination a:hover {
            background-color: var(--secondary-pink);
            color: white;
        }

        .update-success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .maintenance-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }

        [data-theme="dark"] .update-success {
            background-color: #1e4620;
            color: #4caf50;
        }

        [data-theme="dark"] .maintenance-warning {
            background-color: #4a3a2a;
            color: #ffd93d;
        }



        .btn-warning {
            background-color: #ffa502;
            color: white;
        }

        .btn-success {
            background-color: #2ed573;
            color: white;
        }

        .btn-warning:hover {
            background-color: #ff9500;
        }

        .btn-success:hover {
            background-color: #20bf6b;
        }

        @media (max-width: 768px) {
            .maintenance-status {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
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

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 8px;
            }

            .applications-table {
                overflow-x: auto;
            }
        }

        /* Reason Popup */
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

        #reasonPopup {
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
            opacity: 0;
            animation: fadeScale 0.3s forwards;
        }

        @keyframes fadeScale {
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        #reasonPopup h3 {
            margin-bottom: 15px;
        }

        #reasonPopup textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        #reasonPopup .btn {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('dashboard.php'); ?>

    <div class="container">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
            <div class="message error">
                ❌ Access denied. You don't have permission to perform this action.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="update-success">
                ✅ Application <?= htmlspecialchars($_GET['updated']) ?> status updated successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['bulk_updated'])): ?>
            <div class="update-success">
                ✅ Successfully updated <?= (int)$_GET['bulk_updated'] ?> application(s) status!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="update-success">
                🗑️ Application <?= htmlspecialchars($_GET['deleted']) ?> has been permanently deleted.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['maintenance_updated'])): ?>
            <div class="update-success <?= $_GET['type'] === 'warning' ? 'maintenance-warning' : '' ?>">
                <?= $_GET['type'] === 'warning' ? '🚧' : '✅' ?> <?= htmlspecialchars($_GET['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Maintenance Statistics -->
        <div class="stats-grid">
            <div class="stat-card <?= $site_maintenance_active ? 'maintenance-on' : 'maintenance-off' ?>">
                <div class="stat-number"><?= $site_maintenance_active ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Site Maintenance</div>
            </div>
            <div class="stat-card <?= ($form_maintenance_active || $site_maintenance_active) ? 'maintenance-on' : 'maintenance-off' ?>">
                <div class="stat-number"><?= ($form_maintenance_active || $site_maintenance_active) ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Form Maintenance</div>
            </div>
        </div>

        <!-- Application Statistics -->
        <div class="stats-grid">
            <div class="stat-card application-stat">
                <div class="stat-number"><?= $total_applications ?></div>
                <div>Total Applications</div>
            </div>
            <div class="stat-card application-stat">
                <div class="stat-number"><?= $status_counts['unreviewed'] ?? 0 ?></div>
                <div>Unreviewed</div>
            </div>
            <div class="stat-card application-stat">
                <div class="stat-number"><?= ($status_counts['stage2'] ?? 0) + ($status_counts['stage3'] ?? 0) ?></div>
                <div>In Progress</div>
            </div>
            <div class="stat-card application-stat">
                <div class="stat-number"><?= $status_counts['accepted'] ?? 0 ?></div>
                <div>Accepted</div>
            </div>
            <div class="stat-card application-stat">
                <div class="stat-number"><?= $status_counts['denied'] ?? 0 ?></div>
                <div>Denied</div>
            </div>
            <div class="stat-card application-stat">
                <div class="stat-number"><?= $status_counts['invalid'] ?? 0 ?></div>
                <div>Invalid</div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filters" method="GET">
            <div class="filter-group">
                <label>Status:</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="unreviewed" <?= $status_filter === 'unreviewed' ? 'selected' : '' ?>>Unreviewed</option>
                    <option value="stage2" <?= $status_filter === 'stage2' ? 'selected' : '' ?>>Stage 2</option>
                    <option value="stage3" <?= $status_filter === 'stage3' ? 'selected' : '' ?>>Stage 3</option>
                    <option value="accepted" <?= $status_filter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="denied" <?= $status_filter === 'denied' ? 'selected' : '' ?>>Denied</option>
                    <option value="invalid" <?= $status_filter === 'invalid' ? 'selected' : '' ?>>Invalid</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search:</label>
                <input type="text" name="search" placeholder="Name, email, or ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">🔄 Reset</a>
        </form>

        <!-- Applications Table with Bulk Actions -->
        <form id="bulkForm" method="POST">
            <?php if (!isReadOnlyUser()): ?>
            <!-- Bulk Actions -->
            <div class="bulk-actions" style="margin: 20px 0; padding: 15px; background: var(--container-bg); border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <label style="font-weight: bold;">Bulk Actions:</label>
                    <select name="bulk_action" id="bulkAction" style="padding: 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                        <option value="">Select Action</option>
                        <option value="bulk_status">Change Status</option>
                        <option value="bulk_delete">Delete Applications</option>
                    </select>
                    <select name="bulk_status" id="bulkStatus" style="padding: 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                        <option value="">Select Status</option>
                        <option value="unreviewed">Set to Unreviewed</option>
                        <option value="stage2">Set to Stage 2</option>
                        <option value="stage3">Set to Stage 3</option>
                        <option value="accepted">Set to Accepted</option>
                        <option value="denied">Set to Denied</option>
                        <option value="invalid">Set to Invalid</option>
                    </select>
                    <button type="button" id="bulkSubmit" class="btn btn-warning btn-sm" style="padding: 8px 15px;">Apply to Selected</button>
                    <span id="selectedCount" style="color: var(--text-color); font-size: 0.9rem;">0 selected</span>
                </div>
                <input type="hidden" name="bulk_update" value="1">
                <input type="hidden" name="bulk_delete" value="1">
            </div>
            <?php endif; ?>

        <div class="applications-table">
            <table>
                <thead>
                    <tr>
                        <?php if (!isReadOnlyUser()): ?>
                        <th><input type="checkbox" id="selectAll" title="Select All"></th>
                        <?php endif; ?>
                        <th>Application ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Cat</th>
                        <th>Cage/Week</th>
                        <th>Submitted</th>
                        <?php if (!isReadOnlyUser()): ?>
                        <th>Actions</th>
                        <?php else: ?>
                        <th>View</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <?php if (!isReadOnlyUser()): ?>
                            <td><input type="checkbox" name="selected_applications[]" value="<?= htmlspecialchars($app['application_id']) ?>" class="app-checkbox"></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($app['application_id']) ?></td>
                            <td><?= htmlspecialchars($app['name']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($app['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($app['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($app['isCat']) ?></td>
                            <td><?= htmlspecialchars($app['cage']) ?></td>
                            <td><?= htmlspecialchars($app['submitted_at']) ?></td>
                            <?php if (!isReadOnlyUser()): ?>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['application_id']) ?>">
                                        <select name="new_status" onchange="openReasonPopup('<?= htmlspecialchars($app['application_id']) ?>', this.value)" style="width: auto; padding: 4px; font-size: 0.8rem;">
                                            <option value="">Change Status</option>
                                            <option value="unreviewed" <?= $app['status'] === 'unreviewed' ? 'disabled' : '' ?>>Unreviewed</option>
                                            <option value="stage2" <?= $app['status'] === 'stage2' ? 'disabled' : '' ?>>Stage 2</option>
                                            <option value="stage3" <?= $app['status'] === 'stage3' ? 'disabled' : '' ?>>Stage 3</option>
                                            <option value="accepted" <?= $app['status'] === 'accepted' ? 'disabled' : '' ?>>Accepted</option>
                                            <option value="denied" <?= $app['status'] === 'denied' ? 'disabled' : '' ?>>Denied</option>
                                            <option value="invalid" <?= $app['status'] === 'invalid' ? 'disabled' : '' ?>>Invalid</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                    <a href="view.php?id=<?= urlencode($app['application_id']) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 0.8rem;">👁️</a>
                                    <a href="edit.php?id=<?= urlencode($app['application_id']) ?>" class="btn btn-primary btn-sm" style="padding: 4px 8px; font-size: 0.8rem;">✏️</a>
                                    <a href="delete.php?id=<?= urlencode($app['application_id']) ?>" class="btn btn-sm" style="padding: 4px 8px; font-size: 0.8rem; background-color: var(--danger-color); color: white;" onclick="return confirm('Are you sure you want to delete this application?')">🗑️</a>
                                </div>
                            </td>
                            <?php else: ?>
                            <td>
                                <a href="view.php?id=<?= urlencode($app['application_id']) ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 0.8rem;">👁️ View</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">← Prev</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Blur Overlay -->
    <div id="blurOverlay"></div>

    <!-- Add a popup for entering a reason -->
    <div id="reasonPopup">
        <h3>Provide a Reason</h3>
        <textarea id="reasonInput" rows="4"></textarea>
        <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
            <button id="cancelReason" class="btn btn-secondary">Cancel</button>
            <button id="submitReason" class="btn btn-primary">Submit</button>
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

        // Bulk Actions Functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const appCheckboxes = document.querySelectorAll('.app-checkbox');
        const selectedCount = document.getElementById('selectedCount');
        const bulkSubmit = document.getElementById('bulkSubmit');
        const bulkStatus = document.getElementById('bulkStatus');
        const bulkAction = document.getElementById('bulkAction');
        const bulkForm = document.getElementById('bulkForm');

        // Select All functionality
        selectAllCheckbox.addEventListener('change', function() {
            appCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });

        // Individual checkbox change
        appCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedCount();
                // Update select all checkbox state
                const checkedCount = document.querySelectorAll('.app-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === appCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < appCheckboxes.length;
            });
        });

        // Update selected count display
        function updateSelectedCount() {
            const checkedCount = document.querySelectorAll('.app-checkbox:checked').length;
            selectedCount.textContent = `${checkedCount} selected`;
            bulkSubmit.disabled = checkedCount === 0 || bulkStatus.value === '';
        }

        // Enable/disable bulk submit based on status selection
        bulkStatus.addEventListener('change', function() {
            updateSelectedCount();
        });

        // Bulk submit with confirmation
        bulkSubmit.addEventListener('click', function(e) {
            e.preventDefault();
            const checkedCount = document.querySelectorAll('.app-checkbox:checked').length;
            const action = bulkAction.value;

            if (checkedCount === 0) {
                alert('Please select at least one application.');
                return;
            }

            if (action === '') {
                alert('Please select an action.');
                return;
            }

            if (action === 'bulk_delete') {
                if (confirm(`Are you sure you want to delete ${checkedCount} selected application(s)? This action cannot be undone.`)) {
                    bulkForm.submit();
                }
            } else if (action === 'bulk_status') {
                const statusText = bulkStatus.options[bulkStatus.selectedIndex].text;
                if (confirm(`Are you sure you want to ${statusText.toLowerCase()} for ${checkedCount} selected application(s)?`)) {
                    bulkForm.submit();
                }
            }
        });

        // Reason Popup
        const reasonPopup = document.getElementById('reasonPopup');
        const blurOverlay = document.getElementById('blurOverlay');
        const reasonInput = document.getElementById('reasonInput');
        const cancelReason = document.getElementById('cancelReason');
        const submitReason = document.getElementById('submitReason');

        let currentApplicationId = null;
        let currentNewStatus = null;

        function openReasonPopup(applicationId, newStatus) {
            currentApplicationId = applicationId;
            currentNewStatus = newStatus;
            reasonInput.value = '';
            reasonPopup.style.display = 'block';
            blurOverlay.style.display = 'block';
        }

        cancelReason.addEventListener('click', () => {
            reasonPopup.style.display = 'none';
            blurOverlay.style.display = 'none';
        });

        submitReason.addEventListener('click', () => {
            const reason = reasonInput.value.trim() || 'N/A';
            reasonPopup.style.display = 'none';
            blurOverlay.style.display = 'none';

            // Submit the form with the reason
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const applicationIdInput = document.createElement('input');
            applicationIdInput.name = 'application_id';
            applicationIdInput.value = currentApplicationId;
            form.appendChild(applicationIdInput);

            const newStatusInput = document.createElement('input');
            newStatusInput.name = 'new_status';
            newStatusInput.value = currentNewStatus;
            form.appendChild(newStatusInput);

            const reasonInputField = document.createElement('input');
            reasonInputField.name = 'reason';
            reasonInputField.value = reason;
            form.appendChild(reasonInputField);

            const updateStatusInput = document.createElement('input');
            updateStatusInput.name = 'update_status';
            updateStatusInput.value = '1';
            form.appendChild(updateStatusInput);

            document.body.appendChild(form);
            form.submit();
        });

        // Initialize
        updateSelectedCount();
    </script>
    
    <?php
    // Close database connection at the end
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
