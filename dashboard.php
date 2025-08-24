<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Dashboard</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #ffc0cb;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
    }
    .container {
      background: #fff0f5;
      padding: 30px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      width: 400px;
    }
    h1 {
      color: #ff1493;
      margin-bottom: 20px;
    }
    a {
      display: block;
      margin: 10px 0;
      color: #ff1493;
      text-decoration: underline;
      font-size: 1.1rem;
    }
    a:hover {
      color: #c71585;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <a href="form.php">📝 Submit New Application</a>
    <a href="my_applications.php">📂 View My Applications</a>
    <a href="retention.html">📜 Data Retention Policy</a>
    <a href="logout.php">🚪 Logout</a>
  </div>
</body>
</html>
