<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Check admin maintenance mode - only allow Emma to access during maintenance
if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== 'emma') {
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
        if ($table_check && $table_check->num_rows > 0) {
            $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'admin_maintenance_mode' LIMIT 1";
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

// Handle maintenance mode toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_maintenance'])) {
    $new_status = $_POST['new_maintenance_status'];
    $maintenance_type = $_POST['maintenance_type'];
    
    // Check if site_settings table exists, create if not
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check->num_rows === 0) {
        $create_table_sql = "CREATE TABLE site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(255) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(100)
        )";
        $conn->query($create_table_sql);
    }
    
    // Update or insert maintenance setting
    $admin_username = $_SESSION['admin_username'] ?? 'system';
    $stmt = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, updated_by) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
    $stmt->bind_param("sssss", $setting_name, $new_status, $admin_username, $new_status, $admin_username);
    $setting_name = $maintenance_type;
    
    if ($stmt->execute()) {
        $type_labels = [
            'maintenance_mode' => 'Site',
            'form_maintenance_mode' => 'Form'
        ];
        $type_label = $type_labels[$maintenance_type] ?? $maintenance_type;
        $message = "$type_label maintenance mode " . ($new_status === '1' ? "enabled" : "disabled") . " successfully!";
    } else {
        $error = "Error updating maintenance mode: " . $conn->error;
    }
    $stmt->close();
}

// Get current maintenance statuses
$site_maintenance_active = false;
$site_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
$site_maintenance_result = $conn->query($site_maintenance_sql);
if ($site_maintenance_result && $site_maintenance_result->num_rows > 0) {
    $site_maintenance_row = $site_maintenance_result->fetch_assoc();
    $site_maintenance_active = ($site_maintenance_row['setting_value'] === '1');
}

$form_maintenance_active = false;
$form_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'form_maintenance_mode' LIMIT 1";
$form_maintenance_result = $conn->query($form_maintenance_sql);
if ($form_maintenance_result && $form_maintenance_result->num_rows > 0) {
    $form_maintenance_row = $form_maintenance_result->fetch_assoc();
    $form_maintenance_active = ($form_maintenance_row['setting_value'] === '1');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Control - Admin Panel</title>
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
        .btn-secondary { background-color: var(--secondary-pink); color: white; }
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-warning { background-color: var(--warning-color); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .container {
            max-width: 1200px;
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

        .maintenance-control {
            margin: 20px 0;
            padding: 20px;
            border-radius: 12px;
            border: 2px solid;
            background-color: rgba(46, 213, 115, 0.1);
            border-color: var(--success-color);
        }

        .maintenance-control h4 {
            color: var(--success-color);
            margin-bottom: 10px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .maintenance-control p {
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .status-indicator {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .status-enabled {
            background-color: var(--danger-color);
            color: white;
        }

        .status-disabled {
            background-color: var(--success-color);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px var(--shadow-color);
            border: 2px solid var(--success-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--success-color);
        }

        .stat-label {
            color: var(--text-color);
            font-size: 0.9rem;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .maintenance-control {
                margin: 15px 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('maintenance-control.php'); ?>

    <div class="container">
        <div class="section">
            <h3>🚧 Maintenance Control Panel</h3>
            <p>Manage different maintenance modes for the application system. Each mode serves a different purpose and can be controlled independently.</p>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $site_maintenance_active ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Site Maintenance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $form_maintenance_active ? 'ON' : 'OFF' ?></div>
                <div class="stat-label">Form Maintenance</div>
            </div>
        </div>

        <!-- Site Maintenance Control -->
        <div class="maintenance-control">
            <h4>🌐 Site Maintenance Mode</h4>
            <p>Completely closes the entire site for all public users. This includes the application form, status checker, and all public pages. Only admin panel remains accessible.</p>
            
            <div class="status-indicator <?= $site_maintenance_active ? 'status-enabled' : 'status-disabled' ?>">
                Status: <?= $site_maintenance_active ? 'ENABLED - Site is closed' : 'DISABLED - Site is open' ?>
            </div>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="new_maintenance_status" value="<?= $site_maintenance_active ? '0' : '1' ?>">
                <input type="hidden" name="maintenance_type" value="maintenance_mode">
                <button type="submit" name="toggle_maintenance" 
                        class="btn <?= $site_maintenance_active ? 'btn-success' : 'btn-danger' ?>"
                        onclick="return confirm('Are you sure you want to <?= $site_maintenance_active ? 'disable' : 'enable' ?> site maintenance mode? This will <?= $site_maintenance_active ? 'open' : 'close' ?> the entire site for public users.')">
                    <?= $site_maintenance_active ? '✅ Disable Site Maintenance' : '🚧 Enable Site Maintenance' ?>
                </button>
            </form>
        </div>

        <!-- Form Maintenance Control -->
        <div class="maintenance-control">
            <h4>📝 Form Maintenance Mode</h4>
            <p>Blocks public access to the application form only, but allows logged-in admins to access it. Status checker and other public pages remain accessible.</p>
            
            <div class="status-indicator <?= $form_maintenance_active ? 'status-enabled' : 'status-disabled' ?>">
                Status: <?= $form_maintenance_active ? 'ENABLED - Form closed to public' : 'DISABLED - Form is open' ?>
            </div>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="new_maintenance_status" value="<?= $form_maintenance_active ? '0' : '1' ?>">
                <input type="hidden" name="maintenance_type" value="form_maintenance_mode">
                <button type="submit" name="toggle_maintenance" 
                        class="btn <?= $form_maintenance_active ? 'btn-success' : 'btn-secondary' ?>"
                        onclick="return confirm('Are you sure you want to <?= $form_maintenance_active ? 'disable' : 'enable' ?> form maintenance mode? This will <?= $form_maintenance_active ? 'open' : 'close' ?> the application form for public users.')">
                    <?= $form_maintenance_active ? '✅ Disable Form Maintenance' : '📝 Enable Form Maintenance' ?>
                </button>
            </form>
        </div>

        <!-- Information Section -->
        <div class="section">
            <h3>ℹ️ Maintenance Mode Information</h3>
            <ul style="line-height: 2;">
                <li><strong>Site Maintenance:</strong> Closes everything for public users - use for major updates or emergencies</li>
                <li><strong>Form Maintenance:</strong> Closes only the application form - use for testing or form updates</li>
                <li><strong>Admin Access:</strong> Admins can always access the admin panel and bypass form maintenance</li>
                <li><strong>Independent Control:</strong> Each maintenance mode works independently and can be enabled separately</li>
            </ul>
        </div>
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
    </script>
    
    <?php
    // Close database connection at the end
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
