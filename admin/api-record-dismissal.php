<?php
/**
 * API endpoint for recording privacy notification dismissals
 * Records when a user dismisses a privacy notification
 */

// Include necessary files
require_once '../config/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if the notification_id is provided
if (!isset($data['notification_id']) || !is_numeric($data['notification_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid notification ID'
    ]);
    exit;
}

$notification_id = (int)$data['notification_id'];

// Generate a visitor identifier (hash of IP + user agent)
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
} elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
    $ip_address = $_SERVER['HTTP_X_REAL_IP'];
}
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$visitor_identifier = hash('sha256', $ip_address . $user_agent);

// Check if the privacy_notification_dismissals table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'privacy_notification_dismissals'")->num_rows > 0;

if (!$table_exists) {
    // If table doesn't exist, return success but indicate the table doesn't exist
    echo json_encode([
        'success' => true,
        'table_exists' => false,
        'message' => 'Dismissal recorded in localStorage only (server table does not exist)'
    ]);
    exit;
}

// Check if the notification exists
$notification_exists = $conn->query("SELECT id FROM privacy_notifications WHERE id = $notification_id")->num_rows > 0;
if (!$notification_exists) {
    echo json_encode([
        'success' => false,
        'error' => 'Notification does not exist'
    ]);
    exit;
}

// Insert the dismissal record, replacing if it already exists
$sql = "INSERT INTO privacy_notification_dismissals (notification_id, visitor_identifier) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE dismissed_at = CURRENT_TIMESTAMP";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $notification_id, $visitor_identifier);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'table_exists' => true,
        'message' => 'Dismissal recorded successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error recording dismissal: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
