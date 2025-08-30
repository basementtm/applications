<?php
// Start session for potential admin check
session_start();

// Database connection for banner functionality
include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Include action logger for logging
require_once 'admin/action_logger.php';

// Check if user is an admin (for IP ban bypass)
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

// If IP is banned, log the attempt and show banned page
if ($ip_banned) {
    // Log the banned IP access attempt
    logBannedIPAccess('retention.php', $ban_reason);
    
    http_response_code(403);
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>Access Denied</title>
      <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%); margin: 0; padding: 0; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        h1 { color: #ff1744; font-size: 2.5em; margin-bottom: 20px; }
        p { color: #666; font-size: 1.1em; line-height: 1.6; margin-bottom: 30px; }
        .error-code { font-size: 1.5em; color: #ff1744; font-weight: bold; }
      </style>
    </head>
    <body>
      <div class='container'>
        <h1>🚫 Access Denied</h1>
        <div class='error-code'>Error 403 - Forbidden</div>
        <p>Your IP address has been banned from accessing this service.</p>
        <p>If you believe this is an error, please contact the website administrator.</p>
      </div>
    </body>
    </html>";
    exit;
}

// Get banner settings
$banner_settings = [
    'text' => '',
    'enabled' => false,
    'type' => 'info'
];

if (!$conn->connect_error) {
    // Process scheduled maintenance first
    include('includes/scheduled_maintenance_helper.php');
    processScheduledMaintenance($conn);
    
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Data Retention Policy - basement™</title>
  <style>
    :root {
      --bg-color: #ffc0cb;
      --container-bg: #fff0f5;
      --text-color: #333;
      --primary-pink: #ff1493;
      --secondary-pink: #ff69b4;
      --shadow-color: rgba(0,0,0,0.1);
      --highlight-bg: #ffe6f0;
      --contact-bg: #f0f8ff;
    }

    [data-theme="dark"] {
      --bg-color: #2d1b2e;
      --container-bg: #3d2b3e;
      --text-color: #e0d0e0;
      --primary-pink: #ff6bb3;
      --secondary-pink: #d147a3;
      --shadow-color: rgba(0,0,0,0.3);
      --highlight-bg: #4a3a4a;
      --contact-bg: #3a3a4a;
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
      padding: 20px;
      flex-direction: column;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    #policy-container {
      background-color: var(--container-bg);
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px var(--shadow-color);
      text-align: center;
      max-width: 600px;
      width: 90%;
      box-sizing: border-box;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 1s forwards;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }

    h1 {
      color: var(--primary-pink);
      margin-bottom: 20px;
      font-size: 2em;
      transition: color 0.3s ease;
    }

    h2 {
      color: var(--secondary-pink);
      margin-top: 25px;
      margin-bottom: 15px;
      font-size: 1.3em;
      transition: color 0.3s ease;
    }

    p, li {
      line-height: 1.6;
      margin-bottom: 10px;
      color: var(--text-color);
      transition: color 0.3s ease;
    }

    ul {
      text-align: left;
      padding-left: 20px;
      margin: 15px 0;
    }

    .highlight {
      background-color: var(--highlight-bg);
      padding: 15px;
      border-radius: 8px;
      border-left: 4px solid var(--primary-pink);
      margin: 20px 0;
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .contact-info {
      background-color: var(--contact-bg);
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
      border-left: 4px solid var(--secondary-pink);
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .back-link {
      margin-top: 25px;
    }

    .back-link a {
      color: var(--primary-pink);
      text-decoration: none;
      font-weight: bold;
      padding: 10px 20px;
      border: 2px solid var(--primary-pink);
      border-radius: 25px;
      transition: all 0.3s ease;
    }

    .back-link a:hover {
      background-color: var(--primary-pink);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(255, 20, 147, 0.3);
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

    /* Banner styles */
    #notice-banner {
      padding: 15px 20px;
      margin: 0;
      font-weight: bold;
      text-align: center;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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

    @keyframes fadeSlideDown {
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeScale {
      to { 
        opacity: 1; 
        transform: scale(1);
      }
    }

    @keyframes fadeUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      #policy-container {
        padding: 20px 25px;
        margin-top: 60px;
      }
      
      h1 {
        font-size: 1.6em;
      }
      
      h2 {
        font-size: 1.2em;
      }

      .theme-switcher {
        width: 50px;
        height: 50px;
        font-size: 20px;
        bottom: 15px;
        right: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">
    🌙
  </div>

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
  
  <div id="policy-container">
    <h1>Data Retention Policy</h1>
    <p>
      All personal data submitted through the basement™ application form is stored securely on our servers for a maximum of 24-48 months.  
      Once the application has been reviewed by our team, all submitted information is still stored on our systems.
    </p>
    <p>
      This ensures that your personal data is not only used for the purpose of evaluating your application and is kept longer than necessary.
    </p>
    <a class="button" href="https://girlskissing.dev/">Back to Application Form</a>
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
  
  <?php
  // Close database connection at the end
  if (isset($conn)) {
      $conn->close();
  }
  ?>
</body>
</html>
