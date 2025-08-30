<?php
// Add the started_early column to the scheduled_maintenance table

// Include database configuration
require_once 'config.php';

// Create database connection
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if scheduled_maintenance table exists
$table_check = $conn->query("SHOW TABLES LIKE 'scheduled_maintenance'");
if ($table_check && $table_check->num_rows === 0) {
    die("The scheduled_maintenance table does not exist. Please create it first.");
}

// Check if the column already exists
$column_check = $conn->query("SHOW COLUMNS FROM scheduled_maintenance LIKE 'started_early'");
if ($column_check && $column_check->num_rows > 0) {
    echo "The 'started_early' column already exists in the scheduled_maintenance table.";
} else {
    // Add the started_early column
    $sql = "ALTER TABLE scheduled_maintenance ADD COLUMN started_early TINYINT(1) NOT NULL DEFAULT 0";
    
    if ($conn->query($sql) === TRUE) {
        echo "The 'started_early' column was added successfully to the scheduled_maintenance table.";
    } else {
        echo "Error adding the 'started_early' column: " . $conn->error;
    }
}

$conn->close();
?>
