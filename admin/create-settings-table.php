<?php
session_start();

// Check if user is logged in and is super admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    // Create the site_settings table
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(100),
            INDEX idx_setting_name (setting_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($create_table_sql)) {
        // Insert default maintenance mode setting
        $insert_default_sql = "
            INSERT IGNORE INTO site_settings (setting_name, setting_value, description, updated_by) 
            VALUES ('maintenance_mode', '0', 'Controls whether the site is in maintenance mode (1=on, 0=off)', 'system')
        ";
        
        if ($conn->query($insert_default_sql)) {
            $message = "✅ Settings table created successfully and default maintenance setting added!";
            $message_type = "success";
        } else {
            $message = "❌ Table created but failed to add default setting: " . $conn->error;
            $message_type = "error";
        }
    } else {
        $message = "❌ Failed to create settings table: " . $conn->error;
        $message_type = "error";
    }
}

// Check if table exists
$table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($check_table && $check_table->num_rows > 0) {
    $table_exists = true;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Settings Table - Admin</title>
    <style>
        :root {
            --bg-color: #ffc0cb;
            --container-bg: #fff0f5;
            --text-color: #333;
            --primary-pink: #ff1493;
            --secondary-pink: #ff69b4;
            --border-color: #ccc;
            --shadow-color: rgba(0,0,0,0.1);
            --success-color: #2ed573;
            --danger-color: #ff4757;
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

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        h1 {
            color: var(--primary-pink);
            margin-bottom: 30px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-box {
            background-color: var(--container-bg);
            border: 2px solid var(--border-color);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .status-exists {
            border-color: var(--success-color);
            background-color: #f0fff0;
        }

        .status-missing {
            border-color: var(--danger-color);
            background-color: #fff5f5;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
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
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        [data-theme="dark"] .status-exists {
            background-color: #1e4620;
        }

        [data-theme="dark"] .status-missing {
            background-color: #4a2c2a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Settings Setup</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <div class="status-box <?= $table_exists ? 'status-exists' : 'status-missing' ?>">
            <h3><?= $table_exists ? '✅ Table Status: EXISTS' : '❌ Table Status: MISSING' ?></h3>
            <p>
                <?php if ($table_exists): ?>
                    The site_settings table is already created and ready to use.
                <?php else: ?>
                    The site_settings table needs to be created for database-based maintenance mode.
                <?php endif; ?>
            </p>
        </div>
        
        <?php if (!$table_exists): ?>
            <form method="POST">
                <button type="submit" name="create_table" class="btn btn-primary" 
                        onclick="return confirm('Create the site_settings table? This is safe and will not affect existing data.')">
                    🔧 Create Settings Table
                </button>
            </form>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
</body>
</html>
