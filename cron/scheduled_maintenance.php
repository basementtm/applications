<?php
/**
 * Scheduled Maintenance Cron Job
 * 
 * This script should be run every minute via cron to check for scheduled maintenance
 * Example cron entry: * * * * * /usr/bin/php /var/www/applications/cron/scheduled_maintenance.php
 */

// Set timezone to CEST
date_default_timezone_set('Europe/Berlin');

// Include database config
include('/var/www/config/db_config.php');

// Check if database connection is available
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    error_log("Scheduled Maintenance Cron: Database connection failed - " . $conn->connect_error);
    exit(1);
}

// Include scheduled maintenance helper
include('../includes/scheduled_maintenance_helper.php');

// Include action logger
include('../admin/action_logger.php');

try {
    // Process scheduled maintenance
    processScheduledMaintenance($conn);
    
    // Log successful execution (optional - uncomment if you want to log every execution)
    // error_log("Scheduled Maintenance Cron: Successfully processed at " . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    error_log("Scheduled Maintenance Cron: Error processing - " . $e->getMessage());
    exit(1);
}

// Close database connection
$conn->close();
exit(0);
?>
