<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://apply.emmameowss.gay");

// Start session for potential admin check
session_start();

// Check maintenance status from database with fallback
include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

// Check if user is an admin (for IP ban bypass)
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Check if IP is banned (skip check for admins)
$ip_banned = false;
if (!$is_admin && !$conn->connect_error) {
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Handle proxy/forwarded IPs
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $user_ip = trim($forwarded_ips[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $user_ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    // Check if banned_ips table exists and if IP is banned
    $table_check = $conn->query("SHOW TABLES LIKE 'banned_ips'");
    if ($table_check && $table_check->num_rows > 0) {
        $ban_check_sql = "SELECT id FROM banned_ips WHERE ip_address = ? AND is_active = 1 LIMIT 1";
        $ban_stmt = $conn->prepare($ban_check_sql);
        $ban_stmt->bind_param("s", $user_ip);
        $ban_stmt->execute();
        $ban_result = $ban_stmt->get_result();
        $ip_banned = ($ban_result->num_rows > 0);
        $ban_stmt->close();
    }
}

// If IP is banned, return banned status
if ($ip_banned) {
    http_response_code(403);
    echo json_encode(["error" => "Access denied", "banned" => true]);
    exit;
}

$maintenance = false;
if (!$conn->connect_error) {
    // Check if site_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
        $result = $conn->query($maintenance_sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $maintenance = ($row['setting_value'] === '1');
        }
    }
    $conn->close();
}

echo json_encode(["maintenance" => $maintenance]);
