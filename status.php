<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://apply.emmameowss.gay");

// Check maintenance status from database
include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

$maintenance = false;
if (!$conn->connect_error) {
    $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
    $result = $conn->query($maintenance_sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maintenance = ($row['setting_value'] === '1');
    }
    $conn->close();
}

echo json_encode(["maintenance" => $maintenance]);
