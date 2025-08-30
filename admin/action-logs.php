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

// Include navbar component
include('navbar.php');

// Pagination settings
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter settings
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($action_filter)) {
    $where_conditions[] = "action_type LIKE ?";
    $params[] = "%$action_filter%";
    $param_types .= 's';
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
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--container-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
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
        .action-maintenance { background: #6f42c1; color: white; }
        .action-settings { background: #fd7e14; color: white; }
        .action-file { background: #20c997; color: white; }
        .action-default { background: #6c757d; color: white; }

        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
        }

        .timestamp {
            font-size: 0.9rem;
            white-space: nowrap;
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
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('action-logs.php'); ?>
    <div class="container">
        <div class="page-header">
            <h1>📊 Action Logs</h1>
            <p>Monitor all admin activities and system actions</p>
        </div>

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
            </form>
        </div>

        <!-- Action Logs Table -->
        <table class="logs-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th>Details</th>
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
                                } elseif (strpos($log['action_type'], 'APPLICATION') !== false) {
                                    $action_class = 'action-application';
                                } elseif (strpos($log['action_type'], 'MAINTENANCE') !== false) {
                                    $action_class = 'action-maintenance';
                                } elseif (strpos($log['action_type'], 'SETTINGS') !== false) {
                                    $action_class = 'action-settings';
                                } elseif (strpos($log['action_type'], 'FILE') !== false) {
                                    $action_class = 'action-file';
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?>">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?= $i ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?>">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
