<?php
// Start session for potential logging and user authentication
session_start();

// Database maintenance mode check with fallback
include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Include action logger for logging
require_once 'admin/action_logger.php';

// Include unified user authentication system
require_once 'user_auth.php';

// Check user authentication status
$is_logged_in = isLoggedIn();
$current_user = $is_logged_in ? getUserData() : null;
$user_id = $current_user ? $current_user['id'] : null;

// Function to log visitor activity
function logVisitor($conn, $page = 'submit_form', $action = 'submit') {
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
        
        // Add form data for submissions
        $form_data = [];
        if ($action === 'submit') {
            $form_data = [
                'name' => isset($_POST['name']) ? $_POST['name'] : null,
                'email' => isset($_POST['email']) ? $_POST['email'] : null,
                'page' => $page,
                'action' => $action,
                'referrer' => $referrer
            ];
        } else {
            $form_data = [
                'page' => $page,
                'action' => $action,
                'referrer' => $referrer
            ];
        }
        
        // Check if action_logs table exists before logging
        $table_check = $conn->query("SHOW TABLES LIKE 'action_logs'");
        if ($table_check && $table_check->num_rows > 0) {
            $action_type = 'VISITOR_' . strtoupper($action);
            $description = "Visitor $action on $page page";
            $target_type = 'page';
            $additional_data = json_encode($form_data);
            
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
    logBannedIPAccess('submit.php', $ban_reason);
    
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

$maintenance_active = false;
if (!$conn->connect_error) {
    // Log visitor to the submit page
    logVisitor($conn, 'submit_form', 'visit');
    
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
      <title>Maintenance - Applications Temporarily Closed</title>
      <style>
        body { 
          font-family: Arial, sans-serif; 
          text-align: center; 
          background-color: #ffc0cb; 
          color: #333; 
          padding: 50px; 
          margin: 0; 
          min-height: 100vh; 
          display: flex; 
          flex-direction: column; 
          justify-content: center; 
          align-items: center; 
        }
        .container { 
          background-color: #fff0f5; 
          padding: 40px; 
          border-radius: 15px; 
          box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
          max-width: 600px; 
        }
        h1 { color: #ff1493; margin-bottom: 20px; }
        p { margin: 15px 0; line-height: 1.6; }
        .maintenance-icon { font-size: 4rem; margin-bottom: 20px; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='maintenance-icon'>🚧</div>
        <h1>Maintenance</h1>
        <p><strong>Applications are temporarily closed while we perform maintenance.</strong></p>
        <p><small>Please try again later. Thank you for your patience!.</small></p>
      </div>
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

// Check if IP is banned (skip check for admins)
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$ip_banned = false;

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
        $ban_check_sql = "SELECT id FROM banned_ips WHERE ip_address = ? AND is_active = 1 LIMIT 1";
        $ban_stmt = $conn->prepare($ban_check_sql);
        $ban_stmt->bind_param("s", $user_ip);
        $ban_stmt->execute();
        $ban_result = $ban_stmt->get_result();
        $ip_banned = ($ban_result->num_rows > 0);
        $ban_stmt->close();
    }
}

if ($ip_banned) {
    http_response_code(403);
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>Access Restricted - basement application form</title>
      <style>
        body { 
          font-family: Arial, sans-serif; 
          text-align: center; 
          background-color: #ffc0cb; 
          color: #333; 
          padding: 50px; 
          margin: 0; 
          min-height: 100vh; 
          display: flex; 
          flex-direction: column; 
          justify-content: center; 
          align-items: center; 
        }
        .container { 
          background-color: #fff0f5; 
          padding: 40px; 
          border-radius: 15px; 
          box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
          max-width: 600px; 
          border: 3px solid #ff1493;
        }
        h1 { color: #ff1493; margin-bottom: 20px; }
        p { margin: 15px 0; line-height: 1.6; }
        .icon { font-size: 4rem; margin-bottom: 20px; }
        a { color: #ff1493; text-decoration: underline; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='icon'>🚫</div>
        <h1>Access Restricted</h1>
        <p><strong>Your IP address has been restricted from submitting applications and/or checking application status.</strong></p>
        <p>If you believe this is an error, please contact support.</p>
      </div>
    </body>
    </html>";
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}

// Include action logger for application submission logging
require_once 'admin/action_logger.php';

// Collect data safely
$name              = $_POST['name'] ?? '';
$email             = $_POST['email'] ?? '';
$gfphone           = $_POST['gfphone'] ?? '';
$reason            = $_POST['reason'] ?? '';
$cage              = $_POST['cage'] ?? 0;
$isCat             = $_POST['isCat'] ?? '';
$owner             = $_POST['owner'] ?? ''; // optional owner field
$preferredLocation = $_POST['preferredLocation'] ?? ''; // new field
$agreeTerms = isset($_POST['agreeTerms']) ? 1 : 0; // 1 if checked, 0 if not
$status = 'unreviewed'; // Default status for new applications

// Generate application ID first
$timestamp = date('Ymd');
$randomSuffix = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
$applicationId = 'APP-' . $timestamp . '-' . $randomSuffix;

// Insert into database
$sql = "INSERT INTO applicants (application_id, name, email, gfphone, reason, cage, isCat, owner, preferredLocation, agreeTerms, status, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssisssissi", $applicationId, $name, $email, $gfphone, $reason, $cage, $isCat, $owner, $preferredLocation, $agreeTerms, $status, $user_id);

$success = $stmt->execute();
$errorMsg = $stmt->error;

// Log the application submission if successful (before closing connection)
if ($success) {
    // Log using the admin action logger
    logApplicationSubmission($applicationId, $name, $email);
    
    // Log using the visitor logger with more details
    logVisitor($conn, 'submit_form', 'submit_success');
} else {
    // Log submission failure
    logVisitor($conn, 'submit_form', 'submit_failed');
}

$stmt->close();

// If successful submission and user is logged in, optionally redirect to dashboard
if ($success && $is_logged_in && isset($_POST['redirect_to_dashboard'])) {
    $conn->close();
    header("Location: dashboard.php?status=application_submitted");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Application Status</title>
  <style>
    :root {
      --bg-color: #ffc0cb;
      --container-bg: #fff0f5;
      --text-color: #333;
      --primary-pink: #ff1493;
      --secondary-pink: #ff69b4;
      --shadow-color: rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
      --bg-color: #2d1b2e;
      --container-bg: #3d2b3e;
      --text-color: #e0d0e0;
      --primary-pink: #ff6bb3;
      --secondary-pink: #d147a3;
      --shadow-color: rgba(0,0,0,0.3);
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
      max-width: 400px;
      width: 100%;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }
    h1 {
      color: var(--primary-pink);
      transition: color 0.3s ease;
    }
    .application-id {
      background-color: var(--secondary-pink);
      color: white;
      padding: 15px;
      border-radius: 10px;
      margin: 20px 0;
      font-weight: bold;
      font-size: 1.1rem;
      transition: background-color 0.3s ease;
    }
    p {
      margin: 15px 0;
      color: var(--text-color);
      transition: color 0.3s ease;
    }
    a.button {
      display: inline-block;
      margin: 10px 5px;
      padding: 12px 25px;
      background-color: var(--secondary-pink);
      color: white;
      text-decoration: none;
      border-radius: 8px;
      transition: all 0.3s ease;
      min-width: 120px;
      text-align: center;
    }
    a.button:hover {
      background-color: var(--primary-pink);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(255, 20, 147, 0.3);
    }

    a.button.secondary {
      background-color: var(--primary-pink);
    }
    a.button.secondary:hover {
      background-color: var(--secondary-pink);
    }

    .button-container {
      margin-top: 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      align-items: center;
    }

    @media (min-width: 480px) {
      .button-container {
        flex-direction: row;
        justify-content: center;
      }
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
    }

    .theme-switcher:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 15px var(--shadow-color);
      background-color: var(--secondary-pink);
      color: white;
    }

    @media (max-width: 768px) {
      .container {
        padding: 20px 25px;
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
    <?php if ($success): ?>
      <h1>✅ Application Sent</h1>
      <div class="application-id">
        📋 Application ID: <?= htmlspecialchars($applicationId) ?>
      </div>
      <p>Thanks <?= htmlspecialchars($name) ?> for "applying"! Check your email in a few hours, or use the application checker.</p>
      <?php if ($is_logged_in): ?>
        <p><strong>🎉 Great news!</strong> Since you're logged in, this application has been linked to your account. You can view all your applications in your dashboard.</p>
      <?php endif; ?>
      <p><small>Keep your application ID for reference. You might need it later. It won't be shown to you again.</small></p>
      <div class="button-container">
        <?php if ($is_logged_in): ?>
        <a class="button" href="dashboard.php">🏠 View Dashboard</a>
        <?php endif; ?>
        <a class="button secondary" href="status-check.html?id=<?= urlencode($applicationId) ?>">📋 Check Status</a>
        <a class="button" href="https://girlskissing.dev">Return to Form</a>
      </div>
    <?php else: ?>
      <h1>❌ error</h1>
      <p>it's either you broke something or i did</p>
      <p>Error: <?= htmlspecialchars($errorMsg) ?></p>
      <div class="button-container">
        <a class="button" href="https://girlskissing.dev">Return to Form</a>
      </div>
    <?php endif; ?>
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
