<?php
// user_auth.php handles session starting
require_once __DIR__ . '/../user_auth.php';

// Include action logging functions
require_once 'action_logger.php';

// Include navbar component
include('navbar.php');

// Check if user is logged in and is admin
requireAdmin('../login.php?redirect=admin/view.php');

// Check if user is still active (not disabled)
checkUserStatus();

include('/var/www/config/db_config.php');
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
        
        // Log the application view action
        logApplicationView($application_id, $application_id);
    }
    $stmt->close();
}

// Don't close connection here - navbar needs it later
// $conn->close();

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
        <?php 
        // user_auth.php is already included, which contains navbar functions
        echo getNavbarCSS(); 
        ?>

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--container-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
        }

        .page-title {
            color: var(--primary-pink);
            margin: 0 0 30px 0;
            font-size: 2rem;
            text-align: center;
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

        .page-title {
            color: var(--primary-pink);
            margin: 0 0 30px 0;
            font-size: 2rem;
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
    
    <?php renderAdminNavbar('view.php'); ?>
    
    <div class="container">
        <h1 class="page-title">📋 Application Details</h1>
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
    
    <?php echo getNavbarJS(); ?>

    <?php
    // Close database connection at the end
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>
