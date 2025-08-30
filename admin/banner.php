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

// Check maintenance mode - only allow Emma to access during maintenance
if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== 'emma') {
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
        if ($table_check && $table_check->num_rows > 0) {
            $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
            $maintenance_result = $conn->query($maintenance_sql);
            if ($maintenance_result && $maintenance_result->num_rows > 0) {
                $maintenance_row = $maintenance_result->fetch_assoc();
                if ($maintenance_row['setting_value'] === '1') {
                    header("Location: maintenance.php");
                    exit();
                }
            }
        }
    } catch (Exception $e) {
        // Continue if there's a database error
    }
}

// Include navbar component
include('navbar.php');

$message = '';
$error = '';

// Handle banner update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_banner'])) {
    $banner_text = trim($_POST['banner_text']);
    $banner_enabled = isset($_POST['banner_enabled']) ? 1 : 0;
    $banner_type = $_POST['banner_type'] ?? 'info';
    
    // Check if site_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    
    if ($table_check && $table_check->num_rows > 0) {
        $admin_username = $_SESSION['admin_username'] ?? 'system';
        
        // Update or insert banner settings
        $settings = [
            'banner_text' => $banner_text,
            'banner_enabled' => (string)$banner_enabled,
            'banner_type' => $banner_type
        ];
        
        foreach ($settings as $setting_name => $setting_value) {
            $upsert_sql = "INSERT INTO site_settings (setting_name, setting_value, updated_at, updated_by) 
                           VALUES (?, ?, NOW(), ?) 
                           ON DUPLICATE KEY UPDATE 
                           setting_value = VALUES(setting_value), updated_at = NOW(), updated_by = VALUES(updated_by)";
            $stmt = $conn->prepare($upsert_sql);
            $stmt->bind_param("sss", $setting_name, $setting_value, $admin_username);
            $stmt->execute();
            $stmt->close();
        }
        
        $message = "Banner settings updated successfully!";
    } else {
        $error = "Site settings table does not exist. Please run the database setup first.";
    }
}

// Get current banner settings
$banner_text = '';
$banner_enabled = 0;
$banner_type = 'info';
$settings_exist = false;

$table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($table_check && $table_check->num_rows > 0) {
    $settings_exist = true;
    
    // Get banner settings
    $settings_sql = "SELECT setting_name, setting_value FROM site_settings 
                     WHERE setting_name IN ('banner_text', 'banner_enabled', 'banner_type')";
    $settings_result = $conn->query($settings_sql);
    
    if ($settings_result) {
        while ($row = $settings_result->fetch_assoc()) {
            switch ($row['setting_name']) {
                case 'banner_text':
                    $banner_text = $row['setting_value'];
                    break;
                case 'banner_enabled':
                    $banner_enabled = (int)$row['setting_value'];
                    break;
                case 'banner_type':
                    $banner_type = $row['setting_value'];
                    break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Management - Admin Panel</title>
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
            transition: background-color 0.3s ease, color 0.3s ease;
            line-height: 1.6;
        }

        <?= getNavbarCSS() ?>

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
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-warning { background-color: var(--warning-color); color: white; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
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

        .banner-form {
            background-color: var(--container-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px var(--shadow-color);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        input[type="text"], textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 1rem;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }

        .banner-preview {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .banner-preview h3 {
            color: var(--primary-pink);
            margin-bottom: 15px;
        }

        .preview-banner {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-weight: bold;
            text-align: center;
        }

        .preview-banner.info {
            background-color: #cce7ff;
            color: #0066cc;
            border: 1px solid #99d6ff;
        }

        .preview-banner.warning {
            background-color: #fff3cd;
            color: #cc6600;
            border: 1px solid #ffe066;
        }

        .preview-banner.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .preview-banner.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .setup-notice {
            background-color: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #ffeaa7;
            text-align: center;
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
                padding: 15px;
            }

            .banner-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('banner.php'); ?>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$settings_exist): ?>
            <div class="setup-notice">
                <h3>⚠️ Database Setup Required</h3>
                <p>The site_settings table needs to be created before you can manage banners.</p>
                <a href="create-settings-table.php" class="btn btn-warning" style="margin-top: 10px;">🔧 Setup Database</a>
            </div>
        <?php else: ?>
            <!-- Banner Form -->
            <div class="banner-form">
                <h2 style="color: var(--primary-pink); margin-bottom: 20px;">📢 Banner Management</h2>
                <form method="POST" id="bannerForm">
                    <div class="form-group">
                        <label for="banner_text">Banner Text:</label>
                        <textarea id="banner_text" name="banner_text" placeholder="Enter your banner message here..."><?= htmlspecialchars($banner_text) ?></textarea>
                        <small style="color: var(--border-color);">This text will appear at the top of the application form and status pages.</small>
                    </div>

                    <div class="form-group">
                        <label for="banner_type">Banner Type:</label>
                        <select id="banner_type" name="banner_type">
                            <option value="info" <?= $banner_type === 'info' ? 'selected' : '' ?>>ℹ️ Info (Blue)</option>
                            <option value="warning" <?= $banner_type === 'warning' ? 'selected' : '' ?>>⚠️ Warning (Yellow)</option>
                            <option value="success" <?= $banner_type === 'success' ? 'selected' : '' ?>>✅ Success (Green)</option>
                            <option value="error" <?= $banner_type === 'error' ? 'selected' : '' ?>>❌ Error (Red)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="banner_enabled" name="banner_enabled" value="1" <?= $banner_enabled ? 'checked' : '' ?>>
                            <label for="banner_enabled">Enable Banner</label>
                        </div>
                        <small style="color: var(--border-color);">Uncheck to hide the banner without deleting the text.</small>
                    </div>

                    <button type="submit" name="update_banner" class="btn btn-primary">💾 Update Banner</button>
                </form>
            </div>

            <!-- Banner Preview -->
            <div class="banner-preview">
                <h3>👀 Live Preview</h3>
                <div id="previewContainer">
                    <?php if ($banner_enabled && !empty($banner_text)): ?>
                        <div class="preview-banner <?= htmlspecialchars($banner_type) ?>">
                            <?= htmlspecialchars($banner_text) ?>
                        </div>
                        <p style="color: var(--border-color); font-size: 0.9rem;">✅ Banner is currently visible to users</p>
                    <?php elseif (!empty($banner_text)): ?>
                        <div class="preview-banner <?= htmlspecialchars($banner_type) ?>" style="opacity: 0.5;">
                            <?= htmlspecialchars($banner_text) ?>
                        </div>
                        <p style="color: var(--border-color); font-size: 0.9rem;">❌ Banner is disabled (shown above with opacity for preview)</p>
                    <?php else: ?>
                        <p style="color: var(--border-color); font-style: italic;">No banner text entered</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Live preview updates
        const bannerText = document.getElementById('banner_text');
        const bannerType = document.getElementById('banner_type');
        const bannerEnabled = document.getElementById('banner_enabled');
        const previewContainer = document.getElementById('previewContainer');

        function updatePreview() {
            const text = bannerText.value.trim();
            const type = bannerType.value;
            const enabled = bannerEnabled.checked;

            if (text) {
                const opacity = enabled ? '1' : '0.5';
                const status = enabled ? '✅ Banner is currently visible to users' : '❌ Banner is disabled (shown above with opacity for preview)';
                
                previewContainer.innerHTML = `
                    <div class="preview-banner ${type}" style="opacity: ${opacity};">
                        ${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}
                    </div>
                    <p style="color: var(--border-color); font-size: 0.9rem;">${status}</p>
                `;
            } else {
                previewContainer.innerHTML = '<p style="color: var(--border-color); font-style: italic;">No banner text entered</p>';
            }
        }

        bannerText.addEventListener('input', updatePreview);
        bannerType.addEventListener('change', updatePreview);
        bannerEnabled.addEventListener('change', updatePreview);

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
    
    <?php
    // Close database connection at the end
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
