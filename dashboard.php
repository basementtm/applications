<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once '/var/www/config/db_config.php';
require_once 'user_auth.php';

// Check if user is logged in
requireLogin();

// Establish database connection
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data
$user_data = getUserData();
$applications = getUserApplications();

// Check for welcome message
$show_welcome = isset($_GET['welcome']) && $_GET['welcome'] == '1';

// Check for status messages
$status_message = '';
$status_type = '';
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'application_submitted':
            $status_message = 'Your application has been submitted successfully!';
            $status_type = 'success';
            break;
        case 'application_updated':
            $status_message = 'Your application has been updated successfully!';
            $status_type = 'success';
            break;
    }
}

$current_page = 'dashboard.php';
?>

<!DOCTYPE html>
<html lang="en" <?php
$theme = $_COOKIE['theme'] ?? 'light';
if ($theme === 'dark') {
    echo 'data-theme="dark"';
}
?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - girlskissing.dev</title>
    <style>
        :root {
            --bg-color: #f8f9fa;
            --container-bg: #ffffff;
            --text-color: #333333;
            --primary-pink: #ff1493;
            --secondary-pink: #ff69b4;
            --border-color: #dee2e6;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --input-bg: #f1f3f5;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
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
        
        <?php echo getUserNavbarCSS(); ?>
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-pink), var(--secondary-pink));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px var(--shadow-color);
        }
        
        .welcome-banner h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }
        
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background-color: var(--container-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .dashboard-card h3 {
            color: var(--primary-pink);
            margin: 0 0 15px 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background-color: var(--input-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-pink);
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .applications-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .application-item {
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 10px;
            background-color: var(--input-bg);
            transition: transform 0.2s ease;
        }
        
        .application-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .application-id {
            font-weight: bold;
            color: var(--primary-pink);
            font-size: 1.1rem;
        }
        
        .application-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-unreviewed {
            background-color: #ffeaa7;
            color: #d63031;
        }
        
        .status-stage2, .status-under-review {
            background-color: #a29bfe;
            color: #6c5ce7;
        }
        
        .status-stage3 {
            background-color: #81ecec;
            color: #00cec9;
        }
        
        .status-accepted, .status-approved {
            background-color: #55efc4;
            color: #00b894;
        }
        
        .status-rejected, .status-denied {
            background-color: #fab1a0;
            color: #e17055;
        }
        
        .status-invalid {
            background-color: #e67e22;
            color: white;
        }
        
        .status-pending {
            background-color: #ffeaa7;
            color: #d63031;
        }
        
        .application-details {
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .application-date {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.6;
            margin-top: 5px;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 10px 20px;
            background-color: var(--primary-pink);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            background-color: var(--secondary-pink);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        .action-btn.secondary {
            background-color: var(--text-color);
            opacity: 0.7;
        }
        
        .action-btn.secondary:hover {
            opacity: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-color);
            opacity: 0.6;
        }
        
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-error {
            background-color: var(--danger-color);
            color: white;
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
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .application-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php renderUserNavbar($current_page, true); ?>
    
    <?php if ($show_welcome): ?>
        <div class="welcome-banner">
            <h2>🎉 Welcome to girlskissing.dev, <?php echo htmlspecialchars($user_data['username']); ?>!</h2>
            <p>Your account has been created successfully. You can now submit and track your applications.</p>
        </div>
    <?php endif; ?>
    
    <?php if ($status_message): ?>
        <div class="status-message status-<?php echo $status_type; ?>">
            <?php echo htmlspecialchars($status_message); ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-grid">
        <!-- Account Overview -->
        <div class="dashboard-card">
            <h3>👤 Account Overview</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($applications); ?></span>
                    <span class="stat-label">Applications</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $user_data['two_factor_enabled'] ? 'Yes' : 'No'; ?></span>
                    <span class="stat-label">2FA Enabled</span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h3>⚡ Quick Actions</h3>
            <div class="quick-actions">
                <a href="index.php" class="action-btn">
                    📝 Submit New Application
                </a>
                <a href="status-check.html" class="action-btn secondary">
                    📊 Check Application Status
                </a>
                <a href="settings.php" class="action-btn secondary">
                    ⚙️ Account Settings
                </a>
            </div>
        </div>
    </div>
    
    <!-- Applications List -->
    <div class="dashboard-card">
        <h3>📋 Your Applications</h3>
        <div class="applications-list">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <span class="icon">📝</span>
                    <h4>No Applications Yet</h4>
                    <p>You haven't submitted any applications yet. Ready to get started?</p>
                    <a href="index.php" class="action-btn">Submit Your First Application</a>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <?php if (is_array($app)): ?>
                    <div class="application-item">
                        <div class="application-header">
                            <span class="application-id">#<?php echo htmlspecialchars($app['application_id']); ?></span>
                            <span class="application-status status-<?php echo strtolower(str_replace(' ', '-', $app['status'])); ?>">
                                <?php echo htmlspecialchars($app['status']); ?>
                            </span>
                        </div>
                        <div class="application-details">
                            <strong><?php echo htmlspecialchars($app['name']); ?></strong> • <?php echo htmlspecialchars($app['email']); ?>
                            <?php if (!empty($app['gfphone'])): ?>
                                <br>Phone: <?php echo htmlspecialchars($app['gfphone']); ?>
                            <?php endif; ?>
                            <?php if (!empty($app['reason'])): ?>
                                <br>Reason: <?php echo htmlspecialchars($app['reason']); ?>
                            <?php endif; ?>
                            <?php if ($app['cage'] > 0): ?>
                                <br>Nights in Cage: <?php echo htmlspecialchars($app['cage']); ?>
                            <?php endif; ?>
                            <?php if (!empty($app['isCat'])): ?>
                                <br>Cat: <?php echo htmlspecialchars($app['isCat']); ?>
                            <?php endif; ?>
                            <?php if (!empty($app['owner'])): ?>
                                <br>Owner: <?php echo htmlspecialchars($app['owner']); ?>
                            <?php endif; ?>
                            <?php if (!empty($app['preferredLocation'])): ?>
                                <br>Preferred Location: <?php echo htmlspecialchars($app['preferredLocation']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="application-date">
                            Submitted: <?php echo date('M j, Y g:i A', strtotime($app['created_at'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php echo getUserNavbarJS(); ?>
    
    <script>
        // Theme Switcher
        const themeSwitcher = document.getElementById("themeSwitcher");
        const htmlEl = document.documentElement;

        // Set initial theme from cookie
        const currentTheme = "<?php echo $theme; ?>";
        if (currentTheme === "dark") {
            htmlEl.setAttribute("data-theme", "dark");
            themeSwitcher.textContent = "☀️";
        }

        themeSwitcher.addEventListener("click", () => {
            const isDark = htmlEl.getAttribute("data-theme") === "dark";
            
            if (isDark) {
                htmlEl.removeAttribute("data-theme");
                themeSwitcher.textContent = "🌙";
                document.cookie = "theme=light;path=/;max-age=31536000";
            } else {
                htmlEl.setAttribute("data-theme", "dark");
                themeSwitcher.textContent = "☀️";
                document.cookie = "theme=dark;path=/;max-age=31536000";
            }
        });
    </script>
</body>
</html>
