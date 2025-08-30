<?php
// Navigation component - include this on all admin pages except settings
function renderAdminNavbar($currentPage = '') {
    global $conn; // Access the database connection
    $username = $_SESSION['admin_username'] ?? 'Unknown';
    
    // Check maintenance status from database with fallback
    $maintenance_active = false;
    if (isset($conn) && $conn && !$conn->connect_error) {
        try {
            // Check if site_settings table exists
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
            // Silently fail if there's a database error
            $maintenance_active = false;
        }
    }
    
    // Define nav items with their icons and titles
    $navItems = [
        'dashboard.php' => ['🏠', 'Dashboard'],
        'settings.php' => ['⚙️', 'Settings']
    ];
    
    // Add maintenance and banner management only for non-readonly users
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'readonly_admin') {
        $navItems['maintenance-control.php'] = ['🚧', 'Maintenance'];
        $navItems['banner.php'] = ['📢', 'Banner Management'];
    }
    
    // Add link to return to main form
    $navItems['../index.php'] = ['📝', 'Return to Form'];
    
    // Add owner-only navigation for Emma
    if (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] === 'emma') {
        $navItems['owner.php'] = ['👧', 'Owner Panel'];
        $navItems['action-logs.php'] = ['📊', 'Action Logs'];
        $navItems['ip-ban-management.php'] = ['🚫', 'IP Bans'];
        $navItems['privacy-notifications.php'] = ['🔔', 'Privacy Notifications'];
    }
    
    echo '<div class="header">';
    echo '<h1>🏠 Admin Dashboard</h1>';
    echo '<div class="header-actions">';
    
    // Add maintenance status indicator
    if ($maintenance_active) {
        echo '<span class="maintenance-badge" title="Maintenance Mode Active - Applications Closed">🚧 MAINTENANCE</span>';
    }
    
    // Render navigation buttons
    foreach ($navItems as $page => $details) {
        $icon = $details[0];
        $title = $details[1];
        $activeClass = ($currentPage === $page) ? ' btn-active' : '';
        echo '<a href="' . $page . '" class="btn btn-secondary btn-sm' . $activeClass . '">' . $icon . ' ' . $title . '</a>';
    }
    
    echo '<a href="logout.php" class="btn btn-primary btn-sm">🚪 Logout</a>';
    echo '</div>';
    echo '</div>';
}

// CSS for the navbar - include this in the <style> section
function getNavbarCSS() {
    return '
        :root {
            --bg-color: #ffc0cb;
            --container-bg: #fff0f5;
            --text-color: #333;
            --primary-pink: #ff1493;
            --secondary-pink: #ff69b4;
            --border-color: #ccc;
            --shadow-color: rgba(0,0,0,0.1);
            --input-bg: #fff0f5;
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
            --input-bg: #4a3a4a;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }

        .header {
            background-color: var(--container-bg);
            padding: 15px 20px;
            box-shadow: 0 2px 5px var(--shadow-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .header h1 {
            color: var(--primary-pink);
            font-size: 1.5rem;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .header-actions span {
            margin-right: 10px;
            font-weight: bold;
        }

        .maintenance-badge {
            background-color: #ff4757;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
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
        }

        .btn-primary {
            background-color: var(--primary-pink);
            color: white;
        }

        .btn-secondary {
            background-color: var(--secondary-pink);
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .btn-active {
            background-color: var(--primary-pink) !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px var(--shadow-color);
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
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-actions {
                justify-content: center;
            }
        }
    ';
}
?>
