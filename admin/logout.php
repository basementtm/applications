<?php
session_start();

// Include database configuration
include('/var/www/config/db_config.php');

// Include action logging functions
require_once 'action_logger.php';

// Establish database connection for logging
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Log the logout action before destroying session
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    try {
        logAction('ADMIN_LOGOUT', "Admin user logged out", 'admin_user', $_SESSION['admin_id'] ?? null);
    } catch (Exception $e) {
        // Continue with logout even if logging fails
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

session_destroy();
header("Location: login.php");
exit();
?>
