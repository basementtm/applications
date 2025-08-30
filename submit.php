<?php
// Database maintenance mode check
include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

$maintenance_active = false;
if (!$conn->connect_error) {
    $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
    $result = $conn->query($maintenance_sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maintenance_active = ($row['setting_value'] === '1');
    }
}

if ($maintenance_active) {
    http_response_code(503);
    header("Retry-After: 3600");
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
      <meta charset='UTF-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>Maintenance - Applications Temporarily Closed</title>
      <style>
        body { 
          font-family: Arial, sans-serif; 
          text-align: center; 
          background-color: #ffc0cb; 
          color: #333; 
          padding: 50px; 
          margin: 0; 
          min-height: 100vh; 
          display: flex; 
          flex-direction: column; 
          justify-content: center; 
          align-items: center; 
        }
        .container { 
          background-color: #fff0f5; 
          padding: 40px; 
          border-radius: 15px; 
          box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
          max-width: 600px; 
        }
        h1 { color: #ff1493; margin-bottom: 20px; }
        p { margin: 15px 0; line-height: 1.6; }
        .maintenance-icon { font-size: 4rem; margin-bottom: 20px; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='maintenance-icon'>🚧</div>
        <h1>Maintenance</h1>
        <p><strong>Applications are temporarily closed while we perform maintenance.</strong></p>
        <p><small>Please try again later. Thank you for your patience!.</small></p>
      </div>
    </body>
    </html>";
    exit();
}

// Continue with existing database connection or create new one if maintenance check failed
if ($conn->connect_error) { 
    include('/var/www/config/db_config.php');
    $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
}

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
$status = 'unreviewed'; // Default status for new applications

// Generate application ID first
$timestamp = date('Ymd');
$randomSuffix = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
$applicationId = 'APP-' . $timestamp . '-' . $randomSuffix;

// Insert into database
$sql = "INSERT INTO applicants (application_id, name, email, gfphone, reason, cage, isCat, owner, preferredLocation, agreeTerms, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssisssiss", $applicationId, $name, $email, $gfphone, $reason, $cage, $isCat, $owner, $preferredLocation, $agreeTerms, $status);

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
    .application-id {
      background-color: #ff69b4;
      color: white;
      padding: 15px;
      border-radius: 10px;
      margin: 20px 0;
      font-weight: bold;
      font-size: 1.1rem;
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
      <div class="application-id">
        📋 Application ID: <?= htmlspecialchars($applicationId) ?>
      </div>
      <p>Thanks <?= htmlspecialchars($name) ?> for "applying"! Check your email in a few hours, I guess.</p>
      <p><small>Keep your application ID for reference.</small></p>
    <?php else: ?>
      <h1>❌ error</h1>
      <p>it's either you broke something or i did</p>
      <p>Error: <?= htmlspecialchars($errorMsg) ?></p>
    <?php endif; ?>
    <a class="button" href="https://girlskissing.dev">Return</a>
  </div>
</body>
</html>
