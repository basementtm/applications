<?php
// Debug script to test login.php for errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug test...<br>";

// Test session start
session_start();
echo "Session started successfully.<br>";

// Test database config include
$config_path = '/var/www/config/db_config.php';
echo "Checking config path: $config_path<br>";

if (!file_exists($config_path)) {
    echo "ERROR: Database configuration file not found at $config_path<br>";
    
    // Try alternative path
    $alt_config_path = dirname(__DIR__) . '/config/db_config.php';
    echo "Trying alternative path: $alt_config_path<br>";
    
    if (file_exists($alt_config_path)) {
        echo "Found config at alternative path!<br>";
    } else {
        echo "Config not found at alternative path either.<br>";
    }
} else {
    echo "Config file exists.<br>";
    
    // Try to include it
    try {
        include($config_path);
        echo "Config included successfully.<br>";
        
        if (isset($DB_SERVER)) {
            echo "DB_SERVER is set.<br>";
        } else {
            echo "DB_SERVER is not set.<br>";
        }
        
        // Try database connection
        $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
        if ($conn->connect_error) {
            echo "Database connection failed: " . $conn->connect_error . "<br>";
        } else {
            echo "Database connection successful.<br>";
            $conn->close();
        }
        
    } catch (Exception $e) {
        echo "Error including config: " . $e->getMessage() . "<br>";
    }
}

echo "Debug test completed.";
?>
