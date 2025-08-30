<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['admin_username'];
$message = '';
$message_type = '';

// Fetch current user settings
$sql = "SELECT username, email, two_factor_enabled, created_at FROM admin_users WHERE username = ? AND active = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_email':
            $new_email = trim($_POST['email'] ?? '');
            if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $update_sql = "UPDATE admin_users SET email = ? WHERE username = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $new_email, $username);
                if ($update_stmt->execute()) {
                    $user_data['email'] = $new_email;
                    $message = "Email updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating email.";
                    $message_type = "error";
                }
                $update_stmt->close();
            } else {
                $message = "Please enter a valid email address.";
                $message_type = "error";
            }
            break;
            
        case 'toggle_2fa':
            $enable_2fa = $_POST['enable_2fa'] === '1';
            
            if ($enable_2fa && empty($user_data['two_factor_secret'])) {
                // Generate new secret when enabling 2FA
                $secret = generateRandomSecret();
                $update_sql = "UPDATE admin_users SET two_factor_enabled = ?, two_factor_secret = ? WHERE username = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("iss", $enable_2fa, $secret, $username);
            } else {
                // Just toggle the enabled status
                $update_sql = "UPDATE admin_users SET two_factor_enabled = ? WHERE username = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("is", $enable_2fa, $username);
            }
            
            if ($update_stmt->execute()) {
                $user_data['two_factor_enabled'] = $enable_2fa;
                if (isset($secret)) {
                    $user_data['two_factor_secret'] = $secret;
                }
                $message = $enable_2fa ? "2FA enabled successfully! Please complete setup." : "2FA disabled successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating 2FA settings.";
                $message_type = "error";
            }
            $update_stmt->close();
            break;
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - Admin</title>
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
            min-height: 100vh;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .header {
            background-color: var(--container-bg);
            padding: 15px 20px;
            box-shadow: 0 2px 5px var(--shadow-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .header h1 {
            color: var(--primary-pink);
            font-size: 1.8rem;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }

        .settings-section {
            background-color: var(--container-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .section-title {
            color: var(--primary-pink);
            font-size: 1.4rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--primary-pink);
        }

        input[type="email"], input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-pink);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-info {
            flex: 1;
        }

        .setting-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .setting-description {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
        }

        .status-enabled {
            background-color: var(--success-color);
            color: white;
        }

        .status-disabled {
            background-color: var(--border-color);
            color: var(--text-color);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
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

        [data-theme="dark"] .message.success {
            background-color: #1e4620;
            color: #4caf50;
        }

        [data-theme="dark"] .message.error {
            background-color: #4a2c2a;
            color: #ff6b6b;
        }

        .passkey-list {
            margin-top: 15px;
        }

        .passkey-item {
            background-color: var(--input-bg);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .qr-code-container {
            text-align: center;
            padding: 20px;
            background-color: var(--input-bg);
            border-radius: 10px;
            margin: 15px 0;
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
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .setting-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <div class="container">
        <div class="header">
            <h1>⚙️ User Settings</h1>
            <div class="header-actions">
                <span>Logged in as: <strong><?= htmlspecialchars($username) ?></strong></span>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Account Settings -->
            <div class="settings-section">
                <h2 class="section-title">👤 Account Settings</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Update Email</button>
                </form>

                <div style="margin-top: 30px;">
                    <h3 style="color: var(--primary-pink); margin-bottom: 15px;">Account Information</h3>
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-title">Username</div>
                            <div class="setting-description"><?= htmlspecialchars($username) ?></div>
                        </div>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-title">Account Created</div>
                            <div class="setting-description"><?= date('F j, Y', strtotime($user_data['created_at'])) ?></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <a href="change-password.php" class="btn btn-warning">🔐 Change Password</a>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="settings-section">
                <h2 class="section-title">🔒 Security Settings</h2>
                
                <!-- Two-Factor Authentication -->
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-title">Two-Factor Authentication</div>
                        <div class="setting-description">Add an extra layer of security with TOTP authentication</div>
                    </div>
                    <div>
                        <span class="status-badge <?= $user_data['two_factor_enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                            <?= $user_data['two_factor_enabled'] ? 'Enabled' : 'Disabled' ?>
                        </span>
                        <form method="POST" style="display: inline; margin-left: 10px;">
                            <input type="hidden" name="action" value="toggle_2fa">
                            <input type="hidden" name="enable_2fa" value="<?= $user_data['two_factor_enabled'] ? '0' : '1' ?>">
                            <button type="submit" class="btn <?= $user_data['two_factor_enabled'] ? 'btn-danger' : 'btn-success' ?>" onclick="return confirm('<?= $user_data['two_factor_enabled'] ? 'Disable' : 'Enable' ?> 2FA?')">
                                <?= $user_data['two_factor_enabled'] ? '❌ Disable' : '✅ Enable' ?>
                            </button>
                        </form>
                    </div>
                </div>

        <?php if ($user_data['two_factor_enabled'] && !empty($user_data['two_factor_secret'])): ?>
            <?php
            // Check if 2FA setup is complete by checking for backup codes
            $setup_complete = false;
            $backup_check_sql = "SELECT COUNT(*) as count FROM two_factor_backup_codes WHERE username = ?";
            $backup_check_stmt = $conn->prepare($backup_check_sql);
            $backup_check_stmt->bind_param("s", $username);
            $backup_check_stmt->execute();
            $backup_check_result = $backup_check_stmt->get_result();
            $backup_count = $backup_check_result->fetch_assoc()['count'];
            $backup_check_stmt->close();
            $setup_complete = $backup_count > 0;
            ?>
            
            <?php if (!$setup_complete): ?>
            <div class="qr-code-container">
                <h4>📱 Complete 2FA Setup</h4>
                <p>2FA is enabled but setup is incomplete. Click below to finish setup:</p>
                <a href="setup-2fa.php" class="btn btn-warning">⚠️ Complete 2FA Setup</a>
            </div>
            <?php else: ?>
            <div class="qr-code-container">
                <h4>✅ 2FA Setup Complete</h4>
                <p>Two-factor authentication is active and configured.</p>
                <a href="setup-2fa.php" class="btn btn-info">� Manage 2FA</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
            </div>
        </div>
    </div>

    <?php $conn->close(); ?>

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
</body>
</html>

<?php
// Helper function for generating random secret
function generateRandomSecret($length = 20) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

// Helper function for base32 encoding (simple implementation)
function base32_encode($data) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $remainder = 0;
    $remainderSize = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $b = ord($data[$i]);
        $remainder = ($remainder << 8) | $b;
        $remainderSize += 8;
        
        while ($remainderSize > 4) {
            $remainderSize -= 5;
            $c = $remainder >> $remainderSize;
            $output .= $chars[$c & 31];
            $remainder &= (1 << $remainderSize) - 1;
        }
    }
    
    if ($remainderSize > 0) {
        $remainder <<= 5 - $remainderSize;
        $output .= $chars[$remainder & 31];
    }
    
    return $output;
}
?>
