<?php
// Maintenance mode check
if (file_exists('/var/www/config/maintenance.flag')) {
    http_response_code(503);
    header("Retry-After: 3600");
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='UTF-8'>
      <title>offline</title>
      <style>
        body { font-family: Arial; text-align: center; background-color: #ffc0cb; color: #333; padding: 50px; }
        h1 { color: #ff1493; }
      </style>
    </head>
    <body>
      <h1>🚧 Applications Closed</h1>
      <p>Applications are currently closed, check back later!</p>
    </body>
    </html>";
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Collect data safely
$name              = $_POST['name'] ?? '';
$email             = $_POST['email'] ?? '';
$gfphone           = $_POST['gfphone'] ?? '';
$reason            = $_POST['reason'] ?? '';
$cage              = $_POST['cage'] ?? 0;
$isCat             = $_POST['isCat'] ?? '';
$owner             = $_POST['owner'] ?? ''; // optional owner field
$preferredLocation = $_POST['preferredLocation'] ?? ''; // new field
$agreeTerms = isset($_POST['agreeTerms']) ? 1 : 0; // 1 if checked, 0 if not
$status = $_POST['status'] ?? '';

// Insert into database
$sql = "INSERT INTO applicants (name, email, gfphone, reason, cage, isCat, owner, preferredLocation, agreeTerms, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssisssis", $name, $email, $gfphone, $reason, $cage, $isCat, $owner, $preferredLocation, $agreeTerms, $status);

$success = $stmt->execute();
$errorMsg = $stmt->error;

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Application Status</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #ffc0cb;
      color: #333;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      text-align: center;
    }
    .container {
      background-color: #fff0f5;
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      max-width: 400px;
      width: 100%;
    }
    h1 {
      color: #ff1493;
    }
    p {
      margin: 15px 0;
    }
    a.button {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 25px;
      background-color: #ff69b4;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      transition: background-color 0.3s;
    }
    a.button:hover {
      background-color: #ff1493;
    }
  </style>
</head>
<body>
  <div class="container">
    <?php if ($success): ?>
      <h1>✅ Application Sent</h1>
      <p>Thanks <?= htmlspecialchars($name) ?> for "applying"! Check your email in a few hours, I guess.</p>
    <?php else: ?>
      <h1>❌ error</h1>
      <p>it's either you broke something or i did</p>
      <p>Error: <?= htmlspecialchars($errorMsg) ?></p>
    <?php endif; ?>
    <a class="button" href="https://apply.emmameowss.gay">Return</a>
  </div>
</body>
</html>
