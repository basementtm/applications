<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is Emma (owner)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_username'] !== 'emma') {
    header("Location: login.php");
    exit();
}

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
    <title>Action Log - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-pink: #ff69b4;
            --secondary-pink: #ff1493;
            --light-pink: #ffb6c1;
            --bg-dark: #1a1a1a;
            --card-dark: #2d2d2d;
            --text-light: #ffffff;
            --border-dark: #404040;
        }

        body {
            background: linear-gradient(135deg, var(--primary-pink), var(--secondary-pink));
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        body.dark-theme {
            background: linear-gradient(135deg, #2d1b3d, #1a1a2e);
            color: var(--text-light);
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin: 2rem;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .dark-theme .main-container {
            background: rgba(45, 45, 45, 0.95);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .navbar {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
            border-radius: 15px;
        }

        .dark-theme .navbar {
            background: rgba(45, 45, 45, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            color: white !important;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            margin: 0 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
        }

        .btn-pink {
            background: linear-gradient(45deg, var(--primary-pink), var(--secondary-pink));
            border: none;
            color: white;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-pink:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 105, 180, 0.4);
            color: white;
        }

        .table {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .dark-theme .table {
            background: rgba(45, 45, 45, 0.9);
            color: var(--text-light);
        }

        .table th {
            background: linear-gradient(45deg, var(--primary-pink), var(--secondary-pink));
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table td {
            border-color: rgba(0, 0, 0, 0.1);
            padding: 0.8rem 1rem;
            vertical-align: middle;
        }

        .dark-theme .table td {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .action-login-success { background: #28a745; color: white; }
        .action-login-failed { background: #dc3545; color: white; }
        .action-user { background: #17a2b8; color: white; }
        .action-application { background: #ffc107; color: #000; }
        .action-maintenance { background: #6f42c1; color: white; }
        .action-settings { background: #fd7e14; color: white; }
        .action-file { background: #20c997; color: white; }
        .action-default { background: #6c757d; color: white; }

        .filter-section {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .dark-theme .filter-section {
            background: rgba(45, 45, 45, 0.8);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid rgba(255, 105, 180, 0.3);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-pink);
            box-shadow: 0 0 10px rgba(255, 105, 180, 0.3);
        }

        .dark-theme .form-control,
        .dark-theme .form-select {
            background: var(--card-dark);
            border-color: var(--border-dark);
            color: var(--text-light);
        }

        .dark-theme .form-control:focus,
        .dark-theme .form-select:focus {
            background: var(--card-dark);
            border-color: var(--primary-pink);
            color: var(--text-light);
        }

        .pagination .page-link {
            color: var(--primary-pink);
            border: 1px solid rgba(255, 105, 180, 0.3);
            border-radius: 8px;
            margin: 0 2px;
        }

        .pagination .page-link:hover {
            background: var(--primary-pink);
            color: white;
            border-color: var(--primary-pink);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-pink);
            border-color: var(--primary-pink);
        }

        .dark-theme .pagination .page-link {
            background: var(--card-dark);
            color: var(--text-light);
            border-color: var(--border-dark);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background: rgba(0, 0, 0, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
        }

        .dark-theme .ip-address {
            background: rgba(255, 255, 255, 0.1);
        }

        .timestamp {
            font-size: 0.9rem;
            color: #666;
        }

        .dark-theme .timestamp {
            color: #ccc;
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Theme">
        <i class="bi bi-moon-fill" id="theme-icon"></i>
    </button>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="owner.php">
                <i class="bi bi-shield-check"></i> Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="owner.php">
                    <i class="bi bi-house-door"></i> Dashboard
                </a>
                <a class="nav-link active" href="action-logs.php">
                    <i class="bi bi-activity"></i> Action Logs
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-1">
                    <i class="bi bi-activity"></i> Action Logs
                </h1>
                <p class="text-muted mb-0">Monitor all system activities and user actions</p>
            </div>
            <div class="text-end">
                <small class="text-muted">Total Records: <?= number_format($total_records) ?></small>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="action_filter" class="form-label">Action Type</label>
                    <select class="form-select" id="action_filter" name="action_filter">
                        <option value="">All Actions</option>
                        <?php foreach ($action_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $action_filter === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user_filter" class="form-label">User</label>
                    <select class="form-select" id="user_filter" name="user_filter">
                        <option value="">All Users</option>
                        <?php foreach ($usernames as $username): ?>
                            <option value="<?= htmlspecialchars($username) ?>" <?= $user_filter === $username ? 'selected' : '' ?>>
                                <?= htmlspecialchars($username) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_filter" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date_filter" name="date_filter" value="<?= htmlspecialchars($date_filter) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-pink me-2">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="action-logs.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Action Logs Table -->
        <div class="table-responsive">
            <table class="table">
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
                                <td>
                                    <div class="timestamp">
                                        <?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?>
                                    </div>
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
                                        <small class="text-muted">
                                            <?= htmlspecialchars($log['target_type']) ?> #<?= $log['target_id'] ?>
                                        </small>
                                    <?php elseif ($log['target_type']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($log['target_type']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ip-address"><?= htmlspecialchars($log['ip_address']) ?></span>
                                </td>
                                <td>
                                    <?php if ($log['additional_data']): ?>
                                        <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#details-<?= $log['id'] ?>" aria-expanded="false">
                                            <i class="bi bi-info-circle"></i>
                                        </button>
                                        <div class="collapse mt-2" id="details-<?= $log['id'] ?>">
                                            <div class="card card-body">
                                                <small><?= htmlspecialchars($log['additional_data']) ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                <i class="bi bi-inbox"></i> No action logs found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Action logs pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&action_filter=<?= urlencode($action_filter) ?>&user_filter=<?= urlencode($user_filter) ?>&date_filter=<?= urlencode($date_filter) ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark theme functionality
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const isDark = document.body.classList.contains('dark-theme');
            const icon = document.getElementById('theme-icon');
            
            if (isDark) {
                icon.className = 'bi bi-sun-fill';
                localStorage.setItem('darkTheme', 'true');
            } else {
                icon.className = 'bi bi-moon-fill';
                localStorage.setItem('darkTheme', 'false');
            }
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('darkTheme');
            if (savedTheme === 'true') {
                document.body.classList.add('dark-theme');
                document.getElementById('theme-icon').className = 'bi bi-sun-fill';
            }
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
