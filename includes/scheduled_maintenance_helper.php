<?php
// Scheduled maintenance helper functions

function getScheduledMaintenance($conn) {
    $maintenance = [];
    
    // Check if scheduled_maintenance table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'scheduled_maintenance'");
    if ($table_check && $table_check->num_rows > 0) {
        $sql = "SELECT * FROM scheduled_maintenance 
                WHERE is_active = 1 AND maintenance_completed = 0 
                ORDER BY start_time ASC LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $maintenance = $result->fetch_assoc();
        }
    }
    
    return $maintenance;
}

function processScheduledMaintenance($conn) {
    // Set timezone to CEST
    date_default_timezone_set('Europe/Berlin');
    $current_time = date('Y-m-d H:i:s');
    
    // Get active scheduled maintenance
    $maintenance = getScheduledMaintenance($conn);
    
    if (empty($maintenance)) {
        return;
    }
    
    $start_time = $maintenance['start_time'];
    $end_time = $maintenance['end_time'];
    $warning_time = date('Y-m-d H:i:s', strtotime($start_time . ' -1 hour'));
    
    // Include action logger if not already included
    if (!function_exists('logAction')) {
        // Try different possible paths to the action_logger.php file
        $action_logger_paths = [
            __DIR__ . '/../admin/action_logger.php',
            '/var/www/html/admin/action_logger.php',
            dirname(dirname(__FILE__)) . '/admin/action_logger.php'
        ];
        
        $logger_loaded = false;
        foreach ($action_logger_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $logger_loaded = true;
                break;
            }
        }
        
        if (!$logger_loaded) {
            // If we can't load the logger, create a simple placeholder function
            if (!function_exists('logAction')) {
                function logAction($action_type, $description, $module = null, $record_id = null, $additional_data = null) {
                    // Placeholder function that does nothing
                    return true;
                }
            }
        }
    }
    
    // Check if it's time for 1-hour warning banner
    if ($current_time >= $warning_time && $current_time < $start_time && !$maintenance['warning_banner_shown']) {
        updateMaintenanceBanner($conn, 'warning', $maintenance);
        markWarningBannerShown($conn, $maintenance['id']);
        logAction('SCHEDULED_MAINTENANCE_WARNING', 'Displayed 1-hour warning banner for scheduled maintenance', 'scheduled_maintenance', $maintenance['id']);
    }
    
    // Check if it's time to start maintenance
    if ($current_time >= $start_time && !$maintenance['maintenance_started']) {
        enableMaintenanceMode($conn);
        hideMaintenanceBanner($conn);
        markMaintenanceStarted($conn, $maintenance['id']);
        logAction('SCHEDULED_MAINTENANCE_STARTED', 'Automatically started scheduled maintenance', 'scheduled_maintenance', $maintenance['id']);
    }
    
    // Check if it's time to end maintenance
    if ($current_time >= $end_time && $maintenance['maintenance_started'] && !$maintenance['maintenance_completed']) {
        disableMaintenanceMode($conn);
        markMaintenanceCompleted($conn, $maintenance['id']);
        logAction('SCHEDULED_MAINTENANCE_COMPLETED', 'Automatically ended scheduled maintenance', 'scheduled_maintenance', $maintenance['id']);
    }
    
    // Show initial warning banner (yellow) when maintenance is scheduled but not in warning period yet
    if ($current_time < $warning_time && !$maintenance['banner_shown']) {
        updateMaintenanceBanner($conn, 'info', $maintenance);
        markBannerShown($conn, $maintenance['id']);
        logAction('SCHEDULED_MAINTENANCE_BANNER', 'Displayed initial banner for scheduled maintenance', 'scheduled_maintenance', $maintenance['id']);
    }
}

function updateMaintenanceBanner($conn, $type, $maintenance) {
    $start_time_formatted = date('F j, Y \a\t g:i A T', strtotime($maintenance['start_time']));
    $end_time_formatted = date('g:i A T', strtotime($maintenance['end_time']));
    
    if ($type === 'warning') {
        $text = "Notice: Maintenance will begin in less than 1 hour at {$start_time_formatted} and end at {$end_time_formatted}";
        if (!empty($maintenance['reason'])) {
            $text .= ". Reason: " . $maintenance['reason'];
        }
        $text .= ". For more information please visit status.girlskissing.dev";
        $banner_type = 'error'; // Red banner
    } else {
        $text = "Notice: Maintenance is scheduled for {$start_time_formatted} to {$end_time_formatted}";
        if (!empty($maintenance['reason'])) {
            $text .= ". Reason: " . $maintenance['reason'];
        }
        $text .= ". For more information please visit status.girlskissing.dev";
        $banner_type = 'warning'; // Yellow banner
    }
    
    // Update banner settings
    $settings = [
        'banner_enabled' => '1',
        'banner_text' => $text,
        'banner_type' => $banner_type
    ];
    
    foreach ($settings as $setting_name => $setting_value) {
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, updated_by) VALUES (?, ?, 'scheduled_maintenance') 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'scheduled_maintenance'");
        $stmt->bind_param("sss", $setting_name, $setting_value, $setting_value);
        $stmt->execute();
        $stmt->close();
    }
}

function hideMaintenanceBanner($conn) {
    $stmt = $conn->prepare("UPDATE site_settings SET setting_value = '0', updated_by = 'scheduled_maintenance' 
                           WHERE setting_name = 'banner_enabled'");
    $stmt->execute();
    $stmt->close();
}

function enableMaintenanceMode($conn) {
    $stmt = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, updated_by) VALUES ('maintenance_mode', '1', 'scheduled_maintenance') 
                           ON DUPLICATE KEY UPDATE setting_value = '1', updated_by = 'scheduled_maintenance'");
    $stmt->execute();
    $stmt->close();
}

function disableMaintenanceMode($conn) {
    $stmt = $conn->prepare("UPDATE site_settings SET setting_value = '0', updated_by = 'scheduled_maintenance' 
                           WHERE setting_name = 'maintenance_mode'");
    $stmt->execute();
    $stmt->close();
}

function markBannerShown($conn, $maintenance_id) {
    $stmt = $conn->prepare("UPDATE scheduled_maintenance SET banner_shown = 1 WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $stmt->close();
}

function markWarningBannerShown($conn, $maintenance_id) {
    $stmt = $conn->prepare("UPDATE scheduled_maintenance SET warning_banner_shown = 1 WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $stmt->close();
}

function markMaintenanceStarted($conn, $maintenance_id) {
    $stmt = $conn->prepare("UPDATE scheduled_maintenance SET maintenance_started = 1 WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $stmt->close();
}

function markMaintenanceCompleted($conn, $maintenance_id) {
    $stmt = $conn->prepare("UPDATE scheduled_maintenance SET maintenance_completed = 1, is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $stmt->close();
}

function addScheduledMaintenance($conn, $start_time, $end_time, $reason, $created_by) {
    $stmt = $conn->prepare("INSERT INTO scheduled_maintenance (start_time, end_time, reason, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $start_time, $end_time, $reason, $created_by);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function cancelScheduledMaintenance($conn, $maintenance_id) {
    // Mark as inactive and hide banner if it's showing
    $stmt = $conn->prepare("UPDATE scheduled_maintenance SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Hide banner if no other active maintenance is scheduled
    $active_maintenance = getScheduledMaintenance($conn);
    if (empty($active_maintenance)) {
        hideMaintenanceBanner($conn);
    }
    
    return $result;
}

function startMaintenanceEarly($conn, $maintenance_id) {
    // Get the maintenance record to verify it's active and not yet started
    $stmt = $conn->prepare("SELECT * FROM scheduled_maintenance WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // Maintenance record not found
    }
    
    $maintenance = $result->fetch_assoc();
    $stmt->close();
    
    // Only allow starting early if maintenance has not started yet and is active
    if ($maintenance['maintenance_started'] == 1 || $maintenance['is_active'] != 1) {
        return false;
    }
    
    // Check if the started_early column exists
    $column_check = $conn->query("SHOW COLUMNS FROM scheduled_maintenance LIKE 'started_early'");
    $has_started_early_column = ($column_check && $column_check->num_rows > 0);
    
    // Mark as started now
    if ($has_started_early_column) {
        $stmt = $conn->prepare("UPDATE scheduled_maintenance SET 
                               maintenance_started = 1, 
                               start_time = NOW(),
                               started_early = 1
                               WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE scheduled_maintenance SET 
                               maintenance_started = 1, 
                               start_time = NOW()
                               WHERE id = ?");
    }
    
    $stmt->bind_param("i", $maintenance_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        // Enable maintenance mode
        enableMaintenanceMode($conn);
        // Hide warning banners
        hideMaintenanceBanner($conn);
    }
    
    return $result;
}

function endMaintenanceEarly($conn, $maintenance_id) {
    // Get the maintenance record to verify it's active and started
    $stmt = $conn->prepare("SELECT * FROM scheduled_maintenance WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // Maintenance record not found
    }
    
    $maintenance = $result->fetch_assoc();
    $stmt->close();
    
    // Only allow ending early if maintenance has started and not completed
    if ($maintenance['maintenance_started'] != 1 || $maintenance['maintenance_completed'] == 1) {
        return false;
    }
    
    // Mark as completed, but keep it in history
    $stmt = $conn->prepare("UPDATE scheduled_maintenance SET 
                            maintenance_completed = 1, 
                            is_active = 0,
                            end_time = NOW(),
                            ended_early = 1
                            WHERE id = ?");
    $stmt->bind_param("i", $maintenance_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        // Disable maintenance mode
        disableMaintenanceMode($conn);
    }
    
    return $result;
}

function getAllScheduledMaintenance($conn, $limit = 10) {
    $maintenance_list = [];
    
    $table_check = $conn->query("SHOW TABLES LIKE 'scheduled_maintenance'");
    if ($table_check && $table_check->num_rows > 0) {
        $sql = "SELECT * FROM scheduled_maintenance 
                ORDER BY created_at DESC 
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $maintenance_list[] = $row;
        }
        $stmt->close();
    }
    
    return $maintenance_list;
}
?>
