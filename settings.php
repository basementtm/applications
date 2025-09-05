<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '/var/www/config/db_config.php';
// Include appropriate auth file based on context
if (strpos($_SERVER['SCRIPT_FILENAME'], '/admin/') !== false) {
    require_once 'auth_functions.php';
} else {
    require_once 'user_auth.php';
}
require_once 'action_logger_lite.php';

// Check if user is logged in
requireLogin();

// Establish database connection
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check user status
checkUserStatus();

// Get user data
$user_data = getUserData();
if (!$user_data) {
    die("Could not retrieve user data.");
}
$username = $user_data['username'];
$message = '';
$message_type = '';

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_email':
            $new_email = trim($_POST['email'] ?? '');
            if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $update_sql = "UPDATE users SET email = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_email, $_SESSION['user_id']);
                if ($update_stmt->execute()) {
                    $old_email = $user_data['email'];
                    $user_data['email'] = $new_email;
                    
                    // Log the email change
                    logEmailChange($old_email, $new_email);
                    
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
            $enable_2fa = isset($_POST['enable_2fa']) && $_POST['enable_2fa'] === '1';
            
            if ($enable_2fa && empty($user_data['two_factor_secret'])) {
                // Generate new secret when enabling 2FA
                $secret = generateRandomSecret();
                $update_sql = "UPDATE users SET two_factor_enabled = ?, two_factor_secret = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("isi", $enable_2fa, $secret, $_SESSION['user_id']);
            } else {
                // Just toggle the enabled status
                $update_sql = "UPDATE users SET two_factor_enabled = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $enable_2fa, $_SESSION['user_id']);
            }
            
            if ($update_stmt->execute()) {
                $user_data['two_factor_enabled'] = $enable_2fa;
                if (isset($secret)) {
                    $user_data['two_factor_secret'] = $secret;
                }
                
                // Log the 2FA change
                log2FAChange($enable_2fa);
                
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

$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" <?php if ($theme === 'dark') { echo 'data-theme="dark"'; } ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings</title>
    <style>
        <?php
        // Use the appropriate navbar CSS based on user role
        if (isAdmin()) {
            echo getNavbarCSS(); 
        } else {
            echo getUserNavbarCSS();
        }
        ?>
        /* Using root variables from getUserNavbarCSS */
        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            padding-top: 80px; /* Adjust for fixed navbar */
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
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
        }
        .section-title {
            color: var(--primary-pink);
            font-size: 1.4rem;
            margin-bottom: 20px;
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
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-weight: bold;
        }
        .btn-primary { background-color: var(--primary-pink); color: white; }
        .btn-secondary { background-color: var(--secondary-pink); color: white; }
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-warning { background-color: #ffc107; color: white; }
        .btn-info { background-color: #17a2b8; color: white; }

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

        /* Theme Switcher Section */
        .theme-section {
            background-color: var(--container-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 30px;
        }

        .theme-toggle-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .theme-switcher-btn {
            background-color: var(--secondary-pink);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px var(--shadow-color);
        }

        .theme-switcher-btn:hover {
            transform: scale(1.1);
            background-color: var(--primary-pink);
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
    <?php 
    // Determine which navbar to use based on user role
    if (isAdmin()) {
        renderAdminNavbar('settings.php');
    } else {
        renderUserNavbar('settings.php'); 
    }
    ?>
    
    <div class="container">
        <h1 style="color: var(--primary-pink); text-align: center; margin-bottom: 20px;">Account Settings</h1>
        
        <!-- Theme Settings Section -->
        <div class="theme-section">
            <h2 class="section-title">🎨 Theme Settings</h2>
            <div class="theme-toggle-container">
                <div class="setting-info">
                    <div class="setting-title">Dark Mode</div>
                    <div class="setting-description">Switch between light and dark theme</div>
                </div>
                <button class="theme-switcher-btn" id="themeSwitcher" title="Toggle Dark Mode">🌙</button>
            </div>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
            <div class="message error">
                ❌ Access denied. You don't have permission to modify settings in read-only mode.
            </div>
        <?php endif; ?>
        
        <?php if (isReadOnlyUser()): ?>
            <div class="message" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                📖 You are viewing settings in read-only mode. All modification options are disabled.
            </div>
        <?php endif; ?>
        
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
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required <?= isReadOnlyUser() ? 'disabled' : '' ?>>
                    </div>
                    <?php if (!isReadOnlyUser()): ?>
                    <button type="submit" class="btn btn-primary">💾 Update Email</button>
                    <?php else: ?>
                    <button type="button" class="btn btn-secondary" disabled title="Read-only mode - modifications disabled">💾 Update Email (Read-Only)</button>
                    <?php endif; ?>
                </form>

                <!-- Two-Factor Authentication moved here -->
                <div style="margin-top: 30px;">
                    <h3 style="color: var(--primary-pink); margin-bottom: 15px;">🔒 Two-Factor Authentication</h3>
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-title">Two-Factor Authentication</div>
                            <div class="setting-description">Add an extra layer of security with TOTP authentication</div>
                        </div>
                        <div>
                            <span class="status-badge <?= $user_data['two_factor_enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                                <?= $user_data['two_factor_enabled'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                            <?php if (!isReadOnlyUser()): ?>
                            <form method="POST" style="display: inline; margin-left: 10px;">
                                <input type="hidden" name="action" value="toggle_2fa">
                                <input type="hidden" name="enable_2fa" value="<?= $user_data['two_factor_enabled'] ? '0' : '1' ?>">
                                <button type="submit" class="btn <?= $user_data['two_factor_enabled'] ? 'btn-danger' : 'btn-success' ?>" onclick="return confirm('<?= $user_data['two_factor_enabled'] ? 'Disable' : 'Enable' ?> 2FA?')">
                                    <?= $user_data['two_factor_enabled'] ? '❌ Disable' : '✅ Enable' ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary" disabled title="Read-only mode - modifications disabled" style="margin-left: 10px;">
                                <?= $user_data['two_factor_enabled'] ? '❌ Disable (Read-Only)' : '✅ Enable (Read-Only)' ?>
                            </button>
                            <?php endif; ?>
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
                            <a href="setup-2fa.php" class="btn btn-info">🔧 Manage 2FA</a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

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
                    <div class="setting-item">
                        <div class="setting-info">
                            <div class="setting-title">Account Type</div>
                            <div class="setting-description">
                                <?php
                                if (isOwner()) {
                                    echo "Owner (Super Administrator)";
                                } elseif (isSuperAdmin()) {
                                    echo "Super Administrator";
                                } elseif (isAdmin() && !isReadOnlyUser()) {
                                    echo "Administrator";
                                } elseif (isReadOnlyUser()) {
                                    echo "Read-Only Administrator";
                                } else {
                                    echo "Standard User";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="message" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; margin-top: 20px;">
                        <strong>📝 Note:</strong> To change your password, please contact support.
                    </div>
                </div>
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
            
            const oldTheme = isDark ? "dark" : "light";
            const newTheme = isDark ? "light" : "dark";
            
            if (isDark) {
                body.removeAttribute("data-theme");
                themeSwitcher.textContent = "🌙";
                localStorage.setItem("theme", "light");
                document.cookie = "theme=light; path=/; max-age=31536000"; // 1 year
            } else {
                body.setAttribute("data-theme", "dark");
                themeSwitcher.textContent = "☀️";
                localStorage.setItem("theme", "dark");
                document.cookie = "theme=dark; path=/; max-age=31536000"; // 1 year
            }
            
            // Log theme change via AJAX to avoid page reload
            fetch('log_theme_change.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `old_theme=${oldTheme}&new_theme=${newTheme}`
            });
        });
    </script>
    
    <?php 
    // Use the appropriate navbar JavaScript based on user role
    if (isAdmin()) {
        echo getNavbarJS(); 
    } else {
        echo getUserNavbarJS();
    }
    ?>
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
