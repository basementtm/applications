<?php
session_start();

// Check if user is logged in and is Emma (owner)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_username'] !== 'emma') {
    die("Access denied. Only Emma can create database tables.");
}

// Include database config
include('/var/www/config/db_config.php');

$sql = "CREATE TABLE IF NOT EXISTS scheduled_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    reason TEXT,
    banner_shown BOOLEAN DEFAULT FALSE,
    warning_banner_shown BOOLEAN DEFAULT FALSE,
    maintenance_started BOOLEAN DEFAULT FALSE,
    maintenance_completed BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_start_time (start_time),
    INDEX idx_end_time (end_time),
    INDEX idx_is_active (is_active)
)";

if ($conn->query($sql) === TRUE) {
    echo "<h2>✅ Scheduled Maintenance table created successfully!</h2>";
    echo "<p>The scheduled_maintenance table has been created with the following structure:</p>";
    echo "<ul>";
    echo "<li><strong>start_time:</strong> When maintenance should begin (CEST timezone)</li>";
    echo "<li><strong>end_time:</strong> When maintenance should end (CEST timezone)</li>";
    echo "<li><strong>reason:</strong> Optional reason for the maintenance</li>";
    echo "<li><strong>banner_shown:</strong> Whether warning banner has been displayed</li>";
    echo "<li><strong>warning_banner_shown:</strong> Whether 1-hour warning banner has been shown</li>";
    echo "<li><strong>maintenance_started:</strong> Whether maintenance has been activated</li>";
    echo "<li><strong>maintenance_completed:</strong> Whether maintenance has been completed</li>";
    echo "<li><strong>created_by:</strong> Admin who scheduled the maintenance</li>";
    echo "<li><strong>is_active:</strong> Whether this schedule is still active</li>";
    echo "</ul>";
    echo "<p><a href='maintenance.php'>Back to Maintenance Control</a> | <a href='owner.php'>Back to Owner Panel</a></p>";
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
    <title>Create Scheduled Maintenance Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; background-color: #ffc0cb; }
        h2 { color: #ff69b4; }
        a { color: #ff69b4; text-decoration: none; margin-right: 20px; }
        a:hover { text-decoration: underline; }
        ul { background: #fff0f5; padding: 20px; border-radius: 10px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
</body>
</html>
