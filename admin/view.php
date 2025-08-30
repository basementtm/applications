<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('../config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$application_id = $_GET['id'] ?? '';
$application = null;

if (!empty($application_id)) {
    $sql = "SELECT * FROM applicants WHERE application_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $application = $result->fetch_assoc();
    }
    $stmt->close();
}

$conn->close();

if (!$application) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - <?= htmlspecialchars($application['application_id']) ?></title>
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
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--container-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 10px;
        }

        h1 {
            color: var(--primary-pink);
            margin: 0;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-weight: bold;
            margin: 0 5px;
        }

        .btn-primary { background-color: var(--primary-pink); color: white; }
        .btn-secondary { background-color: var(--secondary-pink); color: white; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .application-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .detail-section {
            background-color: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .detail-section h3 {
            color: var(--primary-pink);
            margin-top: 0;
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: bold;
            color: var(--text-color);
            min-width: 120px;
        }

        .detail-value {
            color: var(--text-color);
            text-align: right;
            word-break: break-word;
            max-width: 60%;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            text-align: center;
            display: inline-block;
        }

        .status-unreviewed { background-color: var(--secondary-pink); color: white; }
        .status-stage2 { background-color: #ffa502; color: white; }
        .status-stage3 { background-color: #3742fa; color: white; }
        .status-accepted { background-color: #2ed573; color: white; }
        .status-denied { background-color: #ff4757; color: white; }

        .reason-text {
            background-color: var(--container-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-top: 10px;
            font-style: italic;
            line-height: 1.6;
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
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .application-details {
                grid-template-columns: 1fr;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .detail-value {
                text-align: left;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <div class="container">
        <div class="header">
            <h1>📋 Application Details</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <div class="application-details">
            <!-- Basic Information -->
            <div class="detail-section">
                <h3>🆔 Basic Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Application ID:</span>
                    <span class="detail-value"><?= htmlspecialchars($application['application_id']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($application['name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">
                        <a href="mailto:<?= htmlspecialchars($application['email']) ?>" style="color: var(--primary-pink);">
                            <?= htmlspecialchars($application['email']) ?>
                        </a>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Submitted:</span>
                    <span class="detail-value"><?= date('Y-m-d H:i', strtotime($application['created_at'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?= htmlspecialchars($application['status']) ?>">
                            <?= ucfirst(htmlspecialchars($application['status'])) ?>
                        </span>
                    </span>
                </div>
            </div>

            <!-- Application Details -->
            <div class="detail-section">
                <h3>📝 Application Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Is Cat:</span>
                    <span class="detail-value"><?= htmlspecialchars($application['isCat']) ?></span>
                </div>
                <?php if ($application['owner']): ?>
                <div class="detail-row">
                    <span class="detail-label">Owner:</span>
                    <span class="detail-value"><?= htmlspecialchars($application['owner']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Cage Nights/Week:</span>
                    <span class="detail-value"><?= htmlspecialchars($application['cage']) ?></span>
                </div>
                <?php if ($application['gfphone']): ?>
                <div class="detail-row">
                    <span class="detail-label">GF Phone:</span>
                    <span class="detail-value">
                        <a href="tel:<?= htmlspecialchars($application['gfphone']) ?>" style="color: var(--primary-pink);">
                            <?= htmlspecialchars($application['gfphone']) ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($application['preferredLocation']): ?>
                <div class="detail-row">
                    <span class="detail-label">Preferred Location:</span>
                    <span class="detail-value"><?= htmlspecialchars($application['preferredLocation']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Agreed to Terms:</span>
                    <span class="detail-value"><?= $application['agreeTerms'] ? 'Yes' : 'No' ?></span>
                </div>
            </div>

            <!-- Application Reason -->
            <div class="detail-section" style="grid-column: 1 / -1;">
                <h3>💭 Application Reason</h3>
                <div class="reason-text">
                    <?= nl2br(htmlspecialchars($application['reason'])) ?>
                </div>
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
</body>
</html>
