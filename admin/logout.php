<?php
session_start();

// Include action logging functions
require_once 'action_logger.php';

// Log the logout action before destroying session
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    logAction('ADMIN_LOGOUT', "Admin user logged out", 'admin_user', $_SESSION['admin_id'] ?? null);
}

session_destroy();
header("Location: login.php");
exit();
?>
