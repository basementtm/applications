<?php
// Database column fix for readonly_admin role
session_start();

// Check if user is logged in and is Emma (only Emma should run this)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Access denied. Please log in first.");
}

if (!isset($_SESSION['admin_username']) || $_SESSION['admin_username'] !== 'emma') {
    die("Access denied. Only Emma can run database fixes.");
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Role Column Fix</h2>";
echo "<p>Fixing the 'role' column in admin_users table to support 'readonly_admin'...</p>";

try {
    // First, let's check the current column definition
    $check_sql = "DESCRIBE admin_users role";
    $check_result = $conn->query($check_sql);
    if ($check_result) {
        $current_def = $check_result->fetch_assoc();
        echo "<p>Current role column definition: " . htmlspecialchars($current_def['Type']) . "</p>";
    }

    // Modify the role column to be larger
    $alter_sql = "ALTER TABLE admin_users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin'";
    
    if ($conn->query($alter_sql)) {
        echo "<p style='color: green;'>✅ Successfully updated role column to VARCHAR(50)</p>";
        
        // Verify the change
        $verify_result = $conn->query($check_sql);
        if ($verify_result) {
            $new_def = $verify_result->fetch_assoc();
            echo "<p>New role column definition: " . htmlspecialchars($new_def['Type']) . "</p>";
        }
        
        echo "<p><strong>The database has been fixed! You can now create readonly_admin users.</strong></p>";
        echo "<p><a href='owner.php'>← Return to Owner Panel</a></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Error updating column: " . htmlspecialchars($conn->error) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>
