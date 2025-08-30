<?php
session_start();

// Check if user is logged in and is Emma (owner)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_username'] !== 'emma') {
    die("Access denied. Only Emma can create database tables.");
}

// Include database config
include('/var/www/config/db_config.php');

$sql = "CREATE TABLE IF NOT EXISTS action_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    action_type VARCHAR(100) NOT NULL,
    action_description TEXT NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    additional_data JSON,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_ip_address (ip_address),
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<h2>✅ Action logs table created successfully!</h2>";
    echo "<p><a href='action-logs.php'>View Action Logs</a> | <a href='owner.php'>Back to Owner Panel</a></p>";
} else {
    echo "<h2>❌ Error creating table:</h2>";
    echo "<p>" . $conn->error . "</p>";
    echo "<p><a href='owner.php'>Back to Owner Panel</a></p>";
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Action Logs Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        h2 { color: #ff69b4; }
        a { color: #ff69b4; text-decoration: none; margin-right: 20px; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
</body>
</html>
