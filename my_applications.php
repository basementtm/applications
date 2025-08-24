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

// Get applications
$stmt = $conn->prepare("SELECT name, email, gfphone, reason, cage, isCat, owner, created_at FROM applicants WHERE user_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Applications</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #ffc0cb;
      text-align: center;
      margin: 0;
      padding: 20px;
    }
    .container {
      background: #fff0f5;
      padding: 30px;
      border-radius: 15px;
      display: inline-block;
      max-width: 700px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    h1 { color: #ff1493; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #ffb6c1;
      color: #333;
    }
    tr:hover { background-color: #ffe4e1; }
    a { color: #ff1493; text-decoration: underline; }
  </style>
</head>
<body>
  <div class="container">
    <h1>My Applications</h1>
    <?php if ($result->num_rows > 0): ?>
      <table>
        <tr>
          <th>name</th>
          <th>email</th>
          <th>girlfriend's number</th>
          <th>why did you apply</th>
          <th>how many days in cage this week</th>
          <th>cat yes or no</th>
          <th>owner of cat</th>        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['gfphone'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['reason']) ?></td>
            <td><?= (int)$row['cage'] ?></td>
            <td><?= $row['isCat'] ? "Yes" : "No" ?></td>
            <td><?= htmlspecialchars($row['owner'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
          </tr>
        <?php endwhile; ?>
      </table>
    <?php else: ?>
      <p>You haven’t submitted any applications yet.</p>
    <?php endif; ?>
    <p style="margin-top:15px;">
      <a href="form.php">Submit New Application</a> | 
      <a href="dashboard.php">Dashboard</a> | 
      <a href="logout.php">Logout</a>
    </p>
  </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
