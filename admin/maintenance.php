<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if maintenance is actually enabled
$maintenance_active = false;
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $maintenance_sql = "SELECT setting_value FROM site_settings WHERE setting_name = 'admin_maintenance_mode' LIMIT 1";
        $maintenance_result = $conn->query($maintenance_sql);
        if ($maintenance_result && $maintenance_result->num_rows > 0) {
            $maintenance_row = $maintenance_result->fetch_assoc();
            $maintenance_active = ($maintenance_row['setting_value'] === '1');
        }
    }
} catch (Exception $e) {
    // Continue if there's a database error
}

// If maintenance is not active or user is Emma, redirect to dashboard
if (!$maintenance_active || (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] === 'emma')) {
    header("Location: dashboard.php");
    exit();
}

$username = $_SESSION['admin_username'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - Admin Panel</title>
    <style>
        :root {
            --bg-color: #ffc0cb;
            --container-bg: #fff0f5;
            --text-color: #333;
            --primary-pink: #ff1493;
            --secondary-pink: #ff69b4;
            --border-color: #ccc;
            --shadow-color: rgba(0,0,0,0.1);
            --warning-color: #ffa502;
        }

        [data-theme="dark"] {
            --bg-color: #2d1b2e;
            --container-bg: #3d2b3e;
            --text-color: #e0d0e0;
            --primary-pink: #ff6bb3;
            --secondary-pink: #d147a3;
            --border-color: #666;
            --shadow-color: rgba(0,0,0,0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .maintenance-container {
            max-width: 600px;
            text-align: center;
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 3px solid var(--warning-color);
        }

        .maintenance-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        h1 {
            color: var(--warning-color);
            font-size: 2rem;
            margin-bottom: 20px;
        }

        .message {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: var(--text-color);
        }

        .user-info {
            background-color: rgba(255, 165, 2, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid var(--warning-color);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-weight: bold;
            margin: 5px;
        }

        .btn-primary {
            background-color: var(--primary-pink);
            color: white;
        }

        .btn-secondary {
            background-color: var(--secondary-pink);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px var(--shadow-color);
        }

        .refresh-note {
            font-size: 0.9rem;
            color: var(--border-color);
            margin-top: 20px;
            font-style: italic;
        }

        /* Theme Switcher */
        .theme-switcher {
            position: fixed;
            bottom: 20px;
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

        @media (max-width: 768px) {
            .maintenance-container {
                margin: 20px;
                padding: 30px 20px;
            }

            h1 {
                font-size: 1.5rem;
            }

            .message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <div class="maintenance-container">
        <div class="maintenance-icon">🚧</div>
        <h1>Admin Panel Under Maintenance</h1>
        
        <div class="user-info">
            <strong>Logged in as:</strong> <?= htmlspecialchars($username) ?>
        </div>
        
        <div class="message">
            The admin panel is currently under maintenance and has been temporarily closed by the owner. 
            Please try again later or contact the owner if this is urgent.
        </div>
        
        <div class="message">
            Only the owner has access during maintenance mode.
        </div>
        
        <a href="dashboard.php" class="btn btn-primary">🔄 Try Again</a>
        <a href="logout.php" class="btn btn-secondary">🚪 Logout</a>
        
        <div class="refresh-note">
            This page will automatically check for updates when you refresh.
        </div>
        
        <div style="margin-top: 20px;">
            <a href="https://status.girlskissing.dev" target="_blank" style="color: var(--primary-pink); text-decoration: underline;">Check System Status Page</a>
        </div>
    </div>

    <script>
        // Theme Switcher
        const themeSwitcher = document.getElementById("themeSwitcher");
        const body = document.body;

        const currentTheme = localStorage.getItem("theme") || "light";
        if (currentTheme === "dark") {
            body.setAttribute("data-theme", "dark");
            themeSwitcher.textContent = "☀️";
        }

        themeSwitcher.addEventListener("click", () => {
            const isDark = body.getAttribute("data-theme") === "dark";
            
            if (isDark) {
                body.removeAttribute("data-theme");
                themeSwitcher.textContent = "🌙";
                localStorage.setItem("theme", "light");
            } else {
                body.setAttribute("data-theme", "dark");
                themeSwitcher.textContent = "☀️";
                localStorage.setItem("theme", "dark");
            }
        });

        // Auto refresh every 30 seconds to check if maintenance is disabled
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
    
    <?php
    // Close database connection
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
