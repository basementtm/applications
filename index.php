<?php
// Start session to check for user login
session_start();

// Database maintenance mode check with fallback
include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Include action logger for visitor logging
if (file_exists('admin/action_logger.php')) {
    require_once 'admin/action_logger.php';
}

// Include unified user authentication system
require_once 'user_auth.php';

// Check user authentication status
$is_logged_in = isLoggedIn();
$is_admin = isAdmin();
$current_user = $is_logged_in ? getUserData() : null;

// Check for logout message
$logout_message = isset($_GET['logout']) && $_GET['logout'] == '1';

// Function to log visitor activity
function logVisitor($conn, $page = 'main_form', $action = 'view') {
    try {
        // Get visitor information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        
        // Handle proxy/forwarded IPs
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip_address = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        // Skip logging for specific IP addresses (e.g., Docker containers, monitoring services)
        if ($ip_address === '172.17.0.2') {
            return; // Skip logging for this IP address
        }
        
        // Check if action_logs table exists before logging
        $table_check = $conn->query("SHOW TABLES LIKE 'action_logs'");
        if ($table_check && $table_check->num_rows > 0) {
            $action_type = 'VISITOR_' . strtoupper($action);
            $description = "Visitor accessed $page page";
            $target_type = 'page';
            $additional_data = json_encode([
                'page' => $page,
                'action' => $action,
                'referrer' => $referrer
            ]);
            
            // Insert directly into action_logs without using logAction function
            // (since logAction requires admin session)
            $sql = "INSERT INTO action_logs (user_id, username, action_type, action_description, target_type, target_id, ip_address, user_agent, additional_data) 
                    VALUES (NULL, 'Visitor', ?, ?, ?, NULL, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $action_type, $description, $target_type, $ip_address, $user_agent, $additional_data);
            
            if (!$stmt->execute()) {
                error_log("Failed to log visitor: " . $stmt->error);
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Visitor logging error: " . $e->getMessage());
    }
}

$maintenance_active = false;
$form_maintenance_active = false;
if (!$conn->connect_error) {
    // Check if site_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
        $result = $conn->query($maintenance_sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $maintenance_active = ($row['setting_value'] === '1');
        }
        
        $form_maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'form_maintenance_mode' LIMIT 1";
        $form_result = $conn->query($form_maintenance_sql);
        if ($form_result && $form_result->num_rows > 0) {
            $form_row = $form_result->fetch_assoc();
            $form_maintenance_active = ($form_row['setting_value'] === '1');
        }
    }
    
    // Process scheduled maintenance
    if (file_exists('includes/scheduled_maintenance_helper.php')) {
        include('includes/scheduled_maintenance_helper.php');
        if (function_exists('processScheduledMaintenance')) {
            processScheduledMaintenance($conn);
        }
    }
}

// Get banner settings
$banner_settings = [
    'text' => '',
    'enabled' => false,
    'type' => 'info'
];

if (!$conn->connect_error) {
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $settings_sql = "SELECT setting_name, setting_value FROM site_settings 
                         WHERE setting_name IN ('banner_text', 'banner_enabled', 'banner_type')";
        $settings_result = $conn->query($settings_sql);
        
        if ($settings_result) {
            while ($row = $settings_result->fetch_assoc()) {
                switch ($row['setting_name']) {
                    case 'banner_text':
                        $banner_settings['text'] = $row['setting_value'];
                        break;
                    case 'banner_enabled':
                        $banner_settings['enabled'] = ($row['setting_value'] === '1');
                        break;
                    case 'banner_type':
                        $banner_settings['type'] = $row['setting_value'];
                        break;
                }
            }
        }
    }
}

// Check if user is an admin (for form maintenance bypass)
session_start();
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Check if IP is banned (skip check for admins)
$ip_banned = false;
$ban_reason = null;
if (!$is_admin && !$conn->connect_error) {
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Handle proxy/forwarded IPs
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $user_ip = trim($forwarded_ips[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $user_ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    // Check if banned_ips table exists and if IP is banned
    $table_check = $conn->query("SHOW TABLES LIKE 'banned_ips'");
    if ($table_check && $table_check->num_rows > 0) {
        $ban_check_sql = "SELECT id, reason FROM banned_ips WHERE ip_address = ? AND is_active = 1 LIMIT 1";
        $ban_stmt = $conn->prepare($ban_check_sql);
        $ban_stmt->bind_param("s", $user_ip);
        $ban_stmt->execute();
        $ban_result = $ban_stmt->get_result();
        if ($ban_result->num_rows > 0) {
            $ip_banned = true;
            $ban_row = $ban_result->fetch_assoc();
            $ban_reason = $ban_row['reason'];
        }
        $ban_stmt->close();
    }
}

if ($ip_banned) {
    // Include action logger and log the banned IP access attempt
    require_once 'admin/action_logger.php';
    logBannedIPAccess('index.php', $ban_reason);
    
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Restricted - basement application form</title>
        <style>
            :root {
                --bg-color: #ffc0cb;
                --container-bg: #fff0f5;
                --text-color: #333;
                --primary-pink: #ff1493;
                --border-color: #ff1493;
                --shadow-color: rgba(0,0,0,0.1);
            }

            [data-theme="dark"] {
                --bg-color: #2d1b2e;
                --container-bg: #3d2b3e;
                --text-color: #e0d0e0;
                --primary-pink: #ff6bb3;
                --border-color: #ff6bb3;
                --shadow-color: rgba(0,0,0,0.3);
            }

            body {
                font-family: Arial, sans-serif;
                background-color: var(--bg-color);
                color: var(--text-color);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                text-align: center;
                transition: background-color 0.3s ease, color 0.3s ease;
            }
            .restricted-notice {
                background: var(--container-bg);
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 4px 15px var(--shadow-color);
                max-width: 500px;
                border: 3px solid var(--border-color);
                transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            }
            .restricted-notice h1 { 
                color: var(--primary-pink); 
                transition: color 0.3s ease;
            }
            .icon { font-size: 3rem; margin-bottom: 20px; }
            .theme-switcher {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
                background-color: var(--container-bg);
                border: 2px solid var(--primary-pink);
                border-radius: 50%;
                width: 50px;
                height: 50px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                transition: all 0.3s ease;
                box-shadow: 0 4px 10px var(--shadow-color);
            }
            .theme-switcher:hover {
                transform: scale(1.1);
                background-color: var(--primary-pink);
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">
            🌙
        </div>
        <div class="restricted-notice">
            <div class="icon">🚫</div>
            <h1>Access Restricted</h1>
            <p>Your IP address has been restricted from submitting applications and/or checking application status.</p>
            <p>If you believe this is an error, please contact support.</p>
            <p style="margin-top: 15px;"><a href="https://status.girlskissing.dev" target="_blank" style="color: var(--primary-pink); text-decoration: underline;">Check System Status Page</a></p>
        </div>

        <script>
            // Theme switcher functionality
            const themeSwitcher = document.getElementById('themeSwitcher');
            const body = document.body;

            // Load saved theme
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'dark') {
                body.setAttribute('data-theme', 'dark');
                themeSwitcher.textContent = '☀️';
            }

            // Theme toggle
            themeSwitcher.addEventListener('click', () => {
                const isDark = body.getAttribute('data-theme') === 'dark';
                
                if (isDark) {
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
    <?php
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}

if ($maintenance_active) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
     <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>Maintenance</title>
      <style>
        :root {
          --bg-color: #ffc0cb;
          --container-bg: #fff0f5;
          --text-color: #333;
          --primary-pink: #ff1493;
          --shadow-color: rgba(0,0,0,0.1);
        }

        [data-theme='dark'] {
          --bg-color: #2d1b2e;
          --container-bg: #3d2b3e;
          --text-color: #e0d0e0;
          --primary-pink: #ff6bb3;
          --shadow-color: rgba(0,0,0,0.3);
        }

        body { 
          font-family: Arial, sans-serif; 
          text-align: center; 
          background-color: var(--bg-color); 
          color: var(--text-color); 
          padding: 50px; 
          margin: 0; 
          min-height: 100vh; 
          display: flex; 
          flex-direction: column; 
          justify-content: center; 
          align-items: center; 
          transition: background-color 0.3s ease, color 0.3s ease;
        }
        .container { 
          background-color: var(--container-bg); 
          padding: 40px; 
          border-radius: 15px; 
          box-shadow: 0 4px 10px var(--shadow-color); 
          max-width: 1400px; 
          transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        h1 { 
          color: var(--primary-pink); 
          margin-bottom: 20px; 
          transition: color 0.3s ease;
        }
        p { margin: 15px 0; line-height: 1.6; }
        .maintenance-icon { font-size: 4rem; margin-bottom: 20px; }
        .theme-switcher {
          position: fixed;
          bottom: 20px;
          right: 20px;
          z-index: 1000;
          background-color: var(--container-bg);
          border: 2px solid var(--primary-pink);
          border-radius: 50%;
          width: 50px;
          height: 50px;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 24px;
          transition: all 0.3s ease;
          box-shadow: 0 4px 10px var(--shadow-color);
        }
        .theme-switcher:hover {
          transform: scale(1.1);
          background-color: var(--primary-pink);
          color: white;
        }
      </style>
    </head>
    <body>
      <div class='theme-switcher' id='themeSwitcher' title='Toggle Dark Mode'>
        🌙
      </div>
      <div class='container'>
        <div class='maintenance-icon'>🚧</div>
        <h1>Maintenance</h1>
        <p><strong>Site Under Maintenance</strong></p>
        <p>We're performing scheduled maintenance to improve our services.</p>
        <p>Please try again later. Thank you for your patience!</p>
        <p style="margin-top: 15px;"><a href="https://status.girlskissing.dev" target="_blank" style="color: var(--primary-pink); text-decoration: underline;">Check System Status Page</a></p>
      </div>

      <script>
        // Theme switcher functionality
        const themeSwitcher = document.getElementById('themeSwitcher');
        const body = document.body;

        // Load saved theme
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
          body.setAttribute('data-theme', 'dark');
          themeSwitcher.textContent = '☀️';
        }

        // Theme toggle
        themeSwitcher.addEventListener('click', () => {
          const isDark = body.getAttribute('data-theme') === 'dark';
          
          if (isDark) {
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
    <?php
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}

// Check form maintenance mode - block public users but allow admins
if ($form_maintenance_active && !$is_admin) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Form Maintenance - basement application form</title>
        <style>
            :root {
                --bg-color: #ffc0cb;
                --container-bg: #fff0f5;
                --text-color: #333;
                --primary-pink: #ff1493;
                --secondary-pink: #ff69b4;
                --border-color: #ff69b4;
                --shadow-color: rgba(0,0,0,0.1);
            }

            [data-theme="dark"] {
                --bg-color: #2d1b2e;
                --container-bg: #3d2b3e;
                --text-color: #e0d0e0;
                --primary-pink: #ff6bb3;
                --secondary-pink: #d147a3;
                --border-color: #d147a3;
                --shadow-color: rgba(0,0,0,0.3);
            }

            body {
                font-family: Arial, sans-serif;
                background-color: var(--bg-color);
                color: var(--text-color);
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background-color 0.3s ease, color 0.3s ease;
            }
            .maintenance-container {
                text-align: center;
                background-color: var(--container-bg);
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 4px 20px var(--shadow-color);
                max-width: 500px;
                border: 3px solid var(--secondary-pink);
                transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
            }
            h1 { 
                color: var(--primary-pink); 
                margin-bottom: 20px; 
                transition: color 0.3s ease;
            }
            p { 
                margin-bottom: 15px; 
                line-height: 1.6; 
            }
            .maintenance-icon { 
                font-size: 3rem; 
                margin-bottom: 20px; 
            }
            .admin-note {
                background-color: rgba(255, 20, 147, 0.1);
                padding: 15px;
                border-radius: 8px;
                margin-top: 20px;
                border: 1px solid var(--primary-pink);
                transition: border-color 0.3s ease;
            }
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
                font-size: 24px;
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
        <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">
            🌙
        </div>
        <div class="maintenance-container">
            <div class="maintenance-icon">📝</div>
            <h1>Application Form Temporarily Closed</h1>
            <p>The application form is currently in maintenance mode for admin testing and updates.</p>
            <p>We appreciate your patience while we improve the application process.</p>
            <p>Please check back later or contact us if you have any questions.</p>
            <p style="margin-top: 15px;"><a href="https://status.girlskissing.dev" target="_blank" style="color: var(--primary-pink); text-decoration: underline;">Check System Status Page</a></p>
        </div>

        <script>
            // Theme switcher functionality
            const themeSwitcher = document.getElementById('themeSwitcher');
            const body = document.body;

            // Load saved theme
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'dark') {
                body.setAttribute('data-theme', 'dark');
                themeSwitcher.textContent = '☀️';
            }

            // Theme toggle
            themeSwitcher.addEventListener('click', () => {
                const isDark = body.getAttribute('data-theme') === 'dark';
                
                if (isDark) {
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
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>basement application form</title>
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
      --banner-bg: #fff0f5;
      --banner-text: #ff1493;
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
      --banner-bg: #4a3a4a;
      --banner-text: #ff6bb3;
    }

    body {
      font-family: Arial, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      margin: 0;
      padding: 0;
      min-height: 100vh;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .main-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
      flex-direction: column;
    }

    .container {
      background-color: var(--container-bg);
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 4px 15px var(--shadow-color);
      max-width: 1400px;
      width: 100%;
      position: relative;
    }

    h1 {
      text-align: center;
      color: var(--primary-pink);
      margin-bottom: 30px;
      font-size: 2em;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: var(--text-color);
    }

    input[type="text"], 
    input[type="email"], 
    input[type="tel"], 
    select, 
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background-color: var(--input-bg);
      color: var(--text-color);
      font-size: 16px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      box-sizing: border-box;
    }

    input:focus, 
    select:focus, 
    textarea:focus {
      outline: none;
      border-color: var(--primary-pink);
      box-shadow: 0 0 8px rgba(255, 20, 147, 0.3);
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
      margin: 0;
      transform: scale(1.2);
    }

    .checkbox-group label {
      margin: 0;
      font-weight: normal;
      cursor: pointer;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .cage-nights {
      background-color: var(--banner-bg);
      padding: 15px;
      border-radius: 8px;
      border: 1px solid var(--border-color);
      margin-bottom: 20px;
    }

    .cage-nights h3 {
      margin: 0 0 15px 0;
      color: var(--primary-pink);
      font-size: 1.1em;
    }

    .nights-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 10px;
    }

    .night-checkbox {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px;
      background-color: var(--input-bg);
      border-radius: 5px;
      border: 1px solid var(--border-color);
      transition: background-color 0.3s ease;
    }

    .night-checkbox:hover {
      background-color: var(--primary-pink);
      color: white;
    }

    .night-checkbox input[type="checkbox"] {
      width: auto;
      margin: 0;
    }

    .night-checkbox label {
      margin: 0;
      font-weight: normal;
      cursor: pointer;
      font-size: 0.9em;
    }

    .submit-btn {
      width: 100%;
      padding: 15px;
      background-color: var(--primary-pink);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .submit-btn:hover {
      background-color: var(--secondary-pink);
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(255, 20, 147, 0.4);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

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

    .status-link {
      text-align: center;
      margin-top: 20px;
    }

    .status-link a {
      color: var(--primary-pink);
      text-decoration: none;
      font-weight: bold;
      transition: color 0.3s ease;
    }

    .status-link a:hover {
      color: var(--secondary-pink);
      text-decoration: underline;
    }

    /* All the missing CSS from index.html */
    #notice-banner {
      background-color: var(--banner-bg);
      color: var(--banner-text);
      padding: 15px 20px;
      margin: 0;
      font-weight: bold;
      text-align: center;
      box-shadow: 0 4px 10px var(--shadow-color);
      word-wrap: break-word;
      white-space: normal;
      width: 100%;
      box-sizing: border-box;
      border-bottom: 2px solid var(--secondary-pink);
      opacity: 0;
      transform: translateY(-20px);
      animation: fadeSlideDown 1.2s 0.3s forwards;
      transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 100;
    }

    #notice-banner:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(255,20,147,0.2);
    }

    #form-container, #maintenance {
      background-color: var(--container-bg);
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px var(--shadow-color);
      text-align: center;
      max-width: 1400px;
      width: 90%;
      box-sizing: border-box;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 1s forwards;
      margin-top: 60px;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }

    h1 {
      color: var(--primary-pink);
      margin-bottom: 20px;
      opacity: 1;
      transform: translateY(0);
      transition: color 0.3s ease;
    }

    @keyframes fadeSlideDown {
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }

    input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 1rem;
      box-sizing: border-box;
      transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
      background-color: var(--input-bg);
      color: var(--text-color);
    }

    input[type="text"]:focus, input[type="email"]:focus, input[type="tel"]:focus,
    input[type="number"]:focus, textarea:focus {
      transform: scale(1.02);
      box-shadow: 0 0 5px rgba(255,20,147,0.5);
      outline: none;
    }

    .radio-group {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin: 15px 0;
      flex-wrap: wrap;
    }

    .radio-group label {
      background-color: var(--secondary-pink);
      color: white;
      padding: 10px 25px;
      border-radius: 12px;
      cursor: pointer;
      font-weight: bold;
      transition: background-color 0.3s, transform 0.2s;
      min-width: 80px;
      text-align: center;
    }

    .radio-group input[type="radio"] { display: none; }

    .radio-group input[type="radio"]:checked + label {
      background-color: var(--primary-pink);
      transform: scale(1.05);
    }

    .radio-group label:hover { transform: scale(1.05); }

    button {
      padding: 12px 25px;
      background-color: var(--secondary-pink);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s, transform 0.2s;
      margin-top: 10px;
      width: 100%;
      max-width: 200px;
    }

    button:hover {
      background-color: var(--primary-pink);
      animation: pulse 0.6s ease-in-out;
    }

    button:active { transform: scale(0.95); }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

    .form-group {
      position: relative;
      margin: 15px 0;
    }

    .custom-dropdown {
      position: relative;
      width: 100%;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background-color: var(--input-bg);
      cursor: pointer;
      padding: 10px;
      box-sizing: border-box;
      font-size: 1rem;
      transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s ease, border-color 0.3s ease;
      z-index: 1;
    }

    .custom-dropdown .selected {
      color: var(--text-color);
      transition: color 0.3s ease;
    }

    .custom-dropdown .options {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background-color: var(--input-bg);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      margin-top: 5px;
      display: none;
      max-height: 200px;
      overflow-y: auto;
      z-index: 10;
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .custom-dropdown .options div {
      padding: 10px;
      cursor: pointer;
      transition: background-color 0.2s, transform 0.2s, color 0.3s ease;
      color: var(--text-color);
    }

    .custom-dropdown .options div:hover {
      background-color: var(--secondary-pink);
      color: #fff;
      transform: scale(1.02);
    }

    .custom-dropdown.active {
      box-shadow: 0 0 5px rgba(255,20,147,0.5);
      transform: scale(1.02);
    }

    .custom-dropdown.active .options {
      display: block;
      z-index: 10;
    }

    .checkbox-container {
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      margin: 15px 0;
      cursor: pointer;
      font-size: 0.95rem;
      user-select: none;
      color: var(--text-color);
      transition: color 0.3s ease;
      gap: 10px;
    }

    .checkbox-container input {
      position: relative;
      opacity: 0;
      cursor: pointer;
      height: 20px;
      width: 20px;
      margin: 0;
    }

    .checkbox-container .checkmark {
      position: absolute;
      top: 50%;
      left: 0;
      transform: translateY(-50%);
      height: 20px;
      width: 20px;
      background-color: var(--input-bg);
      border: 2px solid var(--secondary-pink);
      border-radius: 5px;
      transition: 0.3s all;
    }

    .checkbox-container input:checked ~ .checkmark {
      background-color: var(--primary-pink);
      border-color: var(--primary-pink);
    }

    .checkbox-container .checkmark:after {
      content: "";
      position: absolute;
      display: none;
    }

    .checkbox-container input:checked ~ .checkmark:after {
      display: block;
      left: 6px;
      top: 2px;
      width: 6px;
      height: 12px;
      border: solid white;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    .theme-switcher {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 1000;
      background-color: var(--container-bg);
      border: 2px solid var(--secondary-pink);
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
      opacity: 0;
      transform: scale(0.8);
      animation: fadeScale 0.3s 0.2s forwards;
    }

    .theme-switcher:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 15px var(--shadow-color);
      background-color: var(--secondary-pink);
      color: white;
    }
    
    .admin-panel-button {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      background-color: var(--container-bg);
      border: 2px solid gold;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px var(--shadow-color);
      opacity: 0;
      transform: scale(0.8);
      animation: fadeScale 0.3s 0.2s forwards;
    }
    
    /* Animation for fading in buttons */
    @keyframes fadeScale {
      from {
        opacity: 0;
        transform: scale(0.8);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    .admin-panel-button:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 15px var(--shadow-color);
      background-color: gold;
      color: white;
    }

    @keyframes fadeScale {
      to { 
        opacity: 1; 
        transform: scale(1);
      }
    }

    footer { 
      font-size: 0.9rem; 
      color: var(--text-color); 
      padding-top: 20px; 
      text-align: center; 
      transition: color 0.3s ease; 
    }

    @media (max-width: 500px) {
      #form-container, #maintenance { 
        padding: 20px; 
        margin-top: 70px;
        width: 95%;
        max-width: none;
      }
      input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea { 
        font-size: 0.9rem; 
        padding: 12px;
      }
      .radio-group { 
        flex-direction: column;
        gap: 10px;
        align-items: center;
      }
      .radio-group label { 
        padding: 12px 20px; 
        font-size: 0.9rem; 
        min-width: 120px;
        width: 100%;
        max-width: 200px;
      }
      button { 
        font-size: 0.95rem; 
        max-width: 100%; 
        padding: 14px 25px;
      }
      h1 {
        font-size: 1.8rem;
        margin-bottom: 15px;
      }
      .custom-dropdown {
        font-size: 0.9rem;
        padding: 12px;
      }
      .custom-dropdown .options div {
        padding: 12px;
        font-size: 0.9rem;
      }
      .checkbox-container {
        font-size: 0.9rem;
        padding-left: 35px;
      }
      .checkbox-container .checkmark {
        height: 22px;
        width: 22px;
      }
      .checkbox-container input:checked ~ .checkmark:after {
        left: 7px;
        top: 3px;
        width: 6px;
        height: 12px;
      }
      .theme-switcher {
        width: 50px;
        height: 50px;
        font-size: 20px;
        bottom: 15px;
        right: 15px;
      }
    }

    /* Banner styles */
    #notice-banner {
      padding: 15px 20px;
      margin: 0;
      font-weight: bold;
      text-align: center;
      box-shadow: 0 4px 10px var(--shadow-color);
      word-wrap: break-word;
      white-space: normal;
      width: 100%;
      box-sizing: border-box;
      opacity: 0;
      transform: translateY(-20px);
      animation: fadeSlideDown 1.2s 0.3s forwards;
      transition: transform 0.2s, box-shadow 0.2s;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 100;
    }

    .banner-info { 
      background-color: #d1ecf1 !important; 
      color: #0c5460 !important; 
      border-bottom: 2px solid #bee5eb !important; 
    }
    .banner-warning { 
      background-color: #fff3cd !important; 
      color: #856404 !important; 
      border-bottom: 2px solid #ffeaa7 !important; 
    }
    .banner-error { 
      background-color: #f8d7da !important; 
      color: #721c24 !important; 
      border-bottom: 2px solid #f5c6cb !important; 
    }
    .banner-success { 
      background-color: #d4edda !important; 
      color: #155724 !important; 
      border-bottom: 2px solid #c3e6cb !important; 
    }

    [data-theme="dark"] .banner-info { 
      background-color: #0c5460 !important; 
      color: #d1ecf1 !important; 
      border-bottom: 2px solid #0c5460 !important; 
    }
    [data-theme="dark"] .banner-warning { 
      background-color: #856404 !important; 
      color: #fff3cd !important; 
      border-bottom: 2px solid #856404 !important; 
    }
    [data-theme="dark"] .banner-error { 
      background-color: #721c24 !important; 
      color: #f8d7da !important; 
      border-bottom: 2px solid #721c24 !important; 
    }
    [data-theme="dark"] .banner-success { 
      background-color: #155724 !important; 
      color: #d4edda !important; 
      border-bottom: 2px solid #155724 !important; 
    }

    @media (max-width: 768px) {
      .main-container {
        padding: 10px;
      }

      .container {
        padding: 20px;
      }

      .form-row {
        grid-template-columns: 1fr;
      }

      .nights-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      h1 {
        font-size: 1.5em;
      }

      .theme-switcher {
        width: 45px;
        height: 45px;
        font-size: 18px;
      }
    }

    @media (max-width: 480px) {
      .nights-grid {
        grid-template-columns: 1fr;
      }
    }

    /* User Navigation Styles */
    <?php echo getUserNavbarCSS(); ?>
  </style>
</head>
<body>
  <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">
    🌙
  </div>
  
  <?php if ($is_admin): ?>
  <div class="admin-panel-button" id="adminPanelButton" title="Go to Admin Panel">
    👑
  </div>
  <?php endif; ?>

  <?php if ($logout_message): ?>
    <div id="logout-banner" style="background-color: var(--success-color); color: white; padding: 10px; text-align: center; margin-bottom: 20px; border-radius: 8px;">
      ✅ You have been successfully logged out. Thank you for visiting!
    </div>
  <?php endif; ?>

  <?php if ($banner_settings['enabled'] && !empty($banner_settings['text'])): ?>
    <?php
    $emoji = '';
    switch($banner_settings['type']) {
        case 'info': $emoji = 'ℹ️'; break;
        case 'warning': $emoji = '⚠️'; break;
        case 'error': $emoji = '❌'; break;
        case 'success': $emoji = '✅'; break;
        default: $emoji = 'ℹ️'; break;
    }
    ?>
    <div id="notice-banner" class="banner-<?= htmlspecialchars($banner_settings['type']) ?>">
      <?= $emoji ?> <?= htmlspecialchars($banner_settings['text']) ?>
    </div>
  <?php endif; ?>

  <!-- User Navigation -->
  <?php renderUserNavbar('index.php', true); ?>

  <div class="main-container">
    <div id="form-container">
      <h1>basement application form</h1>
      
      <?php
      // Log visitor access to the main form
      logVisitor($conn, 'main_form', 'view');
      ?>
      
      <form action="submit.php" method="POST" id="applicationForm">
      <input type="text" name="name" placeholder="name" required>
      <input type="email" name="email" placeholder="email" required>
      <input type="tel" name="gfphone" placeholder="girlfriend's phone number (optional)">
      <textarea name="reason" rows="4" placeholder="why did you apply" required></textarea>
      <input type="number" name="cage" placeholder="how many times have you slept in a cage this week" min="0" max="7" required>
      <div class="form-group">
  <label class="animated-label">preferred location to work at (optional)</label>
  <div class="custom-dropdown" id="locationDropdown">
    <div class="selected">Select a location</div>
    <div class="options">
      <div data-value="Trg Sv. Martina 8, 40313, Sveti Martin na Muri, Croatia">Trg Sv. Martina 8, 40313, Sveti Martin na Muri, Croatia</div>
      <div data-value="Vršanska ul. 18 A, 51500, Krk, Croatia">Vršanska ul. 18 A, 51500, Krk, Croatia</div>
      <div data-value="Gundulićeva poljana 4, 20230, Ston, Croatia">Gundulićeva poljana 4, 20230, Ston, Croatia</div>
      <div data-value="Station Rd, Epsom, Esher KT19 8EW, United Kingdom">Station Rd, Epsom, Esher KT19 8EW, United Kingdom (Shipment via post in a cage needed)</div>
      <div data-value="Other">Other (Please note which via email)</div>
    </div>
    <input type="hidden" name="preferredLocation" required>
  </div>
</div>
    <label style="font-weight:bold; display:block; margin-top:10px;">Are you a cat?</label>
<div class="radio-group">
  <input type="radio" id="catYes" name="isCat" value="Yes" required>
  <label for="catYes">Yes</label>

  <input type="radio" id="catNo" name="isCat" value="No" required>
  <label for="catNo">No</label>
</div>
      <!-- Optional owner field -->
      <input type="text" name="owner" id="ownerField" placeholder="Owner's name and/or email address (for cats only)" style="display:none;">

      <div class="form-group">
  <label class="checkbox-container">
    I agree to the terms of the Privacy Policy
    <input type="checkbox" name="agreeTerms" id="agreeTerms" required>
    <span class="checkmark"></span>
  </label>
</div>

      <button type="submit">Submit</button>

      <p style="margin-top: 15px; font-size: 0.9rem; text-align: center;">
        <a href="privacy-policy.html" style="color:var(--primary-pink); text-decoration:underline;">
          🔒 Privacy Policy
        </a>
        &nbsp;|&nbsp;
        <a href="status-check.html" style="color:var(--primary-pink); text-decoration:underline;">
          📋 Check Application Status
        </a>
      </p>
    </form>
    </div>

  <script>
    // Theme switcher functionality
    const themeSwitcher = document.getElementById('themeSwitcher');
    const body = document.body;

    // Load saved theme
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
      body.setAttribute('data-theme', 'dark');
      themeSwitcher.textContent = '☀️';
    }

    // Theme toggle
    themeSwitcher.addEventListener('click', () => {
      const isDark = body.getAttribute('data-theme') === 'dark';
      
      if (isDark) {
        body.removeAttribute('data-theme');
        themeSwitcher.textContent = '🌙';
        localStorage.setItem('theme', 'light');
      } else {
        body.setAttribute('data-theme', 'dark');
        themeSwitcher.textContent = '☀️';
        localStorage.setItem('theme', 'dark');
      }
    });

    // Form validation
    document.getElementById('applicationForm').addEventListener('submit', function(e) {
      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const address = document.getElementById('address').value.trim();

      if (!name || !email || !address) {
        e.preventDefault();
        alert('Please fill in all required fields (Name, Email, and Address).');
        return;
      }

      // Email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return;
      }
    });

    const catYes = document.getElementById("catYes");
    const catNo = document.getElementById("catNo");
    const ownerField = document.getElementById("ownerField");

    catYes.addEventListener("change", () => { ownerField.style.display = "block"; });
    catNo.addEventListener("change", () => { ownerField.style.display = "none"; });

    // Custom dropdown functionality
    const dropdown = document.getElementById("locationDropdown");
    const selected = dropdown.querySelector(".selected");
    const optionsContainer = dropdown.querySelector(".options");
    const hiddenInput = dropdown.querySelector("input[type='hidden']");

    dropdown.addEventListener("click", () => {
      dropdown.classList.toggle("active");
    });

    optionsContainer.addEventListener("click", (e) => {
      if (e.target.hasAttribute("data-value")) {
        selected.textContent = e.target.textContent;
        hiddenInput.value = e.target.getAttribute("data-value");
        dropdown.classList.remove("active");
      }
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove("active");
      }
    });
    
    // Admin panel button click handler
    const adminPanelButton = document.getElementById('adminPanelButton');
    if (adminPanelButton) {
      adminPanelButton.addEventListener('click', () => {
        window.location.href = 'admin/dashboard.php';
      });
    }
  </script>
  
  <!-- Privacy Policy Notification System -->
  <script src="includes/privacy-notifications.js"></script>
  
  <!-- User Navigation JavaScript -->
  <?php echo getUserNavbarJS(); ?>
  
  <?php
  // Close database connection at the end
  if (isset($conn)) {
      $conn->close();
  }
  ?>
</body>
</html>
