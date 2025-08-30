<?php
// Start session for potential logging
session_start();

// Database maintenance mode check with fallback
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
    logBannedIPAccess('check-status.php', $ban_reason);
    
    http_response_code(403);
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>Access Denied</title>
      <style>
        :root {
          --bg-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
          --container-bg: white;
          --text-color: #666;
          --heading-color: #ff1744;
          --shadow-color: rgba(0,0,0,0.1);
        }

        [data-theme='dark'] {
          --bg-gradient: linear-gradient(135deg, #4a1d2f 0%, #432741 50%, #3d2b3e 100%);
          --container-bg: #3d2b3e;
          --text-color: #e0d0e0;
          --heading-color: #ff6bb3;
          --shadow-color: rgba(0,0,0,0.3);
        }

        body { 
          font-family: Arial, sans-serif; 
          background: var(--bg-gradient); 
          margin: 0; 
          padding: 0; 
          height: 100vh; 
          display: flex; 
          align-items: center; 
          justify-content: center;
          transition: background 0.3s ease;
        }
        .container { 
          background: var(--container-bg); 
          padding: 40px; 
          border-radius: 20px; 
          box-shadow: 0 10px 30px var(--shadow-color); 
          text-align: center; 
          max-width: 500px;
          transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        h1 { 
          color: var(--heading-color); 
          font-size: 2.5em; 
          margin-bottom: 20px;
          transition: color 0.3s ease;
        }
        p { 
          color: var(--text-color); 
          font-size: 1.1em; 
          line-height: 1.6; 
          margin-bottom: 30px;
          transition: color 0.3s ease;
        }
        .error-code { 
          font-size: 1.5em; 
          color: var(--heading-color); 
          font-weight: bold;
          transition: color 0.3s ease;
        }
        .theme-switcher {
          position: fixed;
          bottom: 20px;
          right: 20px;
          z-index: 1000;
          background-color: var(--container-bg);
          border: 2px solid var(--heading-color);
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
          background-color: var(--heading-color);
          color: white;
        }
      </style>
    </head>
    <body>
      <div class='theme-switcher' id='themeSwitcher' title='Toggle Dark Mode'>
        🌙
      </div>
      <div class='container'>
        <h1>🚫 Access Denied</h1>
        <div class='error-code'>Error 403 - Forbidden</div>
        <p>Your IP address has been banned from accessing this service.</p>
        <p>If you believe this is an error, please contact the website administrator.</p>
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
    </html>";
    exit;
}

$maintenance_active = false;
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
    }
    
    // Process scheduled maintenance
    include('includes/scheduled_maintenance_helper.php');
    processScheduledMaintenance($conn);
}

if ($maintenance_active) {
    http_response_code(503);
    header("Retry-After: 3600");
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>Maintenance - Status Check Temporarily Unavailable</title>
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
          max-width: 600px; 
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
        <p><strong>Application status checking is temporarily unavailable.</strong></p>
        <p>We're performing scheduled maintenance to improve our services.</p>
        <p>Please try again later. Thank you for your patience!</p>
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
    </html>";
    exit();
}

// Continue with existing database connection or create new one if maintenance check failed
if ($conn->connect_error) { 
    include('/var/www/config/db_config.php');
    $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
}

$application_id = $_POST['application_id'] ?? '';
$application_data = null;
$errorMsg = '';

if (!empty($application_id)) {
    // Sanitize and validate the application ID format
    $application_id = trim($application_id);
    if (!preg_match('/^APP-\d{8}-\d{6}$/', $application_id)) {
        $errorMsg = "Invalid application ID format. Please use format: APP-YYYYMMDD-XXXXXX";
    } else {
        // Query the database for the application
        $sql = "SELECT application_id, name, email, status, cage, isCat, preferredLocation FROM applicants WHERE application_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $application_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $application_data = $result->fetch_assoc();
            // Log successful status check
            logStatusCheck($application_id, true, $application_data['name']);
        } else {
            $errorMsg = "Application not found. Please check your application ID and try again.";
            // Log failed status check attempt
            logStatusCheck($application_id, false);
        }
        $stmt->close();
    }
} else {
    $errorMsg = "Please enter an application ID.";
}

$conn->close();

// Status display mapping
$status_display = [
    'unreviewed' => ['🕐', 'Under Review', 'Your application is being reviewed by our team.', '#ff69b4'],
    'denied' => ['❌', 'Application Denied', 'Unfortunately, your application was not successful at this time.', '#ff4757'],
    'stage2' => ['📞', 'Stage 2 (Interview)', 'Congratulations! You\'ve been selected for an interview. Please check your email for details.', '#ffa502'],
    'stage3' => ['⭐', 'Stage 3 (Final Review)', 'Your application and interview is in the final review stage.', '#3742fa'],
    'accepted' => ['✅', 'Accepted', 'Congratulations! Your application has been accepted!', '#2ed573'],
    'invalid' => ['⚠️', 'Invalid Application', 'This application contained invalid information (usually email) or the application has been flagged for review.', '#e67e22']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Status - basement application form</title>
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

    body {
      font-family: Arial, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      text-align: center;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .container {
      background-color: var(--container-bg);
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px var(--shadow-color);
      max-width: 500px;
      width: 100%;
      margin-top: 60px;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }

    h1 {
      color: var(--primary-pink);
      margin-bottom: 20px;
      transition: color 0.3s ease;
    }

    .status-display {
      background: linear-gradient(135deg, var(--container-bg), var(--input-bg));
      border: 2px solid;
      border-radius: 12px;
      padding: 25px;
      margin: 20px 0;
      font-size: 1.1rem;
      font-weight: bold;
      transition: background 0.3s ease;
    }

    /* Status-specific styling for dark mode compatibility */
    .status-unreviewed { --status-color: #ff69b4; }
    .status-denied { --status-color: #ff6b6b; }
    .status-stage2 { --status-color: #ffa726; }
    .status-stage3 { --status-color: #5c6bc0; }
    .status-accepted { --status-color: #66bb6a; }
    .status-invalid { --status-color: #e67e22; }

    [data-theme="dark"] .status-unreviewed { --status-color: #ff8cc8; }
    [data-theme="dark"] .status-denied { --status-color: #ff7979; }
    [data-theme="dark"] .status-stage2 { --status-color: #fdcb6e; }
    [data-theme="dark"] .status-stage3 { --status-color: #a29bfe; }
    [data-theme="dark"] .status-accepted { --status-color: #6c5ce7; }
    [data-theme="dark"] .status-invalid { --status-color: #f39c12; }

    .application-details {
      background-color: var(--input-bg);
      border-radius: 10px;
      padding: 20px;
      margin: 20px 0;
      text-align: left;
      transition: background-color 0.3s ease;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid var(--border-color);
      transition: border-color 0.3s ease;
    }

    .detail-row:last-child {
      border-bottom: none;
    }

    .detail-label {
      font-weight: bold;
      color: var(--primary-pink);
      min-width: 120px;
      transition: color 0.3s ease;
    }

    .detail-value {
      color: var(--text-color);
      text-align: right;
      word-break: break-word;
      transition: color 0.3s ease;
    }

    .error-message {
      background-color: #ffebee;
      color: #c62828;
      border: 2px solid #ff4757;
      border-radius: 10px;
      padding: 20px;
      margin: 20px 0;
      font-weight: bold;
      transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    [data-theme="dark"] .error-message {
      background-color: #4a2c2a;
      color: #ff8a80;
      border-color: #ff6b6b;
    }

    a.button {
      display: inline-block;
      margin: 10px;
      padding: 12px 25px;
      background-color: var(--secondary-pink);
      color: white;
      text-decoration: none;
      border-radius: 8px;
      transition: background-color 0.3s, transform 0.2s;
      font-weight: bold;
    }

    a.button:hover {
      background-color: var(--primary-pink);
      transform: scale(1.02);
    }

    .status-icon {
      font-size: 2rem;
      margin-bottom: 10px;
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

    .theme-switcher:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 15px var(--shadow-color);
      background-color: var(--secondary-pink);
      color: white;
    }

    @media (max-width: 500px) {
      .container { 
        padding: 20px; 
        margin: 10px;
      }
      .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }
      .detail-value {
        text-align: left;
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
  <div class="container">
    <?php if ($application_data): ?>
      <h1>📋 Application Status</h1>
      
      <?php 
      $status = $application_data['status'] ?? 'unreviewed';
      $status_info = $status_display[$status] ?? $status_display['unreviewed'];
      ?>
      
      <div class="status-display status-<?= $status ?>" style="border-color: var(--status-color); color: var(--status-color);">
        <div class="status-icon"><?= $status_info[0] ?></div>
        <div style="font-size: 1.3rem; margin-bottom: 10px;"><?= $status_info[1] ?></div>
        <div style="font-size: 1rem; font-weight: normal; opacity: 0.8; color: var(--text-color);"><?= $status_info[2] ?></div>
      </div>

      <div class="application-details">
        <h3 style="color: var(--primary-pink); margin-top: 0;">Application Details</h3>
        <div class="detail-row">
          <span class="detail-label">Application ID:</span>
          <span class="detail-value"><?= htmlspecialchars($application_data['application_id']) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Name:</span>
          <span class="detail-value"><?= htmlspecialchars($application_data['name']) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Email:</span>
          <span class="detail-value"><?= htmlspecialchars($application_data['email']) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Nights in Cage:</span>
          <span class="detail-value"><?= htmlspecialchars($application_data['cage']) ?>/week</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Cat:</span>
          <span class="detail-value"><?= htmlspecialchars($application_data['isCat']) ?></span>
        </div>
        <?php if ($application_data['preferredLocation']): ?>
        <div class="detail-row">
          <span class="detail-label">Location:</span>
          <span class="detail-value"><?= htmlspecialchars($application_data['preferredLocation']) ?></span>
        </div>
        <?php endif; ?>
      </div>

    <?php elseif ($errorMsg): ?>
      <h1>❌ Error</h1>
      <div class="error-message">
        <?= htmlspecialchars($errorMsg) ?>
      </div>
    <?php endif; ?>

    <a class="button" href="status-check.html">← Check Another Application</a>
    <a class="button" href="index.php">New Application</a>
  </div>

  <script>
    // Theme Switcher
    const themeSwitcher = document.getElementById("themeSwitcher");
    const body = document.body;

    // Check for saved theme preference or default to light mode
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
