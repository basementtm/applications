<?php
session_start();

// Include auth functions for user status checking
require_once 'auth_functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Check if user is still active (not disabled)
checkUserStatus();

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$application_id = $_GET['id'] ?? '';
$confirm = $_GET['confirm'] ?? '';

if (empty($application_id)) {
    header("Location: dashboard.php");
    exit();
}

// If confirmed, delete the application
if ($confirm === 'yes') {
    $sql = "DELETE FROM applicants WHERE application_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $application_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        // Close connection before redirect since we're exiting
        $conn->close();
        header("Location: dashboard.php?deleted=" . urlencode($application_id));
        exit();
    } else {
        $error = "Error deleting application. Please try again.";
    }
    $stmt->close();
}

// Fetch application data for confirmation
$sql = "SELECT application_id, name, email, status FROM applicants WHERE application_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $application_data = $result->fetch_assoc();
} else {
    header("Location: dashboard.php");
    exit();
}

$stmt->close();
// Don't close connection here - navbar needs it later
// $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Application - Admin</title>
    <?php include 'navbar.php'; ?>
    <style>
        <?php echo getNavbarCSS(); ?>

        --danger-color: #ff4757;
        --warning-bg: #fff3cd;
        --warning-border: #ffeaa7;
        --warning-text: #856404;

        [data-theme="dark"] {
            --warning-bg: #4a3a2a;
            --warning-border: #6b5b2a;
            --warning-text: #ffd93d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .page-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 120px);
            padding: 20px;
        }

        .container {
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            max-width: 600px;
            width: 100%;
            text-align: center;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .warning-box {
            background-color: var(--warning-bg);
            border: 2px solid var(--warning-border);
            color: var(--warning-text);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-weight: bold;
        }

        .app-details {
            background-color: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: bold;
            color: var(--primary-pink);
        }

        .detail-value {
            color: var(--text-color);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }

        .status-unreviewed { background-color: var(--secondary-pink); }
        .status-stage2 { background-color: #ffa502; }
        .status-stage3 { background-color: #3742fa; }
        .status-accepted { background-color: #2ed573; }
        .status-denied { background-color: var(--danger-color); }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            min-width: 120px;
        }

        .btn-danger:hover {
            background-color: #ff3838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        [data-theme="dark"] .error-message {
            background-color: #4a2c2a;
            color: #ff6b6b;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn, .btn-danger {
                width: 100%;
            }
        }

        .warning-box {
            background-color: var(--warning-bg);
            border: 2px solid var(--warning-border);
            color: var(--warning-text);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-weight: bold;
        }

        .app-details {
            background-color: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: bold;
            color: var(--primary-pink);
        }

        .detail-value {
            color: var(--text-color);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }

        .status-unreviewed { background-color: var(--secondary-pink); }
        .status-stage2 { background-color: #ffa502; }
        .status-stage3 { background-color: #3742fa; }
        .status-accepted { background-color: #2ed573; }
        .status-denied { background-color: var(--danger-color); }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
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
            min-width: 120px;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-secondary {
            background-color: var(--secondary-pink);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
        }

        .btn-danger:hover {
            background-color: #ff3838;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        [data-theme="dark"] .error-message {
            background-color: #4a2c2a;
            color: #ff6b6b;
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

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('delete.php'); ?>
    
    <div class="page-container">
        <div class="container">
            <h1 style="color: var(--danger-color); margin-bottom: 30px; font-size: 2rem;">🗑️ Delete Application</h1>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="warning-box">
            ⚠️ <strong>WARNING:</strong> This action cannot be undone!<br>
            You are about to permanently delete this application from the database.
        </div>
        
        <div class="app-details">
            <h3 style="color: var(--primary-pink); margin-bottom: 15px;">Application Details</h3>
            <div class="detail-row">
                <span class="detail-label">Application ID:</span>
                <span class="detail-value"><?= htmlspecialchars($application_data['application_id']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?= htmlspecialchars($application_data['name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?= htmlspecialchars($application_data['email']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <span class="status-badge status-<?= htmlspecialchars($application_data['status']) ?>">
                        <?= ucfirst(htmlspecialchars($application_data['status'])) ?>
                    </span>
                </span>
            </div>
        </div>
        
        <div class="actions">
            <a href="delete.php?id=<?= urlencode($application_id) ?>&confirm=yes" 
               class="btn btn-danger" 
               onclick="return confirm('Are you absolutely sure you want to delete this application? This action cannot be undone!')">
                🗑️ Yes, Delete Application
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                ← Cancel & Go Back
            </a>
        </div>
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
    </script>
    
    <?php
    // Close database connection at the end
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
