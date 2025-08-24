<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}

include('/var/www/config/db_config.php');

$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get logged in user ID
$userStmt = $conn->prepare("SELECT id FROM users WHERE username=?");
$userStmt->bind_param("s", $_SESSION['username']);
$userStmt->execute();
$userStmt->bind_result($user_id);
$userStmt->fetch();
$userStmt->close();

$name   = $_POST['name'];
$email  = $_POST['email'];
$gfphone= $_POST['gfphone'] ?: null;
$reason = $_POST['reason'];
$cage   = $_POST['cage'];
$isCat  = $_POST['isCat'];
$owner  = $_POST['owner'] ?: null;

$stmt = $conn->prepare("INSERT INTO applicants (user_id, name, email, gfphone, reason, cage, isCat, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssiss", $user_id, $name, $email, $gfphone, $reason, $cage, $isCat, $owner);

if ($stmt->execute()) {
    echo "<!DOCTYPE html>
    <html><head><meta charset='UTF-8'><title>Submitted</title>
    <style>
      body { font-family: Arial; background:#ffc0cb; text-align:center; padding:50px; }
      .box { background:#fff0f5; padding:30px; border-radius:15px; display:inline-block; }
      h1 { color:#ff1493; }
    </style>
    </head><body>
      <div class='box'>
        <h1>Application Submitted!</h1>
        <p>Thank you, your application has been received.</p>
        <a href='form.php'>Submit another</a> | <a href='dashboard.php'>Dashboard</a>
      </div>
    </body></html>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
