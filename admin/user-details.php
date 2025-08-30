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

// Allow all admin roles to view user details, but restrict actions to owner
$is_owner = ($_SESSION['admin_role'] === 'owner');

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Handle user disable/enable action (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $is_owner) {
    if ($_POST['action'] === 'toggle_status' && isset($_POST['user_id'])) {
        $target_user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'] === '1' ? 1 : 0;
        
        // Prevent owner from disabling themselves
        if ($target_user_id == $_SESSION['admin_id']) {
            $error = "You cannot disable your own account.";
        } else {
            $update_sql = "UPDATE admin_users SET active = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_status, $target_user_id);
            
            if ($update_stmt->execute()) {
                $message = $new_status ? "User has been enabled." : "User has been disabled.";
            } else {
                $error = "Failed to update user status.";
            }
            $update_stmt->close();
        }
    }
}

// Get user details
$sql = "SELECT u.id, u.username, u.email, u.role, u.active, u.created_at, u.last_login, u.two_factor_enabled,
        creator.username as created_by
        FROM admin_users u 
        LEFT JOIN admin_users creator ON u.created_by = creator.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: owner.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Get login attempts for this user (last 50)
$login_sql = "SELECT ip_address, user_agent, attempt_time, success, method, failure_reason 
              FROM login_attempts 
              WHERE username = ? 
              ORDER BY attempt_time DESC 
              LIMIT 50";
$login_stmt = $conn->prepare($login_sql);
$login_stmt->bind_param("s", $user['username']);
$login_stmt->execute();
$login_result = $login_stmt->get_result();
$login_attempts = $login_result->fetch_all(MYSQLI_ASSOC);
$login_stmt->close();

// Get login statistics
$stats_sql = "SELECT 
    COUNT(*) as total_attempts,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_logins,
    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_attempts,
    COUNT(DISTINCT ip_address) as unique_ips,
    MAX(CASE WHEN success = 1 THEN attempt_time END) as last_successful_login
    FROM login_attempts 
    WHERE username = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $user['username']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['username']); ?></title>
    <style>
        :root {
            --bg-color: #ffc0cb;
            --container-bg: #fff0f5;
            --text-color: #333;
            --primary-pink: #ff1493;
            --secondary-pink: #ff69b4;
            --light-pink: #ffb6c1;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --border-color: #ddd;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-color) 0%, var(--light-pink) 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--container-bg);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-pink), var(--secondary-pink));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .content {
            padding: 30px;
        }

        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-pink);
        }

        .info-card h3 {
            margin-top: 0;
            color: var(--primary-pink);
            font-size: 1.4em;
            border-bottom: 2px solid var(--light-pink);
            padding-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-weight: bold;
            color: var(--text-color);
        }

        .info-value {
            color: #666;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-active {
            background: var(--success-color);
            color: white;
        }

        .status-disabled {
            background: var(--danger-color);
            color: white;
        }

        .status-enabled {
            background: var(--success-color);
            color: white;
        }

        .status-disabled-2fa {
            background: var(--warning-color);
            color: white;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            background: var(--primary-pink);
            color: white;
        }

        .role-owner { 
            background: linear-gradient(135deg, #8B008B, #FF1493) !important; 
            color: white !important; 
            border: 2px solid #FFD700;
            box-shadow: 0 2px 8px rgba(139, 0, 139, 0.3);
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-admin { background: var(--secondary-pink) !important; color: white !important; }
        .role-readonly_admin { background: var(--warning-color) !important; color: white !important; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--primary-pink);
            margin: 0;
        }

        .stat-label {
            color: #666;
            margin-top: 10px;
        }

        .login-attempts {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 30px;
        }

        .attempts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .attempts-table th {
            background: var(--light-pink);
            color: var(--text-color);
            padding: 15px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid var(--primary-pink);
        }

        .attempts-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .attempts-table tr:hover {
            background: #f8f9fa;
        }

        .success-attempt {
            color: var(--success-color);
            font-weight: bold;
        }

        .failed-attempt {
            color: var(--danger-color);
            font-weight: bold;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-pink);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-pink);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .actions {
            margin: 30px 0;
            text-align: center;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .user-agent {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-pink);
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>User Details: <?php echo htmlspecialchars($user['username']); ?></h1>
        </div>
        
        <div class="content">
            <?php if ($is_owner): ?>
                <a href="owner.php" class="back-link">← Back to User Management</a>
            <?php else: ?>
                <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="user-info">
                <div class="info-card">
                    <h3>Account Information</h3>
                    <div class="info-row">
                        <span class="info-label">Username:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Role:</span>
                        <span class="info-value">
                            <span class="role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $user['active'] ? 'status-active' : 'status-disabled'; ?>">
                                <?php echo $user['active'] ? 'Active' : 'Disabled'; ?>
                            </span>
                        </span>
                    </div>
                </div>

                <div class="info-card">
                    <h3>Account Details</h3>
                    <div class="info-row">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['created_by'] ?? 'Unknown'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Login:</span>
                        <span class="info-value">
                            <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">2FA Status:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $user['two_factor_enabled'] ? 'status-enabled' : 'status-disabled-2fa'; ?>">
                                <?php echo $user['two_factor_enabled'] ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_attempts'] ?? 0; ?></div>
                    <div class="stat-label">Total Login Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['successful_logins'] ?? 0; ?></div>
                    <div class="stat-label">Successful Logins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['failed_attempts'] ?? 0; ?></div>
                    <div class="stat-label">Failed Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['unique_ips'] ?? 0; ?></div>
                    <div class="stat-label">Unique IP Addresses</div>
                </div>
            </div>

            <?php if ($is_owner && $user['id'] != $_SESSION['admin_id']): ?>
                <div class="actions">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to <?php echo $user['active'] ? 'disable' : 'enable'; ?> this user?');">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $user['active'] ? '0' : '1'; ?>">
                        <button type="submit" class="btn <?php echo $user['active'] ? 'btn-danger' : 'btn-success'; ?>">
                            <?php echo $user['active'] ? 'Disable User' : 'Enable User'; ?>
                        </button>
                    </form>
                </div>
            <?php elseif (!$is_owner): ?>
                <div class="actions">
                    <p style="text-align: center; color: #666; font-style: italic;">
                        Only owners can enable/disable users.
                    </p>
                </div>
            <?php endif; ?>

            <div class="login-attempts">
                <h3>Recent Login Attempts (Last 50)</h3>
                <?php if (empty($login_attempts)): ?>
                    <p>No login attempts recorded for this user.</p>
                <?php else: ?>
                    <table class="attempts-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                                <th>Method</th>
                                <th>Result</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($login_attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i:s A', strtotime($attempt['attempt_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['method']); ?></td>
                                    <td>
                                        <?php if ($attempt['success']): ?>
                                            <span class="success-attempt">Success</span>
                                        <?php else: ?>
                                            <span class="failed-attempt">
                                                Failed
                                                <?php if ($attempt['failure_reason']): ?>
                                                    (<?php echo htmlspecialchars($attempt['failure_reason']); ?>)
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="user-agent" title="<?php echo htmlspecialchars($attempt['user_agent']); ?>">
                                        <?php echo htmlspecialchars($attempt['user_agent']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
