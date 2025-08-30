<?php
/**
 * API endpoint for retrieving active privacy notifications
 * Returns JSON with active notifications that should be shown to users
 */

// Enable error handling - log errors but don't display them
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set up error handling to capture any issues
try {
    // Include necessary files
    require_once '/var/www/config/db_config.php';

    // Set content type to JSON
    header('Content-Type: application/json');

// Check if the privacy_notifications table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'privacy_notifications'")->num_rows > 0;

if (!$table_exists) {
    // If table doesn't exist, return empty array
    echo json_encode([
        'success' => true,
        'notifications' => []
    ]);
    exit;
}

// Check if the dismissals table exists
$dismissals_table_exists = $conn->query("SHOW TABLES LIKE 'privacy_notification_dismissals'")->num_rows > 0;

// If dismissals table doesn't exist, we'll still show notifications but won't track dismissals

// Get active notifications
$query = "SELECT id, title, message, created_at 
          FROM privacy_notifications 
          WHERE active = 1 
          ORDER BY created_at DESC";

$result = $conn->query($query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve notifications: ' . $conn->error
    ]);
    exit;
}

// Build notifications array
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'created_at' => $row['created_at']
    ];
}

// Return JSON response
echo json_encode([
    'success' => true,
    'notifications' => $notifications
]);

} catch (Exception $e) {
    // Handle any errors that occurred
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
}
