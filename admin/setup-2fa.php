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

// Check if 2FA is enabled
$sql = "SELECT two_factor_enabled, two_factor_secret FROM admin_users WHERE username = ? AND active = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if (!$user_data || !$user_data['two_factor_enabled']) {
    header("Location: settings.php");
    exit();
}

// Generate or retrieve secret
$secret = $user_data['two_factor_secret'];
if (empty($secret)) {
    $secret = generateRandomSecret();
    $update_sql = "UPDATE admin_users SET two_factor_secret = ? WHERE username = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ss", $secret, $username);
    $update_stmt->execute();
    $update_stmt->close();
}

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_code = trim($_POST['verification_code'] ?? '');
    
    if (!empty($verification_code)) {
        if (verifyTOTP($secret, $verification_code)) {
            // Generate backup codes
            generateBackupCodes($conn, $username);
            $message = "2FA setup completed successfully! Your backup codes have been generated.";
            $message_type = "success";
        } else {
            $message = "Invalid verification code. Please try again.";
            $message_type = "error";
        }
    }
}

// Get backup codes
$backup_codes = [];
$backup_sql = "SELECT code, used FROM two_factor_backup_codes WHERE username = ? ORDER BY created_at DESC";
$backup_stmt = $conn->prepare($backup_sql);
$backup_stmt->bind_param("s", $username);
$backup_stmt->execute();
$backup_result = $backup_stmt->get_result();
while ($row = $backup_result->fetch_assoc()) {
    $backup_codes[] = $row;
}
$backup_stmt->close();

$conn->close();

// Helper functions
function generateRandomSecret($length = 20) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

function verifyTOTP($secret, $code, $window = 1) {
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

function generateBackupCodes($conn, $username) {
    // Delete existing backup codes
    $delete_sql = "DELETE FROM two_factor_backup_codes WHERE username = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("s", $username);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Generate new backup codes
    for ($i = 0; $i < 10; $i++) {
        $code = sprintf('%04d-%04d', random_int(1000, 9999), random_int(1000, 9999));
        $insert_sql = "INSERT INTO two_factor_backup_codes (username, code) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ss", $username, $code);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup 2FA - Admin</title>
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

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        h1 {
            color: var(--primary-pink);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .step {
            margin-bottom: 30px;
            padding: 20px;
            background-color: var(--input-bg);
            border-radius: 10px;
            border-left: 4px solid var(--primary-pink);
        }

        .step-number {
            display: inline-block;
            background-color: var(--primary-pink);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }

        .step-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .qr-container {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            margin: 15px 0;
        }

        .secret-key {
            background-color: var(--input-bg);
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
            border: 2px dashed var(--border-color);
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

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1.2rem;
            background-color: var(--input-bg);
            color: var(--text-color);
            text-align: center;
            letter-spacing: 2px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
        }

        .backup-codes {
            background-color: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .backup-codes h3 {
            color: var(--primary-pink);
            margin-bottom: 15px;
        }

        .code-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }

        .backup-code {
            background-color: var(--container-bg);
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .backup-code.used {
            opacity: 0.5;
            text-decoration: line-through;
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

        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            margin: 15px 0;
        }

        [data-theme="dark"] .warning {
            background-color: #4a3a2a;
            color: #ffd93d;
            border-color: #6b5b2a;
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
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <div class="container">
        <h1>📱 Setup Two-Factor Authentication</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="step">
            <div class="step-title">
                <span class="step-number">1</span>
                Install an Authenticator App
            </div>
            <p>Download and install one of these authenticator apps on your mobile device:</p>
            <ul style="margin: 10px 0; padding-left: 30px;">
                <li><strong>Google Authenticator</strong> (Android/iOS)</li>
                <li><strong>Microsoft Authenticator</strong> (Android/iOS)</li>
                <li><strong>Authy</strong> (Android/iOS/Desktop)</li>
                <li><strong>1Password</strong> (Premium)</li>
            </ul>
        </div>

        <div class="step">
            <div class="step-title">
                <span class="step-number">2</span>
                Scan QR Code or Enter Secret Key
            </div>
            <p>Use your authenticator app to scan this QR code:</p>
            
            <div class="qr-container">
                <div id="qrcode"></div>
            </div>
            
            <p><strong>Can't scan the QR code?</strong> Manually enter this secret key:</p>
            <div class="secret-key"><?= htmlspecialchars($secret) ?></div>
            
            <div class="warning">
                ⚠️ <strong>Important:</strong> Save this secret key in a secure location. You'll need it to set up 2FA on other devices.
            </div>
        </div>

        <div class="step">
            <div class="step-title">
                <span class="step-number">3</span>
                Verify Setup
            </div>
            <p>Enter the 6-digit code from your authenticator app to verify the setup:</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="verification_code">Verification Code:</label>
                    <input type="text" id="verification_code" name="verification_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>
                </div>
                <button type="submit" class="btn btn-primary">✅ Verify and Complete Setup</button>
            </form>
        </div>

        <?php if (!empty($backup_codes)): ?>
        <div class="backup-codes">
            <h3>🔑 Backup Codes</h3>
            <p>Save these backup codes in a secure location. Each code can only be used once:</p>
            
            <div class="code-grid">
                <?php foreach ($backup_codes as $code_data): ?>
                    <div class="backup-code <?= $code_data['used'] ? 'used' : '' ?>">
                        <?= htmlspecialchars($code_data['code']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="warning">
                ⚠️ <strong>Save these codes!</strong> If you lose access to your authenticator app, these backup codes are the only way to regain access to your account.
            </div>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="settings.php" class="btn btn-secondary">← Back to Settings</a>
        </div>
    </div>

    <!-- Include QR Code library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
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

        // Generate QR Code
        const secret = '<?= htmlspecialchars($secret) ?>';
        const issuer = 'Basement Admin';
        const account = '<?= htmlspecialchars($username) ?>';
        const otpauth = `otpauth://totp/${encodeURIComponent(issuer)}:${encodeURIComponent(account)}?secret=${secret}&issuer=${encodeURIComponent(issuer)}`;
        
        QRCode.toCanvas(document.getElementById('qrcode'), otpauth, {
            width: 256,
            margin: 2,
            color: {
                dark: '#333333',
                light: '#ffffff'
            }
        });

        // Auto-format verification code input
        const verificationInput = document.getElementById('verification_code');
        verificationInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
