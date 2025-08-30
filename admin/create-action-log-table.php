<?php
// Create action log table
require_once '../config.php';

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
    echo "Action logs table created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
