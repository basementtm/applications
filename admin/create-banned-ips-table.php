<?php
session_start();

// Check if user is logged in and is Emma (owner)
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_username'] !== 'emma') {
    die("Access denied. Only Emma can create database tables.");
}

// Include database config
include('/var/www/config/db_config.php');

$sql = "CREATE TABLE IF NOT EXISTS banned_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason TEXT,
    banned_by VARCHAR(50),
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    INDEX idx_ip_address (ip_address),
    INDEX idx_banned_at (banned_at),
    INDEX idx_is_active (is_active)
)";

if ($conn->query($sql) === TRUE) {
    echo "<h2>✅ Banned IPs table created successfully!</h2>";
    echo "<p>The banned_ips table has been created with the following structure:</p>";
    echo "<ul>";
    echo "<li><strong>ip_address:</strong> The IP address to ban (supports IPv4 and IPv6)</li>";
    echo "<li><strong>reason:</strong> Reason for the ban</li>";
    echo "<li><strong>banned_by:</strong> Admin who created the ban</li>";
    echo "<li><strong>banned_at:</strong> When the ban was created</li>";
    echo "<li><strong>is_active:</strong> Whether the ban is currently active</li>";
    echo "<li><strong>notes:</strong> Additional notes about the ban</li>";
    echo "</ul>";
    echo "<p><a href='ip-ban-management.php'>Manage IP Bans</a> | <a href='owner.php'>Back to Owner Panel</a></p>";
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
    <title>Create Banned IPs Table</title>
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
