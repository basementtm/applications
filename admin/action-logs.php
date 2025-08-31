<?php
session_start();

// Include auth functions for user status checking
require_once 'auth_functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Check if user is still active (not disabled)
checkUserStatus();

// Check if user is Emma (owner) - only Emma can view action logs
if (!isset($_SESSION['admin_username']) || $_SESSION['admin_username'] !== 'emma') {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

include('/var/www/config/db_config.php');

// Create database connection
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle manual log addition
$manual_log_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual_log'])) {
    $action_type = trim($_POST['manual_action_type'] ?? '');
    $action_description = trim($_POST['manual_description'] ?? '');
    $target_type = !empty($_POST['manual_target_type']) ? trim($_POST['manual_target_type']) : null;
    $target_id = !empty($_POST['manual_target_id']) ? trim($_POST['manual_target_id']) : null;
    $additional_notes = !empty($_POST['manual_notes']) ? trim($_POST['manual_notes']) : null;
    
    if (!empty($action_type) && !empty($action_description)) {
        $user_id = $_SESSION['admin_id'] ?? null;
        $username = $_SESSION['admin_username'] ?? 'emma';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Manual Entry';
        
        // Handle proxy/forwarded IPs
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip_address = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        // Prepare additional data
        $additional_data = null;
        if ($additional_notes) {
            $additional_data = json_encode([
                'manual_entry' => true,
                'notes' => $additional_notes,
                'added_by' => $username
            ]);
        } else {
            $additional_data = json_encode(['manual_entry' => true, 'added_by' => $username]);
        }
        
        try {
            $sql = "INSERT INTO action_logs (user_id, username, action_type, action_description, target_type, target_id, ip_address, user_agent, additional_data) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssisss", $user_id, $username, $action_type, $action_description, $target_type, $target_id, $ip_address, $user_agent, $additional_data);
            
            if ($stmt->execute()) {
                $manual_log_message = "Manual log entry added successfully!";
                // Redirect to prevent form resubmission
                header("Location: action-logs.php?manual_added=1");
                exit();
            } else {
                $manual_log_message = "Error adding manual log entry: " . $stmt->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $manual_log_message = "Error adding manual log entry: " . $e->getMessage();
        }
    } else {
        $manual_log_message = "Please fill in both Action Type and Description fields.";
    }
}

// Check for success message from redirect
if (isset($_GET['manual_added'])) {
    $manual_log_message = "Manual log entry added successfully!";
}

// Include navbar component
include('navbar.php');

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter settings
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$hide_visitor_logs = isset($_GET['hide_visitor']) ? true : false;

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($action_filter)) {
    if ($action_filter === 'VISITOR') {
        $where_conditions[] = "action_type LIKE 'VISITOR_%'";
    } else {
        $where_conditions[] = "action_type LIKE ?";
        $params[] = "%$action_filter%";
        $param_types .= 's';
    }
} elseif ($hide_visitor_logs) {
    $where_conditions[] = "action_type NOT LIKE 'VISITOR_%'";
}

if (!empty($user_filter)) {
    $where_conditions[] = "username LIKE ?";
    $params[] = "%$user_filter%";
    $param_types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(timestamp) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM action_logs $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    $total_records = $count_result->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $limit);

// Get action logs
$sql = "SELECT * FROM action_logs $where_clause ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($where_conditions)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // No filters, just add limit and offset
    $stmt = $conn->prepare("SELECT * FROM action_logs ORDER BY timestamp DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Get unique action types for filter dropdown
$action_types_result = $conn->query("SELECT DISTINCT action_type FROM action_logs ORDER BY action_type");
$action_types = [];
while ($row = $action_types_result->fetch_assoc()) {
    $action_types[] = $row['action_type'];
}

// Get unique usernames for filter dropdown
$usernames_result = $conn->query("SELECT DISTINCT username FROM action_logs WHERE username IS NOT NULL ORDER BY username");
$usernames = [];
while ($row = $usernames_result->fetch_assoc()) {
    $usernames[] = $row['username'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Logs - Admin Dashboard</title>
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
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            line-height: 1.6;
        }

        <?= getNavbarCSS() ?>

        /* Additional styles for action logs page */
        .container {
            margin: 20px auto;
            max-width: 1200px;
            padding: 20px;
            background: var(--container-bg);
            border-radius: 15px;
            box-shadow: 0 2px 4px var(--shadow-color);
            backdrop-filter: blur(10px);
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-pink);
        }

        .page-header h1 {
            color: var(--primary-pink);
            margin: 0 0 10px 0;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .page-header p {
            color: var(--text-color);
            opacity: 0.8;
            margin: 0;
            font-size: 1.1rem;
        }

        .filters {
            background: rgba(255, 105, 180, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 105, 180, 0.2);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-color);
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--container-bg);
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-pink);
        }

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
            background-color: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .logs-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--container-bg);
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .logs-table th {
            background: var(--primary-pink);
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .logs-table tr:hover {
            background: rgba(255, 105, 180, 0.1);
        }

        .action-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }

        .action-login-success { background: #28a745; color: white; }
        .action-login-failed { background: #dc3545; color: white; }
        .action-user { background: #17a2b8; color: white; }
        .action-application { background: #ffc107; color: #000; }
        .action-application-submitted { background: #28a745; color: white; }
        .action-application-status { background: #17a2b8; color: white; }
                        .action-maintenance { background: #6f42c1; color: white; }
                        .action-settings { background: #fd7e14; color: white; }
                        .action-file { background: #20c997; color: white; }
                        .action-visitor { background: #3498db; color: white; }
                        .action-default { background: #6c757d; color: white; }        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .timestamp {
            font-size: 0.9rem;
            white-space: nowrap;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px 0;
        }

        .pagination a {
            padding: 8px 12px;
            text-decoration: none;
            color: var(--primary-pink);
            border: 1px solid var(--primary-pink);
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .pagination a:hover,
        .pagination a.active {
            background: var(--primary-pink);
            color: white;
        }

        .pagination a.disabled {
            color: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
        }

        .stats-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(255, 105, 180, 0.1);
            border-radius: 5px;
            font-weight: bold;
        }

        .theme-switcher {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--primary-pink);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .theme-switcher:hover {
            transform: scale(1.1);
        }

        .no-logs {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .details-toggle {
            background: none;
            border: 1px solid var(--primary-pink);
            color: var(--primary-pink);
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .details-content {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 105, 180, 0.1);
            border-radius: 5px;
            font-size: 0.85rem;
            border-left: 3px solid var(--primary-pink);
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
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

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--container-bg);
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            background-color: var(--primary-pink);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal form {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-pink);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('action-logs.php'); ?>
    <div class="container">
        <div class="page-header">
            <h1>📊 Action Logs</h1>
            <p>Monitor all admin activities and system actions</p>
            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
                <button onclick="showAddLogModal()" class="btn btn-primary">➕ Add Manual Log</button>
                <a href="?action_filter=VISITOR" class="btn" style="background-color: #3498db; color: white;">👀 View Visitor Logs</a>
                <a href="?hide_visitor=1" class="btn" style="background-color: #e74c3c; color: white;">🚫 Hide Visitor Logs</a>
                <a href="action-logs.php" class="btn btn-secondary">🔄 Show All Logs</a>
            </div>
        </div>

        <?php if (!empty($manual_log_message)): ?>
            <div class="message <?= strpos($manual_log_message, 'Error') !== false ? 'error' : 'success' ?>">
                <?= htmlspecialchars($manual_log_message) ?>
            </div>
        <?php endif; ?>

        <div class="stats-info">
            Total Records: <?= number_format($total_records) ?>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="action_filter">Action Type</label>
                        <select id="action_filter" name="action_filter">
                            <option value="">All Actions</option>
                            <option value="VISITOR" <?= $action_filter === 'VISITOR' ? 'selected' : '' ?>>Visitor Logs</option>
                            <?php foreach ($action_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $action_filter === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="user_filter">User</label>
                        <select id="user_filter" name="user_filter">
                            <option value="">All Users</option>
                            <?php foreach ($usernames as $username): ?>
                                <option value="<?= htmlspecialchars($username) ?>" <?= $user_filter === $username ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($username) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_filter">Date</label>
                        <input type="date" id="date_filter" name="date_filter" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">🔍 Filter</button>
                            <a href="action-logs.php" class="btn btn-secondary">✖ Clear</a>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 10px; display: flex; align-items: center;">
                    <input type="checkbox" id="hide_visitor" name="hide_visitor" value="1" <?= $hide_visitor_logs ? 'checked' : '' ?> style="margin-right: 8px;">
                    <label for="hide_visitor" style="margin-bottom: 0; cursor: pointer;">Hide visitor logs</label>
                </div>
            </form>
        </div>

        <!-- Action Logs Table -->
        <div style="overflow-x: auto; width: 100%;">
        <table class="logs-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Timestamp</th>
                    <th style="width: 10%;">User</th>
                    <th style="width: 18%;">Action</th>
                    <th style="width: 30%;">Description</th>
                    <th style="width: 10%;">Target</th>
                    <th style="width: 12%;">IP Address</th>
                    <th style="width: 8%;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="timestamp">
                                <?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['username'] ?: 'System') ?></strong>
                            </td>
                            <td>
                                <?php
                                $action_class = 'action-default';
                                if (strpos($log['action_type'], 'LOGIN') !== false) {
                                    $action_class = strpos($log['action_type'], 'SUCCESS') !== false ? 'action-login-success' : 'action-login-failed';
                                } elseif (strpos($log['action_type'], 'USER') !== false) {
                                    $action_class = 'action-user';
                                } elseif (strpos($log['action_type'], 'APPLICATION_SUBMITTED') !== false) {
                                    $action_class = 'action-application-submitted';
                                } elseif (strpos($log['action_type'], 'APPLICATION_STATUS') !== false) {
                                    $action_class = 'action-application-status';
                                } elseif (strpos($log['action_type'], 'APPLICATION') !== false) {
                                    $action_class = 'action-application';
                                } elseif (strpos($log['action_type'], 'MAINTENANCE') !== false) {
                                    $action_class = 'action-maintenance';
                                } elseif (strpos($log['action_type'], 'SETTINGS') !== false) {
                                    $action_class = 'action-settings';
                                } elseif (strpos($log['action_type'], 'FILE') !== false) {
                                    $action_class = 'action-file';
                                } elseif (strpos($log['action_type'], 'VISITOR') !== false) {
                                    $action_class = 'action-visitor';
                                }
                                ?>
                                <span class="action-badge <?= $action_class ?>">
                                    <?= htmlspecialchars($log['action_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($log['action_description']) ?></td>
                            <td>
                                <?php if ($log['target_type'] && $log['target_id']): ?>
                                    <small style="opacity: 0.7;">
                                        <?= htmlspecialchars($log['target_type']) ?> #<?= $log['target_id'] ?>
                                    </small>
                                <?php elseif ($log['target_type']): ?>
                                    <small style="opacity: 0.7;"><?= htmlspecialchars($log['target_type']) ?></small>
                                <?php else: ?>
                                    <span style="opacity: 0.5;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ip-address"><?= htmlspecialchars($log['ip_address']) ?></span>
                            </td>
                            <td>
                                <?php if ($log['additional_data']): ?>
                                    <button class="details-toggle" onclick="toggleDetails('details-<?= $log['id'] ?>')">
                                        📋 View
                                    </button>
                                    <div id="details-<?= $log['id'] ?>" class="details-content" style="display: none;">
                                        <?= htmlspecialchars($log['additional_data']) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="opacity: 0.5;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-logs">
                            📝 No action logs found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?><?= $hide_visitor_logs ? '&hide_visitor=1' : '' ?>">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?= $i ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?><?= $hide_visitor_logs ? '&hide_visitor=1' : '' ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?><?= $hide_visitor_logs ? '&hide_visitor=1' : '' ?>">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Manual Log Addition Modal -->
    <div id="addLogModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Add Manual Log Entry</h3>
                <span class="close" onclick="hideAddLogModal()">&times;</span>
            </div>
            <form method="POST" action="action-logs.php" id="addLogForm">
                <input type="hidden" name="add_manual_log" value="1">
                
                <div class="form-group">
                    <label for="manual_action_type">Action Type:</label>
                    <input type="text" id="manual_action_type" name="manual_action_type" required 
                           placeholder="e.g., MANUAL_SYSTEM_NOTE, MANUAL_MAINTENANCE, etc.">
                </div>
                
                <div class="form-group">
                    <label for="manual_description">Description:</label>
                    <textarea id="manual_description" name="manual_description" required 
                              placeholder="Describe what action was taken or what this log represents..." rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="manual_target_type">Target Type (optional):</label>
                    <select id="manual_target_type" name="manual_target_type">
                        <option value="">None</option>
                        <option value="application">Application</option>
                        <option value="admin_user">Admin User</option>
                        <option value="system">System</option>
                        <option value="settings">Settings</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="security">Security</option>
                        <option value="banner">Banner</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="manual_target_id">Target ID (optional):</label>
                    <input type="text" id="manual_target_id" name="manual_target_id" 
                           placeholder="e.g., application ID, user ID, etc.">
                </div>
                
                <div class="form-group">
                    <label for="manual_notes">Additional Notes (optional):</label>
                    <textarea id="manual_notes" name="manual_notes" 
                              placeholder="Any additional context or notes..." rows="2"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">💾 Add Log Entry</button>
                    <button type="button" class="btn btn-secondary" onclick="hideAddLogModal()">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Dark theme functionality
        function toggleTheme() {
            const body = document.body;
            const themeSwitcher = document.getElementById('themeSwitcher');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                themeSwitcher.textContent = '🌙';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                themeSwitcher.textContent = '☀️';
                localStorage.setItem('theme', 'dark');
            }
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            const themeSwitcher = document.getElementById('themeSwitcher');
            
            if (savedTheme === 'dark') {
                body.setAttribute('data-theme', 'dark');
                themeSwitcher.textContent = '☀️';
            }
        });

        // Theme switcher click event
        document.getElementById('themeSwitcher').addEventListener('click', toggleTheme);

        // Toggle details function
        function toggleDetails(elementId) {
            const element = document.getElementById(elementId);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }

        // Add Log Modal functions
        function showAddLogModal() {
            document.getElementById('addLogModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function hideAddLogModal() {
            document.getElementById('addLogModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('addLogModal');
            if (event.target === modal) {
                hideAddLogModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideAddLogModal();
            }
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
