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

// Check if user is Emma (owner) - only Emma can manage IP bans
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

// Include action logger and navbar
require_once 'action_logger.php';
include('navbar.php');

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ban'])) {
        $ip_address = trim($_POST['ip_address']);
        $reason = trim($_POST['reason']);
        $notes = trim($_POST['notes']);
        $admin_username = $_SESSION['admin_username'];
        
        if (!empty($ip_address)) {
            // Validate IP address
            if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
                $stmt = $conn->prepare("INSERT INTO banned_ips (ip_address, reason, banned_by, notes) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $ip_address, $reason, $admin_username, $notes);
                
                if ($stmt->execute()) {
                    $message = "IP address $ip_address has been banned successfully!";
                    
                    // Log the action
                    logAction('IP_BAN_ADDED', "Banned IP address: $ip_address", 'ip_ban', $stmt->insert_id, [
                        'ip_address' => $ip_address,
                        'reason' => $reason,
                        'notes' => $notes
                    ]);
                } else {
                    if ($conn->errno === 1062) { // Duplicate entry
                        $error = "IP address $ip_address is already banned!";
                    } else {
                        $error = "Error banning IP: " . $stmt->error;
                    }
                }
                $stmt->close();
            } else {
                $error = "Invalid IP address format!";
            }
        } else {
            $error = "IP address is required!";
        }
    } elseif (isset($_POST['toggle_ban'])) {
        $ban_id = intval($_POST['ban_id']);
        $new_status = $_POST['new_status'] === '1' ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE banned_ips SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $ban_id);
        
        if ($stmt->execute()) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            $message = "IP ban has been $status_text successfully!";
            
            // Log the action
            logAction('IP_BAN_TOGGLED', "IP ban $status_text (ID: $ban_id)", 'ip_ban', $ban_id, [
                'ban_id' => $ban_id,
                'new_status' => $new_status
            ]);
        } else {
            $error = "Error updating ban status: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['delete_ban'])) {
        $ban_id = intval($_POST['ban_id']);
        
        // Get IP address for logging
        $get_ip_stmt = $conn->prepare("SELECT ip_address FROM banned_ips WHERE id = ?");
        $get_ip_stmt->bind_param("i", $ban_id);
        $get_ip_stmt->execute();
        $ip_result = $get_ip_stmt->get_result();
        $ip_row = $ip_result->fetch_assoc();
        $ip_address = $ip_row['ip_address'] ?? 'unknown';
        $get_ip_stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM banned_ips WHERE id = ?");
        $stmt->bind_param("i", $ban_id);
        
        if ($stmt->execute()) {
            $message = "IP ban has been deleted successfully!";
            
            // Log the action
            logAction('IP_BAN_DELETED', "Deleted IP ban: $ip_address (ID: $ban_id)", 'ip_ban', $ban_id, [
                'ban_id' => $ban_id,
                'ip_address' => $ip_address
            ]);
        } else {
            $error = "Error deleting ban: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all banned IPs
$banned_ips_result = $conn->query("SELECT * FROM banned_ips ORDER BY banned_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Ban Management - Admin Dashboard</title>
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
        
        .container {
            margin: 20px auto;
            max-width: 1200px;
            padding: 20px;
            background: var(--container-bg);
            border-radius: 15px;
            box-shadow: 0 2px 4px var(--shadow-color);
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

        .form-section {
            background: rgba(255, 105, 180, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 105, 180, 0.2);
        }

        .form-section h2 {
            color: var(--primary-pink);
            margin-top: 0;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-color);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--container-bg);
            color: var(--text-color);
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
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

        .btn-primary { background-color: var(--primary-pink); color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-warning { background-color: #ffc107; color: #000; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .bans-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--container-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .bans-table th,
        .bans-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .bans-table th {
            background: var(--primary-pink);
            color: white;
            font-weight: bold;
        }

        .bans-table tr:hover {
            background: rgba(255, 105, 180, 0.1);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-active { background: #dc3545; color: white; }
        .status-inactive { background: #6c757d; color: white; }

        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .no-bans {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
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
            .container {
                margin: 10px;
                padding: 15px;
            }
            
            .actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .actions .btn {
                width: 100%;
                text-align: center;
            }
            
            .theme-switcher {
                width: 45px;
                height: 45px;
                font-size: 18px;
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('ip-ban-management.php'); ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🚫 IP Ban Management</h1>
            <p>Block specific IP addresses from submitting applications</p>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Add New Ban -->
        <div class="form-section">
            <h2>🚫 Add New IP Ban</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="ip_address">IP Address *</label>
                    <input type="text" id="ip_address" name="ip_address" placeholder="192.168.1.1 or 2001:db8::1" required>
                </div>
                <div class="form-group">
                    <label for="reason">Reason for Ban</label>
                    <input type="text" id="reason" name="reason" placeholder="Spam, abuse, etc.">
                </div>
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional information..."></textarea>
                </div>
                <button type="submit" name="add_ban" class="btn btn-danger">🚫 Add IP Ban</button>
            </form>
        </div>

        <!-- Banned IPs List -->
        <div class="form-section">
            <h2>📋 Current IP Bans</h2>
            
            <?php if ($banned_ips_result->num_rows > 0): ?>
                <table class="bans-table">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Banned By</th>
                            <th>Banned At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ban = $banned_ips_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="ip-address"><?= htmlspecialchars($ban['ip_address']) ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $ban['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $ban['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($ban['reason'] ?: 'No reason specified') ?></td>
                                <td><?= htmlspecialchars($ban['banned_by']) ?></td>
                                <td><?= date('Y-m-d H:i:s', strtotime($ban['banned_at'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <!-- Toggle Status -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="ban_id" value="<?= $ban['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $ban['is_active'] ? '0' : '1' ?>">
                                            <button type="submit" name="toggle_ban" 
                                                    class="btn btn-sm <?= $ban['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                                    onclick="return confirm('Are you sure you want to <?= $ban['is_active'] ? 'deactivate' : 'activate' ?> this ban?')">
                                                <?= $ban['is_active'] ? '⏸️ Deactivate' : '▶️ Activate' ?>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete Ban -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="ban_id" value="<?= $ban['id'] ?>">
                                            <button type="submit" name="delete_ban" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to permanently delete this IP ban? This action cannot be undone.')">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($ban['notes']): ?>
                                <tr>
                                    <td colspan="6" style="background: rgba(255, 105, 180, 0.05); font-size: 0.8rem; font-style: italic;">
                                        <strong>Notes:</strong> <?= htmlspecialchars($ban['notes']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-bans">
                    🎉 No IP addresses are currently banned
                </div>
            <?php endif; ?>
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
    </script>
</body>
</html>

<?php
$conn->close();
?>
