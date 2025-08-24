<?php
session_start();
include('/var/www/config/db_config.php');

$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];

// Hash password securely
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $hashed);

if ($stmt->execute()) {
    $_SESSION['username'] = $username;
    header("Location: dashboard.php");
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
