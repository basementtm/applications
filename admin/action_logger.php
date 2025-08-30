<?php
// Action logging functions
// Note: This file requires database connection to be established before use

function logAction($action_type, $action_description, $target_type = null, $target_id = null, $additional_data = null) {
    global $conn;
    
    // Only log actions if user is an admin (has admin session)
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return; // Don't log non-admin actions
    }
    
    $user_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
    $username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'System';
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
        $additional_data_json = $additional_data ? json_encode($additional_data) : null;
        
        $stmt->bind_param("issssisss", $user_id, $username, $action_type, $action_description, $target_type, $target_id, $ip_address, $user_agent, $additional_data_json);
        
        if (!$stmt->execute()) {
            error_log("Failed to log action: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Action logging error: " . $e->getMessage());
    }
}

// Specific logging functions for common admin actions
function logAdminLogin($username, $success, $failure_reason = null) {
    // Only log admin login attempts
    $action_type = $success ? 'ADMIN_LOGIN_SUCCESS' : 'ADMIN_LOGIN_FAILED';
    $description = $success ? "Admin user logged in successfully" : "Failed admin login attempt: $failure_reason";
    $additional_data = $success ? null : ['failure_reason' => $failure_reason];
    
    // Temporarily set session for logging even failed attempts
    $temp_admin_logged_in = isset($_SESSION['admin_logged_in']) ? $_SESSION['admin_logged_in'] : false;
    $_SESSION['admin_logged_in'] = true;
    
    logAction($action_type, $description, 'admin_user', null, $additional_data);
    
    // Restore original session state
    $_SESSION['admin_logged_in'] = $temp_admin_logged_in;
}

function logApplicationAction($action, $app_id, $app_name) {
    $action_type = 'ADMIN_APPLICATION_' . strtoupper($action);
    $description = "Admin $action application '$app_name'";
    
    logAction($action_type, $description, 'application', $app_id);
}

function logUserAction($action, $target_user_id, $target_username) {
    $action_type = 'ADMIN_USER_' . strtoupper($action);
    $description = "Admin $action user '$target_username'";
    
    logAction($action_type, $description, 'admin_user', $target_user_id);
}

function logMaintenanceAction($enabled) {
    $action_type = 'ADMIN_MAINTENANCE_' . ($enabled ? 'ENABLED' : 'DISABLED');
    $description = "Admin " . ($enabled ? 'enabled' : 'disabled') . " maintenance mode";
    
    logAction($action_type, $description, 'system', null);
}

function logSettingsChange($setting_name, $old_value, $new_value) {
    $action_type = 'ADMIN_SETTINGS_CHANGED';
    $description = "Admin changed setting '$setting_name' from '$old_value' to '$new_value'";
    $additional_data = [
        'setting' => $setting_name,
        'old_value' => $old_value,
        'new_value' => $new_value
    ];
    
    logAction($action_type, $description, 'settings', null, $additional_data);
}

function logFileAction($action, $filename, $file_type = 'file') {
    $action_type = 'ADMIN_FILE_' . strtoupper($action);
    $description = "Admin $action " . ucfirst($file_type) . " '$filename'";
    
    logAction($action_type, $description, $file_type, null, ['filename' => $filename]);
}
?>
