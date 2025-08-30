<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$require_2fa = false;
$temp_user_data = null;

// Handle error messages from URL parameters
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'account_disabled':
            $error = "Your account has been disabled.";
            break;
        case 'account_not_found':
            $error = "Your account no longer exists.";
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config_path = '/var/www/config/db_config.php';
    
    if (!file_exists($config_path)) {
        $error = "Database configuration file not found. Please check server setup.";
    } else {
        include($config_path);
        
        if (!isset($DB_SERVER) || !isset($DB_USER) || !isset($DB_PASSWORD) || !isset($DB_NAME)) {
            $error = "Database configuration incomplete.";
        } else {
            $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
            
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            $action = $_POST['action'] ?? 'login';
            
            if ($action === 'login') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (!empty($username) && !empty($password)) {
                    // First check if user exists (regardless of active status)
                    $sql = "SELECT id, username, password, role, two_factor_enabled, two_factor_secret, active FROM admin_users WHERE username = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 1) {
                        $admin = $result->fetch_assoc();
                        if (password_verify($password, $admin['password'])) {
                            // Check if account is disabled
                            if ($admin['active'] != 1) {
                                $error = "Your account has been disabled.";
                                // Log failed attempt - account disabled
                                logLoginAttempt($conn, $username, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'password', 'Account disabled');
                            } else {
                                // Check if 2FA is enabled
                                if ($admin['two_factor_enabled']) {
                                    $_SESSION['temp_user_data'] = $admin;
                                    $require_2fa = true;
                                } else {
                                    // Complete login
                                    completeLogin($conn, $admin);
                                }
                            }
                        } else {
                            $error = "Invalid username or password.";
                            // Log failed attempt - wrong password
                            logLoginAttempt($conn, $username, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'password', 'Invalid password');
                        }
                    } else {
                        $error = "Invalid username or password.";
                        // Log failed attempt - username not found
                        logLoginAttempt($conn, $username, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'password', 'Username not found');
                    }
                    $stmt->close();
                } else {
                    $error = "Please enter both username and password.";
                }
            } elseif ($action === 'verify_2fa') {
                $verification_code = $_POST['verification_code'] ?? '';
                $backup_code = $_POST['backup_code'] ?? '';
                
                if (isset($_SESSION['temp_user_data'])) {
                    $admin = $_SESSION['temp_user_data'];
                    $verified = false;
                    
                    if (!empty($verification_code)) {
                        // Verify TOTP code
                        $verified = verifyTOTP($admin['two_factor_secret'], $verification_code);
                    } elseif (!empty($backup_code)) {
                        // Verify backup code
                        $verified = verifyBackupCode($conn, $admin['username'], $backup_code);
                    }
                    
                    if ($verified) {
                        unset($_SESSION['temp_user_data']);
                        $method = !empty($verification_code) ? '2fa_totp' : '2fa_backup';
                        completeLogin($conn, $admin, $method);
                    } else {
                        $error = "Invalid verification code or backup code.";
                        // Log failed 2FA attempt
                        $method = !empty($verification_code) ? '2fa_totp' : '2fa_backup';
                        logLoginAttempt($conn, $admin['username'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', false, $method, 'Invalid 2FA code');
                        $require_2fa = true;
                        $temp_user_data = $admin;
                    }
                } else {
                    $error = "Session expired. Please log in again.";
                }
            }
            
            $conn->close();
        }
    }
}

// Check if we need to show 2FA form
if (isset($_SESSION['temp_user_data'])) {
    $require_2fa = true;
    $temp_user_data = $_SESSION['temp_user_data'];
}

function completeLogin($conn, $admin, $method = 'password') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    
    // Update last login
    $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $admin['id']);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Log successful login
    logLoginAttempt($conn, $admin['username'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', true, $method);
    
    header("Location: dashboard.php");
    exit();
}

function verifyTOTP($secret, $code, $window = 1) {
    if (empty($secret) || empty($code)) return false;
    
    $time = floor(time() / 30);
    
    for ($i = -$window; $i <= $window; $i++) {
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $time + $i), base32_decode($secret), true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        if (sprintf('%06d', $otp) === $code) {
            return true;
        }
    }
    return false;
}

function verifyBackupCode($conn, $username, $code) {
    $sql = "SELECT id FROM two_factor_backup_codes WHERE username = ? AND code = ? AND used = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $backup_code = $result->fetch_assoc();
        
        // Mark backup code as used
        $update_sql = "UPDATE two_factor_backup_codes SET used = 1, used_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $backup_code['id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        $stmt->close();
        return true;
    }
    
    $stmt->close();
    return false;
}

function base32_decode($data) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $v <<= 5;
        $v += strpos($chars, $data[$i]);
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr(($v >> ($vbits - 8)) & 255);
            $vbits -= 8;
        }
    }
    
    return $output;
}

function logLoginAttempt($conn, $username, $ip, $userAgent, $success, $method, $failureReason = null) {
    try {
        if ($success) {
            $sql = "INSERT INTO login_attempts (username, ip_address, user_agent, success, method, session_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiss", $username, $ip, $userAgent, $success, $method, session_id());
        } else {
            $sql = "INSERT INTO login_attempts (username, ip_address, user_agent, success, method, failure_reason) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiss", $username, $ip, $userAgent, $success, $method, $failureReason);
        }
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail if logging doesn't work - don't break login process
        error_log("Login attempt logging failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Basement Applications</title>
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
            --error-bg: #ffebee;
            --error-text: #c62828;
            --error-border: #ff4757;
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
            --error-bg: #4a2c2a;
            --error-text: #ff6b6b;
            --error-border: #ff6b6b;
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            max-width: 450px;
            width: 100%;
            text-align: center;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        h1 {
            color: var(--primary-pink);
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--primary-pink);
        }

        input[type="text"], input[type="password"] {
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
            width: 100%;
            padding: 12px;
            background-color: var(--primary-pink);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-weight: bold;
        }

        .btn:hover {
            background-color: var(--secondary-pink);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
        }

        .btn-secondary {
            background-color: var(--secondary-pink);
            margin-top: 15px;
        }

        .btn-secondary:hover {
            background-color: var(--primary-pink);
        }

        .error-message {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-weight: bold;
        }



        .two-fa-form {
            background-color: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .two-fa-title {
            color: var(--primary-pink);
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .verification-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-family: monospace;
        }

        .backup-code-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .toggle-link {
            color: var(--primary-pink);
            cursor: pointer;
            text-decoration: underline;
            font-size: 0.9rem;
        }

        .toggle-link:hover {
            color: var(--secondary-pink);
        }

        .hidden {
            display: none;
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

        @media (max-width: 500px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .verification-input {
                font-size: 1.2rem;
                letter-spacing: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <div class="container">
        <h1><?= $require_2fa ? '🔐 Two-Factor Authentication' : '🔐 Admin Login' ?></h1>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($require_2fa): ?>
            <!-- 2FA Verification Form -->
            <div class="two-fa-form">
                <div class="two-fa-title">Enter Verification Code</div>
                <p>Enter the 6-digit code from your authenticator app:</p>
                
                <form method="POST" id="twoFaForm">
                    <input type="hidden" name="action" value="verify_2fa">
                    <div class="form-group">
                        <input type="text" id="verification_code" name="verification_code" class="verification-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="off">
                    </div>
                    <button type="submit" class="btn">✅ Verify Code</button>
                </form>

                <div class="backup-code-section">
                    <span class="toggle-link" onclick="toggleBackupCodeForm()">Use backup code instead</span>
                    
                    <form method="POST" id="backupCodeForm" class="hidden" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="verify_2fa">
                        <div class="form-group">
                            <label for="backup_code">Backup Code:</label>
                            <input type="text" id="backup_code" name="backup_code" placeholder="0000-0000" maxlength="9">
                        </div>
                        <button type="submit" class="btn btn-secondary">Use Backup Code</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Regular Login Form -->
            <form method="POST" id="loginForm">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn">🔓 Login</button>
            </form>


        <?php endif; ?>
        
        <p style="margin-top: 30px; font-size: 0.9rem; opacity: 0.7;">
            <a href="../index.html" style="color: var(--primary-pink); text-decoration: none;">← Back to Application Form</a>
        </p>
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

        // 2FA Code formatting
        <?php if ($require_2fa): ?>
        const verificationInput = document.getElementById('verification_code');
        if (verificationInput) {
            verificationInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 6) {
                    value = value.substring(0, 6);
                }
                e.target.value = value;
                
                // Auto-submit when 6 digits are entered
                if (value.length === 6) {
                    document.getElementById('twoFaForm').submit();
                }
            });
            
            verificationInput.focus();
        }

        // Backup code formatting
        const backupCodeInput = document.getElementById('backup_code');
        if (backupCodeInput) {
            backupCodeInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^0-9-]/g, '');
                if (value.length === 4 && !value.includes('-')) {
                    value = value + '-';
                }
                if (value.length > 9) {
                    value = value.substring(0, 9);
                }
                e.target.value = value;
            });
        }

        function toggleBackupCodeForm() {
            const backupForm = document.getElementById('backupCodeForm');
            const isHidden = backupForm.classList.contains('hidden');
            
            if (isHidden) {
                backupForm.classList.remove('hidden');
                document.getElementById('backup_code').focus();
            } else {
                backupForm.classList.add('hidden');
                document.getElementById('verification_code').focus();
            }
        }
        <?php endif; ?>


    </script>
</body>
</html>
