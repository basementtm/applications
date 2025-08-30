<?php
// Navigation component - include this on all admin pages except settings
function renderAdminNavbar($currentPage = '') {
    $username = $_SESSION['admin_username'] ?? 'Unknown';
    
    // Define nav items with their icons and titles
    $navItems = [
        'dashboard.php' => ['🏠', 'Dashboard'],
        'users.php' => ['👥', 'Manage Users'],
        'settings.php' => ['⚙️', 'Settings']
    ];
    
    echo '<div class="header">';
    echo '<h1>🏠 Admin Dashboard</h1>';
    echo '<div class="header-actions">';
    echo '<span>Welcome, ' . htmlspecialchars($username) . '</span>';
    
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
        .header {
            background-color: var(--container-bg);
            padding: 15px 20px;
            box-shadow: 0 2px 5px var(--shadow-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .header h1 {
            color: var(--primary-pink);
            font-size: 1.5rem;
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
