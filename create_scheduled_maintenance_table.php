<?php
// Simple script to create scheduled maintenance table
// Run this directly via web browser

// Include database config
$config_file = '/var/www/config/db_config.php';
if (file_exists($config_file)) {
    include($config_file);
} else {
    die("Database config file not found at: $config_file");
}

// Create connection
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
    echo "<h2>✅ Success!</h2>";
    echo "<p>Scheduled maintenance table created successfully!</p>";
    echo "<p><a href='admin/maintenance-control.php'>Go to Maintenance Control</a></p>";
} else {
    echo "<h2>❌ Error:</h2>";
    echo "<p>" . $conn->error . "</p>";
}

$conn->close();
?>
