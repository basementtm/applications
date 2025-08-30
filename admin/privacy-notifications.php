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
    // Create new privacy notification
    if (isset($_POST['create_notification'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $required_until = !empty($_POST['required_until']) ? $_POST['required_until'] : null;
        $created_by = $_SESSION['admin_username'];
        
        if (empty($title) || empty($content)) {
            $error = "Both title and content are required.";
        } else {
            $sql = "INSERT INTO privacy_notifications (title, message, created_by, required_until) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $title, $content, $created_by, $required_until);
            
            if ($stmt->execute()) {
                $notification_id = $conn->insert_id;
                $message = "Privacy notification created successfully!";
                
                // Log the action
                logAction('PRIVACY_NOTIFICATION_CREATED', "Created new privacy notification: $title", 'privacy_notification', $notification_id);
            } else {
                $error = "Error creating notification: " . $conn->error;
            }
            
            $stmt->close();
        }
    }
    
    // Deactivate notification
    if (isset($_POST['deactivate_notification'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        $sql = "UPDATE privacy_notifications SET active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        
        if ($stmt->execute()) {
            $message = "Notification deactivated successfully!";
            
            // Log the action
            logAction('PRIVACY_NOTIFICATION_DEACTIVATED', "Deactivated privacy notification ID: $notification_id", 'privacy_notification', $notification_id);
        } else {
            $error = "Error deactivating notification: " . $conn->error;
        }
        
        $stmt->close();
    }
    
    // Activate notification
    if (isset($_POST['activate_notification'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        // First, deactivate all existing notifications
        $conn->query("UPDATE privacy_notifications SET active = 0");
        
        // Then activate the selected one
        $sql = "UPDATE privacy_notifications SET active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        
        if ($stmt->execute()) {
            $message = "Notification activated successfully!";
            
            // Log the action
            logAction('PRIVACY_NOTIFICATION_ACTIVATED', "Activated privacy notification ID: $notification_id", 'privacy_notification', $notification_id);
        } else {
            $error = "Error activating notification: " . $conn->error;
        }
        
        $stmt->close();
    }
    
    // Delete notification
    if (isset($_POST['delete_notification'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        // Get notification details for logging
        $get_notification = $conn->prepare("SELECT title FROM privacy_notifications WHERE id = ?");
        $get_notification->bind_param("i", $notification_id);
        $get_notification->execute();
        $notification_result = $get_notification->get_result();
        $notification_data = $notification_result->fetch_assoc();
        $notification_title = $notification_data['title'] ?? "Unknown";
        $get_notification->close();
        
        // Delete the notification
        $sql = "DELETE FROM privacy_notifications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        
        if ($stmt->execute()) {
                // Also delete related dismissals if the table exists
                $dismissals_table_exists = $conn->query("SHOW TABLES LIKE 'privacy_notification_dismissals'")->num_rows > 0;
                if ($dismissals_table_exists) {
                    $conn->query("DELETE FROM privacy_notification_dismissals WHERE notification_id = $notification_id");
                }            $message = "Notification deleted successfully!";
            
            // Log the action
            logAction('PRIVACY_NOTIFICATION_DELETED', "Deleted privacy notification: $notification_title", 'privacy_notification', $notification_id);
        } else {
            $error = "Error deleting notification: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get all notifications
$notifications = [];
$notifications_result = $conn->query("SELECT * FROM privacy_notifications ORDER BY created_at DESC");
if ($notifications_result && $notifications_result->num_rows > 0) {
    while ($row = $notifications_result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Count dismissals for each notification
// First check if the dismissals table exists
$dismissals_table_exists = $conn->query("SHOW TABLES LIKE 'privacy_notification_dismissals'")->num_rows > 0;

foreach ($notifications as &$notification) {
    if ($dismissals_table_exists) {
        $notification_id = $notification['id'];
        $count_result = $conn->query("SELECT COUNT(*) as dismiss_count FROM privacy_notification_dismissals WHERE notification_id = $notification_id");
        $dismiss_count = $count_result ? $count_result->fetch_assoc()['dismiss_count'] : 0;
        $notification['dismiss_count'] = $dismiss_count;
    } else {
        // If table doesn't exist, set count to 0
        $notification['dismiss_count'] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy Notifications - Admin Dashboard</title>
    <style>
        <?= getNavbarCSS() ?>
        
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

        /* Body styling is already included in navbar CSS */

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        .form-group textarea {
            height: 150px;
            resize: vertical;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary-pink);
            color: white;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--primary-pink);
            color: white;
        }

        tr:nth-child(even) {
            background-color: rgba(255, 20, 147, 0.05);
        }

        .notification-content {
            max-height: 100px;
            overflow-y: auto;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin-top: 5px;
        }

        .active-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
        }

        .active-tag.active {
            background-color: var(--success-color);
            color: white;
        }

        .active-tag.inactive {
            background-color: var(--danger-color);
            color: white;
        }

        /* Theme switcher styling is already included in navbar CSS */

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('privacy-notifications.php'); // Set the current page ?>

    <div class="container">
        <div class="section">
            <h3>🔔 Privacy Policy Notifications</h3>
            <p>Create and manage privacy policy update notifications that will be shown to users.</p>
            
            <?php if (!$dismissals_table_exists): ?>
                <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px; color: #856404;">
                    <strong>⚠️ Warning:</strong> The dismissals tracking table does not exist yet. 
                    <a href="create-privacy-notification-dismissals-table.php" style="color: #856404; text-decoration: underline; font-weight: bold;">
                        Click here to create it.
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="section">
            <h3>📝 Create New Notification</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" required placeholder="e.g. Privacy Policy Updated - August 2025">
                </div>
                
                <div class="form-group">
                    <label for="content">Notification Content:</label>
                    <textarea id="content" name="content" required placeholder="Explain what has changed in the privacy policy..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="required_until">Required Until (Optional):</label>
                    <input type="date" id="required_until" name="required_until">
                    <small style="display: block; margin-top: 5px;">If set, users must acknowledge this notification until this date.</small>
                </div>
                
                <button type="submit" name="create_notification" class="btn btn-primary">Create Notification</button>
            </form>
        </div>

        <div class="section">
            <h3>📋 Existing Notifications</h3>
            
            <?php if (empty($notifications)): ?>
                <p>No notifications have been created yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Required Until</th>
                            <th>Dismissals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notification): ?>
                            <tr>
                                <td><?= $notification['id'] ?></td>
                                <td><?= htmlspecialchars($notification['title']) ?></td>
                                <td>
                                    <div class="notification-content">
                                        <?= nl2br(htmlspecialchars($notification['message'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($notification['active']): ?>
                                        <span class="active-tag active">Active</span>
                                    <?php else: ?>
                                        <span class="active-tag inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('Y-m-d H:i', strtotime($notification['created_at'])) ?>
                                    <br>
                                    <small>by <?= htmlspecialchars($notification['created_by']) ?></small>
                                </td>
                                <td>
                                    <?= $notification['required_until'] ? date('Y-m-d', strtotime($notification['required_until'])) : 'Not set' ?>
                                </td>
                                <td>
                                    <?= number_format($notification['dismiss_count']) ?>
                                </td>
                                <td>
                                    <?php if (!$notification['active']): ?>
                                        <form method="POST" style="display: inline; margin-right: 5px;">
                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                            <button type="submit" name="activate_notification" class="btn btn-success" onclick="return confirm('Activate this notification? This will deactivate any currently active notification.')">Activate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline; margin-right: 5px;">
                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                            <button type="submit" name="deactivate_notification" class="btn btn-warning" onclick="return confirm('Deactivate this notification?')">Deactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                        <button type="submit" name="delete_notification" class="btn btn-danger" onclick="return confirm('Are you sure you want to permanently delete this notification?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme Switcher
        const themeSwitcher = document.getElementById('themeSwitcher');
        const body = document.body;
        
        // Check for saved theme preference
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            body.setAttribute('data-theme', 'dark');
            themeSwitcher.textContent = '☀️';
        }
        
        // Toggle theme
        themeSwitcher.addEventListener('click', () => {
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                themeSwitcher.textContent = '🌙';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                themeSwitcher.textContent = '☀️';
                localStorage.setItem('theme', 'dark');
            }
        });
    </script>
</body>
</html>
