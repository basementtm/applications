<?php
// Simple test file to check what's wrong with settings.php
session_start();

echo "<h2>🔍 Settings Page Debug</h2>\n";

// Check session
echo "<h3>1. Session Check</h3>\n";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo "✅ User is logged in<br>\n";
    echo "Username: " . htmlspecialchars($_SESSION['admin_username'] ?? 'Not set') . "<br>\n";
    echo "Role: " . htmlspecialchars($_SESSION['admin_role'] ?? 'Not set') . "<br>\n";
} else {
    echo "❌ User is not logged in<br>\n";
    echo "<a href='login.php'>Go to Login</a><br>\n";
}

// Check database connection
echo "<h3>2. Database Connection</h3>\n";
$config_path = '/var/www/config/db_config.php';
if (file_exists($config_path)) {
    echo "✅ Config file exists<br>\n";
    
    try {
        include($config_path);
        $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
        
        if ($conn->connect_error) {
            echo "❌ Database connection failed: " . $conn->connect_error . "<br>\n";
        } else {
            echo "✅ Database connection successful<br>\n";
            
            // Test user query
            if (isset($_SESSION['admin_username'])) {
                $username = $_SESSION['admin_username'];
                $sql = "SELECT username, email, two_factor_enabled, created_at FROM admin_users WHERE username = ? AND active = 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "✅ User data found in database<br>\n";
                    $user_data = $result->fetch_assoc();
                    echo "Email: " . htmlspecialchars($user_data['email'] ?? 'Not set') . "<br>\n";
                    echo "2FA Enabled: " . ($user_data['two_factor_enabled'] ? 'Yes' : 'No') . "<br>\n";
                } else {
                    echo "❌ User not found in database<br>\n";
                }
                $stmt->close();
            }
            $conn->close();
        }
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>\n";
    }
} else {
    echo "❌ Config file not found at: $config_path<br>\n";
}

// Check file permissions
echo "<h3>3. File Check</h3>\n";
$settings_file = __DIR__ . '/settings.php';
if (file_exists($settings_file)) {
    echo "✅ settings.php exists<br>\n";
    echo "File size: " . filesize($settings_file) . " bytes<br>\n";
    if (is_readable($settings_file)) {
        echo "✅ File is readable<br>\n";
    } else {
        echo "❌ File is not readable<br>\n";
    }
} else {
    echo "❌ settings.php not found<br>\n";
}

// Check for PHP errors
echo "<h3>4. PHP Error Check</h3>\n";
if (ini_get('display_errors')) {
    echo "✅ Error display is enabled<br>\n";
} else {
    echo "⚠️ Error display is disabled<br>\n";
}

echo "Error reporting level: " . error_reporting() . "<br>\n";

// Try to include settings.php and catch any errors
echo "<h3>5. Include Test</h3>\n";
ob_start();
$error_occurred = false;

try {
    // Capture any output from settings.php
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        echo "Attempting to include settings.php...<br>\n";
        // Don't actually include it as it might cause issues, just check syntax
        $syntax_check = shell_exec("php -l settings.php 2>&1");
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            echo "✅ Settings.php syntax is valid<br>\n";
        } else {
            echo "❌ Syntax error in settings.php:<br>\n";
            echo "<pre>" . htmlspecialchars($syntax_check) . "</pre>\n";
        }
    } else {
        echo "⚠️ Cannot test include - user not logged in<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error including settings.php: " . $e->getMessage() . "<br>\n";
    $error_occurred = true;
}

$output = ob_get_clean();
echo $output;

if (!$error_occurred) {
    echo "<br><a href='settings.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Try Settings Page</a><br>\n";
}

echo "<br><a href='dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Go to Dashboard</a><br>\n";
?>
