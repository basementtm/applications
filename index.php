<?php
// Database maintenance mode check with fallback
include('/var/www/config/db_config.php');
include('includes/banner_helper.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

$maintenance_active = false;
if (!$conn->connect_error) {
    // Check if site_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'maintenance_mode' LIMIT 1";
        $result = $conn->query($maintenance_sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $maintenance_active = ($row['setting_value'] === '1');
        }
    }
}

if ($maintenance_active) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance Mode - basement application form</title>
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
                text-align: center;
            }
            .maintenance-notice {
                background: #fff0f5;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                max-width: 500px;
            }
        </style>
    </head>
    <body>
        <div class="maintenance-notice">
            <h1>🚧 Site Under Maintenance</h1>
            <p>We're currently performing maintenance. Please check back later.</p>
            <p><a href="check-status.php">Check Application Status</a></p>
        </div>
    </body>
    </html>
    <?php
    if (isset($conn)) {
        $conn->close();
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>basement application form</title>
  <style>
    :root {
      --bg-color: #ffc0cb;
      --container-bg: #fff0f5;
      --text-color: #333;
      --primary-pink: #ff1493;
      --secondary-pink: #ff69b4;
      --border-color: #ccc;
      --shadow-color: rgba(0,0,0,0.1);
      --input-bg: #fff0f5;
      --banner-bg: #fff0f5;
      --banner-text: #ff1493;
    }

    [data-theme="dark"] {
      --bg-color: #2d1b2e;
      --container-bg: #3d2b3e;
      --text-color: #e0d0e0;
      --primary-pink: #ff6bb3;
      --secondary-pink: #d147a3;
      --border-color: #666;
      --shadow-color: rgba(0,0,0,0.3);
      --input-bg: #4a3a4a;
      --banner-bg: #4a3a4a;
      --banner-text: #ff6bb3;
    }

    body {
      font-family: Arial, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      margin: 0;
      padding: 0;
      min-height: 100vh;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .main-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
      flex-direction: column;
    }

    .container {
      background-color: var(--container-bg);
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 4px 15px var(--shadow-color);
      max-width: 600px;
      width: 100%;
      position: relative;
    }

    h1 {
      text-align: center;
      color: var(--primary-pink);
      margin-bottom: 30px;
      font-size: 2em;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: var(--text-color);
    }

    input[type="text"], 
    input[type="email"], 
    input[type="tel"], 
    select, 
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background-color: var(--input-bg);
      color: var(--text-color);
      font-size: 16px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      box-sizing: border-box;
    }

    input:focus, 
    select:focus, 
    textarea:focus {
      outline: none;
      border-color: var(--primary-pink);
      box-shadow: 0 0 8px rgba(255, 20, 147, 0.3);
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
      margin: 0;
      transform: scale(1.2);
    }

    .checkbox-group label {
      margin: 0;
      font-weight: normal;
      cursor: pointer;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .cage-nights {
      background-color: var(--banner-bg);
      padding: 15px;
      border-radius: 8px;
      border: 1px solid var(--border-color);
      margin-bottom: 20px;
    }

    .cage-nights h3 {
      margin: 0 0 15px 0;
      color: var(--primary-pink);
      font-size: 1.1em;
    }

    .nights-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 10px;
    }

    .night-checkbox {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px;
      background-color: var(--input-bg);
      border-radius: 5px;
      border: 1px solid var(--border-color);
      transition: background-color 0.3s ease;
    }

    .night-checkbox:hover {
      background-color: var(--primary-pink);
      color: white;
    }

    .night-checkbox input[type="checkbox"] {
      width: auto;
      margin: 0;
    }

    .night-checkbox label {
      margin: 0;
      font-weight: normal;
      cursor: pointer;
      font-size: 0.9em;
    }

    .submit-btn {
      width: 100%;
      padding: 15px;
      background-color: var(--primary-pink);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .submit-btn:hover {
      background-color: var(--secondary-pink);
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(255, 20, 147, 0.4);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

    .theme-switcher {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      background-color: var(--container-bg);
      border: 2px solid var(--secondary-pink);
      border-radius: 50%;
      width: 50px;
      height: 50px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px var(--shadow-color);
    }

    .theme-switcher:hover {
      transform: scale(1.1);
      background-color: var(--secondary-pink);
      color: white;
    }

    .status-link {
      text-align: center;
      margin-top: 20px;
    }

    .status-link a {
      color: var(--primary-pink);
      text-decoration: none;
      font-weight: bold;
      transition: color 0.3s ease;
    }

    .status-link a:hover {
      color: var(--secondary-pink);
      text-decoration: underline;
    }

    /* All the missing CSS from index.html */
    #notice-banner {
      background-color: var(--banner-bg);
      color: var(--banner-text);
      padding: 15px 20px;
      margin: 0;
      font-weight: bold;
      text-align: center;
      box-shadow: 0 4px 10px var(--shadow-color);
      word-wrap: break-word;
      white-space: normal;
      width: 100%;
      box-sizing: border-box;
      border-bottom: 2px solid var(--secondary-pink);
      opacity: 0;
      transform: translateY(-20px);
      animation: fadeSlideDown 1.2s 0.3s forwards;
      transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 100;
    }

    #notice-banner:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(255,20,147,0.2);
    }

    #form-container, #maintenance {
      background-color: var(--container-bg);
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px var(--shadow-color);
      text-align: center;
      max-width: 400px;
      width: 90%;
      box-sizing: border-box;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 1s forwards;
      margin-top: 60px;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }

    h1 {
      color: var(--primary-pink);
      margin-bottom: 20px;
      opacity: 0;
      transform: translateY(-10px);
      animation: fadeSlideDown 0.8s forwards;
      transition: color 0.3s ease;
    }

    @keyframes fadeSlideDown {
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }

    input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 1rem;
      box-sizing: border-box;
      transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
      background-color: var(--input-bg);
      color: var(--text-color);
    }

    input[type="text"]:focus, input[type="email"]:focus, input[type="tel"]:focus,
    input[type="number"]:focus, textarea:focus {
      transform: scale(1.02);
      box-shadow: 0 0 5px rgba(255,20,147,0.5);
      outline: none;
    }

    .radio-group {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin: 15px 0;
      flex-wrap: wrap;
    }

    .radio-group label {
      background-color: var(--secondary-pink);
      color: white;
      padding: 10px 25px;
      border-radius: 12px;
      cursor: pointer;
      font-weight: bold;
      transition: background-color 0.3s, transform 0.2s;
      min-width: 80px;
      text-align: center;
    }

    .radio-group input[type="radio"] { display: none; }

    .radio-group input[type="radio"]:checked + label {
      background-color: var(--primary-pink);
      transform: scale(1.05);
    }

    .radio-group label:hover { transform: scale(1.05); }

    button {
      padding: 12px 25px;
      background-color: var(--secondary-pink);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s, transform 0.2s;
      margin-top: 10px;
      width: 100%;
      max-width: 200px;
    }

    button:hover {
      background-color: var(--primary-pink);
      animation: pulse 0.6s ease-in-out;
    }

    button:active { transform: scale(0.95); }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

    .form-group {
      position: relative;
      margin: 15px 0;
    }

    .custom-dropdown {
      position: relative;
      width: 100%;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background-color: var(--input-bg);
      cursor: pointer;
      padding: 10px;
      box-sizing: border-box;
      font-size: 1rem;
      transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s ease, border-color 0.3s ease;
      z-index: 1;
    }

    .custom-dropdown .selected {
      color: var(--text-color);
      transition: color 0.3s ease;
    }

    .custom-dropdown .options {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background-color: var(--input-bg);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      margin-top: 5px;
      display: none;
      max-height: 200px;
      overflow-y: auto;
      z-index: 10;
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    .custom-dropdown .options div {
      padding: 10px;
      cursor: pointer;
      transition: background-color 0.2s, transform 0.2s, color 0.3s ease;
      color: var(--text-color);
    }

    .custom-dropdown .options div:hover {
      background-color: var(--secondary-pink);
      color: #fff;
      transform: scale(1.02);
    }

    .custom-dropdown.active {
      box-shadow: 0 0 5px rgba(255,20,147,0.5);
      transform: scale(1.02);
    }

    .custom-dropdown.active .options {
      display: block;
      z-index: 10;
    }

    .checkbox-container {
      display: block;
      position: relative;
      padding-left: 30px;
      margin: 15px 0;
      cursor: pointer;
      font-size: 0.95rem;
      user-select: none;
      color: var(--text-color);
      transition: color 0.3s ease;
    }

    .checkbox-container input {
      position: absolute;
      opacity: 0;
      cursor: pointer;
      height: 0;
      width: 0;
    }

    .checkbox-container .checkmark {
      position: absolute;
      top: 0;
      left: 0;
      height: 20px;
      width: 20px;
      background-color: var(--input-bg);
      border: 2px solid var(--secondary-pink);
      border-radius: 5px;
      transition: 0.3s all;
    }

    .checkbox-container input:checked ~ .checkmark {
      background-color: var(--primary-pink);
      border-color: var(--primary-pink);
    }

    .checkbox-container .checkmark:after {
      content: "";
      position: absolute;
      display: none;
    }

    .checkbox-container input:checked ~ .checkmark:after {
      display: block;
      left: 6px;
      top: 2px;
      width: 6px;
      height: 12px;
      border: solid white;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    .theme-switcher {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 1000;
      background-color: var(--container-bg);
      border: 2px solid var(--secondary-pink);
      border-radius: 50%;
      width: 60px;
      height: 60px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px var(--shadow-color);
      opacity: 0;
      transform: scale(0.8);
      animation: fadeScale 0.3s 0.2s forwards;
    }

    .theme-switcher:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 15px var(--shadow-color);
      background-color: var(--secondary-pink);
      color: white;
    }

    @keyframes fadeScale {
      to { 
        opacity: 1; 
        transform: scale(1);
      }
    }

    footer { 
      font-size: 0.9rem; 
      color: var(--text-color); 
      padding-top: 20px; 
      text-align: center; 
      transition: color 0.3s ease; 
    }

    @media (max-width: 500px) {
      #form-container, #maintenance { 
        padding: 20px; 
        margin-top: 70px;
        width: 95%;
        max-width: none;
      }
      input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea { 
        font-size: 0.9rem; 
        padding: 12px;
      }
      .radio-group { 
        flex-direction: column;
        gap: 10px;
        align-items: center;
      }
      .radio-group label { 
        padding: 12px 20px; 
        font-size: 0.9rem; 
        min-width: 120px;
        width: 100%;
        max-width: 200px;
      }
      button { 
        font-size: 0.95rem; 
        max-width: 100%; 
        padding: 14px 25px;
      }
      h1 {
        font-size: 1.8rem;
        margin-bottom: 15px;
      }
      .custom-dropdown {
        font-size: 0.9rem;
        padding: 12px;
      }
      .custom-dropdown .options div {
        padding: 12px;
        font-size: 0.9rem;
      }
      .checkbox-container {
        font-size: 0.9rem;
        padding-left: 35px;
      }
      .checkbox-container .checkmark {
        height: 22px;
        width: 22px;
      }
      .checkbox-container input:checked ~ .checkmark:after {
        left: 7px;
        top: 3px;
        width: 6px;
        height: 12px;
      }
      .theme-switcher {
        width: 50px;
        height: 50px;
        font-size: 20px;
        bottom: 15px;
        right: 15px;
      }
    }

    <?= getBannerCSS() ?>

  </style>

    @media (max-width: 768px) {
      .main-container {
        padding: 10px;
      }

      .container {
        padding: 20px;
      }

      .form-row {
        grid-template-columns: 1fr;
      }

      .nights-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      h1 {
        font-size: 1.5em;
      }

      .theme-switcher {
        width: 45px;
        height: 45px;
        font-size: 18px;
      }
    }

    @media (max-width: 480px) {
      .nights-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">
    🌙
  </div>

  <div class="main-container">
    <div id="form-container">
    <div class="container">
    <h1>basement application form</h1>
    <form action="submit.php" method="POST" id="applicationForm">
      <input type="text" name="name" placeholder="name" required>
      <input type="email" name="email" placeholder="email" required>
      <input type="tel" name="gfphone" placeholder="girlfriend's phone number (optional)">
      <textarea name="reason" rows="4" placeholder="why did you apply" required></textarea>
      <input type="number" name="cage" placeholder="how many times have you slept in a cage this week" min="0" max="7" required>
      <div class="form-group">
  <label class="animated-label">preferred location to work at (optional)</label>
  <div class="custom-dropdown" id="locationDropdown">
    <div class="selected">Select a location</div>
    <div class="options">
      <div data-value="Trg Sv. Martina 8, 40313, Sveti Martin na Muri, Croatia">Trg Sv. Martina 8, 40313, Sveti Martin na Muri, Croatia</div>
      <div data-value="Vršanska ul. 18 A, 51500, Krk, Croatia">Vršanska ul. 18 A, 51500, Krk, Croatia</div>
      <div data-value="Gundulićeva poljana 4, 20230, Ston, Croatia">Gundulićeva poljana 4, 20230, Ston, Croatia</div>
      <div data-value="Station Rd, Epsom, Esher KT19 8EW, United Kingdom">Station Rd, Epsom, Esher KT19 8EW, United Kingdom (Shipment via post in a cage needed)</div>
      <div data-value="Other">Other (Please note which via email)</div>
    </div>
    <input type="hidden" name="preferredLocation" required>
  </div>
</div>
<div class="form-group">
  <label class="checkbox-container">
    I agree to the terms of the Data Retention Policy
    <input type="checkbox" name="agreeTerms" id="agreeTerms" required>
    <span class="checkmark"></span>
  </label>
</div>

    <label style="font-weight:bold; display:block; margin-top:10px;">Are you a cat?</label>

<div class="radio-group">
  <input type="radio" id="catYes" name="isCat" value="Yes" required>
  <label for="catYes">Yes</label>

  <input type="radio" id="catNo" name="isCat" value="No" required>
  <label for="catNo">No</label>
</div>
      <!-- Optional owner field -->
      <input type="text" name="owner" id="ownerField" placeholder="Owner's name and/or email address (for cats only)" style="display:none;">

        <button type="submit" class="submit-btn">
          🚀 Submit Application
        </button>
      </form>

      <div class="status-link">
        <a href="check-status.php">📋 Check Application Status</a>
      </div>
    </div>
  </div>

  <script>
    // Theme switcher functionality
    const themeSwitcher = document.getElementById('themeSwitcher');
    const body = document.body;

    // Load saved theme
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
      body.setAttribute('data-theme', 'dark');
      themeSwitcher.textContent = '☀️';
    }

    // Theme toggle
    themeSwitcher.addEventListener('click', () => {
      const isDark = body.getAttribute('data-theme') === 'dark';
      
      if (isDark) {
        body.removeAttribute('data-theme');
        themeSwitcher.textContent = '🌙';
        localStorage.setItem('theme', 'light');
      } else {
        body.setAttribute('data-theme', 'dark');
        themeSwitcher.textContent = '☀️';
        localStorage.setItem('theme', 'dark');
      }
    });

    // Form validation
    document.getElementById('applicationForm').addEventListener('submit', function(e) {
      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const address = document.getElementById('address').value.trim();

      if (!name || !email || !address) {
        e.preventDefault();
        alert('Please fill in all required fields (Name, Email, and Address).');
        return;
      }

      // Email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return;
      }
    });
  </script>
  
  <?php
  // Close database connection at the end
  if (isset($conn)) {
      $conn->close();
  }
  ?>
</body>
</html>
