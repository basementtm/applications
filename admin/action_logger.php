<?php
// Action logging functions
require_once '../config.php';

function logAction($action_type, $action_description, $target_type = null, $target_id = null, $additional_data = null) {
    global $conn;
    
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

// Specific logging functions for common actions
function logLogin($username, $success, $failure_reason = null) {
    $action_type = $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED';
    $description = $success ? "User logged in successfully" : "Failed login attempt: $failure_reason";
    $additional_data = $success ? null : ['failure_reason' => $failure_reason];
    
    logAction($action_type, $description, 'user', null, $additional_data);
}

function logApplicationAction($action, $app_id, $app_name) {
    $action_type = 'APPLICATION_' . strtoupper($action);
    $description = "Application '$app_name' was $action";
    
    logAction($action_type, $description, 'application', $app_id);
}

function logUserAction($action, $target_user_id, $target_username) {
    $action_type = 'USER_' . strtoupper($action);
    $description = "User '$target_username' was $action";
    
    logAction($action_type, $description, 'user', $target_user_id);
}

function logMaintenanceAction($enabled) {
    $action_type = 'MAINTENANCE_' . ($enabled ? 'ENABLED' : 'DISABLED');
    $description = "Maintenance mode " . ($enabled ? 'enabled' : 'disabled');
    
    logAction($action_type, $description, 'system', null);
}

function logSettingsChange($setting_name, $old_value, $new_value) {
    $action_type = 'SETTINGS_CHANGED';
    $description = "Setting '$setting_name' changed from '$old_value' to '$new_value'";
    $additional_data = [
        'setting' => $setting_name,
        'old_value' => $old_value,
        'new_value' => $new_value
    ];
    
    logAction($action_type, $description, 'settings', null, $additional_data);
}

function logFileAction($action, $filename, $file_type = 'file') {
    $action_type = 'FILE_' . strtoupper($action);
    $description = ucfirst($file_type) . " '$filename' was $action";
    
    logAction($action_type, $description, $file_type, null, ['filename' => $filename]);
}
?>
