<?php
// Script to create login_attempts table for logging user login activity
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('/var/www/config/db_config.php');

try {
    $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database successfully.<br>";
    
    // Create login_attempts table
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        success BOOLEAN DEFAULT FALSE,
        method VARCHAR(50) DEFAULT 'password',
        failure_reason VARCHAR(255) DEFAULT NULL,
        session_id VARCHAR(128) DEFAULT NULL,
        INDEX idx_username (username),
        INDEX idx_ip_address (ip_address),
        INDEX idx_attempt_time (attempt_time),
        INDEX idx_success (success)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table 'login_attempts' created successfully or already exists.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
    
    // Check if table exists and show structure
    $result = $conn->query("DESCRIBE login_attempts");
    if ($result) {
        echo "<br>Table structure:<br>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    $conn->close();
    echo "<br>Setup completed successfully!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
