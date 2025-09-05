<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include('/var/www/config/db_config.php');

// Include appropriate auth file based on context
if (strpos($_SERVER['SCRIPT_FILENAME'], '/admin/') !== false) {
    require_once 'auth_functions.php';
} else {
    require_once 'user_auth.php';
}
require_once 'action_logger_lite.php';

// Check if user is logged in
if (!isLoggedIn()) {
    exit(json_encode(['success' => false, 'error' => 'Not logged in']));
}

// Check for POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_theme = isset($_POST['old_theme']) ? $_POST['old_theme'] : 'light';
    $new_theme = isset($_POST['new_theme']) ? $_POST['new_theme'] : 'dark';
    
    // Log the theme change
    logThemeChange($old_theme, $new_theme);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
