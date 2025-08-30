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
    <h1>🏠 Data Retention Policy</h1>
    
    <div class="highlight">
      <strong>Last Updated:</strong> August 30, 2025
    </div>

    <h2>📋 What Information We Collect</h2>
    <p>When you submit an application to basement™, we collect:</p>
    <ul>
      <li>Personal information (name, email, phone number)</li>
      <li>Address information</li>
      <li>Discord username (if provided)</li>
      <li>Cage night preferences</li>
      <li>Cat status (if applicable) 🐱</li>
      <li>Additional information you provide</li>
      <li>Application timestamp and status updates</li>
    </ul>

    <h2>⏰ How Long We Keep Your Data</h2>
    <p>We retain your application data as follows:</p>
    <ul>
      <li><strong>Active Applications:</strong> Indefinitely while under review</li>
      <li><strong>Accepted Applications:</strong> For the duration of your membership plus 2 years</li>
      <li><strong>Denied Applications:</strong> 1 year from denial date</li>
      <li><strong>Withdrawn Applications:</strong> 6 months from withdrawal</li>
      <li><strong>Inactive Applications:</strong> 2 years from last activity</li>
    </ul>

    <h2>🗑️ Data Deletion</h2>
    <p>After the retention period expires, we automatically delete:</p>
    <ul>
      <li>All personal information from our databases</li>
      <li>Associated files and documents</li>
      <li>Communication logs related to your application</li>
    </ul>

    <div class="highlight">
      <strong>Right to Deletion:</strong> You may request immediate deletion of your data at any time by contacting us, regardless of the retention schedule.
    </div>

    <h2>🔒 Data Security</h2>
    <p>Your information is protected through:</p>
    <ul>
      <li>Encrypted database storage</li>
      <li>Secure admin access controls</li>
      <li>Regular security audits</li>
      <li>Limited access on a need-to-know basis</li>
    </ul>

    <h2>📬 Your Rights</h2>
    <p>You have the right to:</p>
    <ul>
      <li>Access your stored data</li>
      <li>Request corrections to your information</li>
      <li>Request deletion of your data</li>
      <li>Withdraw your application at any time</li>
      <li>Receive a copy of your data</li>
    </ul>

    <div class="contact-info">
      <h2>📞 Contact Us</h2>
      <p>For questions about this policy or to exercise your data rights:</p>
      <p><strong>Email:</strong> privacy@basement.example</p>
      <p><strong>Response Time:</strong> Within 30 days</p>
    </div>

    <div class="back-link">
      <a href="index.php">← Back to Application Form</a>
    </div>
  </div>
  
  <?php
  // Close database connection at the end
  if (isset($conn)) {
      $conn->close();
  }
  ?>
</body>
</html>
