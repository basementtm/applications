<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://apply.emmameowss.gay");

$maintenance = file_exists('/var/www/config/maintenance.flag');

echo json_encode(["maintenance" => $maintenance]);
