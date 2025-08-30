<?php
// Database connection for banner functionality
include('/var/www/config/db_config.php');
include('includes/banner_helper.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Data Retention Policy - basement™</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #ffc0cb;
      color: #333;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
      flex-direction: column;
    }

    #policy-container {
      background-color: #fff0f5;
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 600px;
      width: 90%;
      box-sizing: border-box;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 1s forwards;
    }

    h1 {
      color: #ff1493;
      margin-bottom: 20px;
      font-size: 2em;
    }

    h2 {
      color: #ff69b4;
      margin-top: 25px;
      margin-bottom: 15px;
      font-size: 1.3em;
    }

    p, li {
      line-height: 1.6;
      margin-bottom: 10px;
    }

    ul {
      text-align: left;
      padding-left: 20px;
      margin: 15px 0;
    }

    .highlight {
      background-color: #ffe6f0;
      padding: 15px;
      border-radius: 8px;
      border-left: 4px solid #ff1493;
      margin: 20px 0;
    }

    .contact-info {
      background-color: #f0f8ff;
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
      border-left: 4px solid #ff69b4;
    }

    .back-link {
      margin-top: 25px;
    }

    .back-link a {
      color: #ff1493;
      text-decoration: none;
      font-weight: bold;
      padding: 10px 20px;
      border: 2px solid #ff1493;
      border-radius: 25px;
      transition: all 0.3s ease;
    }

    .back-link a:hover {
      background-color: #ff1493;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(255, 20, 147, 0.3);
    }

    <?= getBannerCSS() ?>

    @keyframes fadeUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      #policy-container {
        padding: 20px 25px;
      }
      
      h1 {
        font-size: 1.6em;
      }
      
      h2 {
        font-size: 1.2em;
      }
    }
  </style>
</head>
<body>
  <?php renderBanner($conn); ?>
  
  <div id="policy-container">
    <h1>Data Retention Policy</h1>
    <p>
      All personal data submitted through the basement™ application form is stored securely on our servers for a maximum of 24-48 months.  
      Once the application has been reviewed by our team, all submitted information is still stored on our systems.
    </p>
    <p>
      This ensures that your personal data is not only used for the purpose of evaluating your application and is kept longer than necessary.
    </p>
    <a class="button" href="https://girlskissing.dev/">Back to Application Form</a>
  </div>
  
  <?php
  // Close database connection at the end
  if (isset($conn)) {
      $conn->close();
  }
  ?>
</body>
</html>
