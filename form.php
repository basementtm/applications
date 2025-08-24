<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>basement application form</title>
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
    }

    #form-container, #maintenance {
      background-color: #fff0f5;
      padding: 30px 40px;
      border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 400px;
      width: 90%;
      box-sizing: border-box;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeUp 1s forwards;
    }

    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }

    h1 {
      color: #ff1493;
      margin-bottom: 20px;
      opacity: 0;
      transform: translateY(-10px);
      animation: fadeSlideDown 0.8s forwards;
    }

    @keyframes fadeSlideDown {
      to { opacity: 1; transform: translateY(0); }
    }

    input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      box-sizing: border-box;
      transition: transform 0.2s, box-shadow 0.2s;
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
      background-color: #ff69b4;
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
      background-color: #ff1493;
      transform: scale(1.05);
    }

    .radio-group label:hover { transform: scale(1.05); }

    /* Submit button animations */
    button {
      padding: 12px 25px;
      background-color: #ff69b4;
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
      background-color: #ff1493;
      animation: pulse 0.6s ease-in-out;
    }

    button:active { transform: scale(0.95); }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

    footer { font-size: 0.9rem; color: #333; padding-top: 20px; text-align: center; }

    @media (max-width: 500px) {
      #form-container, #maintenance { padding: 20px; }
      input[type="text"], input[type="email"], input[type="tel"], input[type="number"], textarea { font-size: 0.9rem; }
      .radio-group label { padding: 8px 15px; font-size: 0.9rem; min-width: 70px; }
      button { font-size: 0.95rem; max-width: 100%; }
    }
  </style>
</head>
<body>

  <div id="form-container">
    <h1>basement application form</h1>
    <form action="https://submit.emmameowss.gay/submit.php" method="POST" id="applicationForm">
      <input type="text" name="name" placeholder="name" required>
      <input type="email" name="email" placeholder="email" required>
      <input type="tel" name="gfphone" placeholder="girlfriend's phone number (optional)">
      <textarea name="reason" rows="4" placeholder="why did you apply" required></textarea>
      <input type="number" name="cage" placeholder="how many times have you slept in a cage this week" min="0" max="7" required>
      
      <label style="font-weight:bold; display:block; margin-top:10px;">Are you a cat?</label>

<div class="radio-group">
  <input type="radio" id="catYes" name="isCat" value="Yes" required>
  <label for="catYes">Yes</label>

  <input type="radio" id="catNo" name="isCat" value="No" required>
  <label for="catNo">No</label>
</div>

      <!-- Optional owner field -->
      <input type="text" name="owner" id="ownerField" placeholder="Owner's name and/or email address (for cats only)" style="display:none;">

      <button type="submit">Submit</button>
      <p style="margin-top: 15px; font-size: 0.9rem;">
  <a href="retention.html" style="color:#ff1493; text-decoration:underline;">
    View our Data Retention Policy
  </a>
</p>
    </form>
  </div>

  <div id="maintenance" style="display:none;">
    <h1>🚧 site temporarily offline</h1>
    <p>we're changing the site a bit please standby - applications are back asap!</p>
  </div>

  <script>
    // Maintenance check
    fetch("https://submit.emmameowss.gay/status.php")
      .then(res => res.json())
      .then(data => {
        if (data.maintenance) {
          document.getElementById("form-container").style.display = "none";
          document.getElementById("maintenance").style.display = "block";
        }
      })
      .catch(err => console.error("Maintenance check failed:", err));

    // Show owner field only if "Yes" is selected
    const catYes = document.getElementById("catYes");
    const catNo = document.getElementById("catNo");
    const ownerField = document.getElementById("ownerField");

    catYes.addEventListener("change", () => { ownerField.style.display = "block"; });
    catNo.addEventListener("change", () => { ownerField.style.display = "none"; });
  </script>
</body>
</html>