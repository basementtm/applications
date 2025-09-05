<?php
// Simplified version of action_logger for both admin and user contexts
// This file works with both admin and user authentication systems

function logUserAction($action_type, $action_description, $target_type = null, $target_id = null, $additional_data = null) {
    global $conn;
    
    // Skip logging if no connection
    if (!isset($conn) || !$conn) {
        return false;
    }
    
    // Check if action_logs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'action_logs'");
    if (!$table_check || $table_check->num_rows === 0) {
        return false; // Table doesn't exist, can't log
    }
    
    // Determine if user is admin or regular user
    $user_id = null;
    $username = null;
    
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // Admin user
        $user_id = $_SESSION['admin_id'] ?? null;
        $username = $_SESSION['admin_username'] ?? 'Unknown Admin';
    } elseif (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        // Regular user
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'Unknown User';
    } else {
        // Not logged in
        return false;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Handle proxy/forwarded IPs
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip_address = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    try {
        $sql = "INSERT INTO action_logs (user_id, username, action_type, action_description, target_type, target_id, ip_address, user_agent, additional_data) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Failed to prepare action log statement: " . $conn->error);
            return false;
        }
        
        $additional_data_json = $additional_data ? json_encode($additional_data) : null;
        
        $stmt->bind_param("issssisss", $user_id, $username, $action_type, $action_description, $target_type, $target_id, $ip_address, $user_agent, $additional_data_json);
        
        if (!$stmt->execute()) {
            error_log("Failed to log action: " . $stmt->error);
            return false;
        }
        
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Action logging error: " . $e->getMessage());
        return false;
    }
}

// Helper functions for common actions
function logUserLogin($success, $username, $failure_reason = null) {
    $action_type = $success ? 'USER_LOGIN_SUCCESS' : 'USER_LOGIN_FAILED';
    $description = $success ? "User logged in successfully" : "Failed login attempt: $failure_reason";
    $additional_data = $success ? null : ['failure_reason' => $failure_reason];
    
    return logUserAction($action_type, $description, 'user', null, $additional_data);
}

function logUserLogout() {
    return logUserAction('USER_LOGOUT', 'User logged out', 'user');
}

function logEmailChange($old_email, $new_email) {
    $description = "User changed email from '$old_email' to '$new_email'";
    $additional_data = [
        'old_email' => $old_email,
        'new_email' => $new_email
    ];
    
    return logUserAction('USER_EMAIL_CHANGED', $description, 'user', null, $additional_data);
}

function log2FAChange($enabled) {
    $action_type = $enabled ? 'USER_2FA_ENABLED' : 'USER_2FA_DISABLED';
    $description = $enabled ? "User enabled two-factor authentication" : "User disabled two-factor authentication";
    
    return logUserAction($action_type, $description, 'user');
}

function logThemeChange($old_theme, $new_theme) {
    $description = "User changed theme from '$old_theme' to '$new_theme'";
    $additional_data = [
        'old_theme' => $old_theme,
        'new_theme' => $new_theme
    ];
    
    return logUserAction('USER_THEME_CHANGED', $description, 'user', null, $additional_data);
}
?>
