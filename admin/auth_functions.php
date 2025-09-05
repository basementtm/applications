<?php
// Shared authentication functions

function checkUserStatus() {
    // Check if user is logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false; // Not logged in
    }
    
    // Check session timeout based on remember me setting
    if (isset($_SESSION['login_time'])) {
        $login_time = $_SESSION['login_time'];
        $remember_me = $_SESSION['remember_me'] ?? false;
        $current_time = time();
        
        // Set timeout based on remember me option
        $timeout = $remember_me ? (30 * 24 * 60 * 60) : (24 * 60 * 60); // 30 days or 24 hours
        
        if (($current_time - $login_time) > $timeout) {
            // Session has expired
            session_destroy();
            header("Location: login.php?error=session_expired");
            exit();
        }
    }
    
    // Check if user account is still active
    if (isset($_SESSION['admin_id'])) {
        // Include database config
        include('/var/www/config/db_config.php');
        $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
        
        if ($conn->connect_error) {
            // Database connection failed - don't logout, just continue
            return true;
        }
        
        $user_id = $_SESSION['admin_id'];
        $check_sql = "SELECT active FROM users WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 1) {
            $user_data = $check_result->fetch_assoc();
            if ($user_data['active'] != 1) {
                // User account has been disabled - log them out
                $check_stmt->close();
                $conn->close();
                session_destroy();
                header("Location: login.php?error=account_disabled");
                exit();
            }
        } else {
            // User no longer exists - log them out
            $check_stmt->close();
            $conn->close();
            session_destroy();
            header("Location: login.php?error=account_not_found");
            exit();
        }
        $check_stmt->close();
        $conn->close();
    }
    
    return true; // User is active and logged in
}

function requireLogin() {
    if (!checkUserStatus()) {
        header("Location: login.php");
        exit();
    }
}
?>
