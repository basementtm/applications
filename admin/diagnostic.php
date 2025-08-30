<?php
// Simple diagnostic page to check server setup
echo "<h1>🔍 Admin Panel Diagnostic</h1>";

echo "<h2>PHP Version:</h2>";
echo "PHP " . phpversion() . "<br><br>";

echo "<h2>Required Extensions:</h2>";
echo "MySQLi: " . (extension_loaded('mysqli') ? '✅ Available' : '❌ Missing') . "<br>";
echo "Sessions: " . (extension_loaded('session') ? '✅ Available' : '❌ Missing') . "<br><br>";

echo "<h2>File Paths:</h2>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Parent directory: " . dirname(__DIR__) . "<br>";

$config_paths = [
    __DIR__ . '/../config/db_config.php',
    '/var/www/config/db_config.php',
    dirname(__DIR__) . '/config/db_config.php'
];

echo "<h3>Checking config file locations:</h3>";
foreach ($config_paths as $path) {
    echo "$path: " . (file_exists($path) ? '✅ Found' : '❌ Not found') . "<br>";
}

echo "<h2>Database Connection Test:</h2>";
$found_config = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        echo "Using config: $path<br>";
        include($path);
        $found_config = true;
        break;
    }
}

if ($found_config && isset($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME)) {
    echo "Config loaded successfully<br>";
    
    try {
        $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
        if ($conn->connect_error) {
            echo "❌ Database connection failed: " . $conn->connect_error . "<br>";
        } else {
            echo "✅ Database connected successfully<br>";
            
            // Check if admin_users table exists
            $result = $conn->query("SHOW TABLES LIKE 'admin_users'");
            if ($result->num_rows > 0) {
                echo "✅ admin_users table exists<br>";
                
                // Check if default admin exists
                $admin_check = $conn->query("SELECT username FROM admin_users WHERE username = 'admin'");
                if ($admin_check->num_rows > 0) {
                    echo "✅ Default admin user exists<br>";
                } else {
                    echo "❌ Default admin user not found - run setup.sql<br>";
                }
            } else {
                echo "❌ admin_users table missing - run setup.sql<br>";
            }
            $conn->close();
        }
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Database configuration not found or incomplete<br>";
}

echo "<br><h2>Next Steps:</h2>";
echo "1. Make sure the database config file exists<br>";
echo "2. Run the setup.sql script to create tables<br>";
echo "3. Check file permissions<br>";
echo "4. <a href='login.php'>Try login page again</a><br>";
?>
