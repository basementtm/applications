<?php
// Unified User Authentication Functions
// This replaces admin-specific auth functions with a unified system

function startUserSession($user_data, $remember_me = false) {
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['user_email'] = $user_data['email'];
    
    // Set session duration based on remember me
    $session_duration = $remember_me ? (30 * 24 * 60 * 60) : (8 * 60 * 60); // 30 days or 8 hours
    $_SESSION['session_expires'] = time() + $session_duration;
    
    // Update last login
    global $conn;
    if (isset($conn)) {
        $update_sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $user_data['id']);
        $stmt->execute();
        $stmt->close();
    }
}

function isLoggedIn() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        return false;
    }
    
    // Check session expiration
    if (isset($_SESSION['session_expires']) && time() > $_SESSION['session_expires']) {
        destroyUserSession();
        return false;
    }
    
    return true;
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role = $_SESSION['user_role'] ?? '';
    return in_array($role, ['readonly_admin', 'admin', 'super_admin']);
}

function isReadOnlyUser() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'readonly_admin';
}

function isSuperAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'super_admin';
}

function isOwner() {
    return isLoggedIn() && ($_SESSION['username'] ?? '') === 'emma';
}

function requireLogin($redirect_to = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_to");
        exit();
    }
}

function requireAdmin($redirect_to = 'dashboard.php?error=access_denied') {
    if (!isAdmin()) {
        header("Location: $redirect_to");
        exit();
    }
}

function destroyUserSession() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

function checkUserStatus() {
    if (!isLoggedIn()) {
        return;
    }
    
    global $conn;
    if (!isset($conn) || !$conn) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $check_sql = "SELECT active FROM users WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || !$result->fetch_assoc()['active']) {
        destroyUserSession();
        header("Location: login.php?error=account_disabled");
        exit();
    }
    
    $stmt->close();
}

function getUserData($user_id = null) {
    if (!isLoggedIn() && !$user_id) {
        return null;
    }
    
    global $conn;
    if (!isset($conn) || !$conn) {
        return null;
    }
    
    $id = $user_id ?? $_SESSION['user_id'];
    $sql = "SELECT id, username, email, role, active, two_factor_enabled, created_at, last_login FROM users WHERE id = ? AND active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    return $user_data;
}

function getUserApplications($user_id = null) {
    if (!isLoggedIn() && !$user_id) {
        return [];
    }
    
    global $conn;
    if (!isset($conn) || !$conn) {
        return [];
    }
    
    $id = $user_id ?? $_SESSION['user_id'];
    $sql = "SELECT application_id, name, email, gfphone, reason, cage, isCat, owner, preferredLocation, agreeTerms, status, created_at 
            FROM applicants 
            WHERE user_id = ? 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    $stmt->close();
    return $applications;
}

function linkApplicationToUser($application_id, $user_id) {
    global $conn;
    if (!isset($conn) || !$conn) {
        return false;
    }
    
    $sql = "UPDATE applicants SET user_id = ? WHERE application_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $application_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

// Legacy compatibility functions for existing admin code
function checkUserStatusLegacy() {
    checkUserStatus();
}

// Navigation helper functions
function renderUserNavbar($current_page = '', $is_main_site = false) {
    $username = $_SESSION['username'] ?? '';
    $is_logged_in = isLoggedIn();
    $is_admin = isAdmin();
    
    // Define navigation items
    $nav_items = [];
    
    if ($is_main_site) {
        // Main site navigation
        if ($is_logged_in) {
            $nav_items['dashboard.php'] = ['🏠', 'Dashboard'];
            $nav_items['settings.php'] = ['⚙️', 'Settings'];
        }
        $nav_items['privacy-policy.html'] = ['📜', 'Privacy Policy'];
        $nav_items['status-check.html'] = ['📊', 'Application Status'];
        
        if ($is_admin) {
            $nav_items['admin/dashboard.php'] = ['👑', 'Admin Panel'];
        }
    } else {
        // Admin panel navigation (kept for backward compatibility)
        $nav_items['dashboard.php'] = ['🏠', 'Dashboard'];
        $nav_items['settings.php'] = ['⚙️', 'Settings'];
        
        if (!isReadOnlyUser()) {
            $nav_items['maintenance-control.php'] = ['🚧', 'Maintenance'];
            $nav_items['banner.php'] = ['📢', 'Banner Management'];
        }
        
        if (isOwner()) {
            $nav_items['owner.php'] = ['👧', 'Owner Panel'];
            $nav_items['action-logs.php'] = ['📊', 'Action Logs'];
            $nav_items['ip-ban-management.php'] = ['🚫', 'IP Bans'];
        }
        
        $nav_items['../index.php'] = ['📝', 'Return to Form'];
    }
    
    echo '<div class="header">';
    echo '<h1>' . ($is_main_site ? '🌸 girlskissing.dev' : '🏠 Admin Dashboard') . '</h1>';
    echo '<div class="header-actions">';
    
    if ($is_logged_in) {
        echo '<span class="user-info">Welcome, <strong>' . htmlspecialchars($username) . '</strong></span>';
    }
    
    // Navigation dropdown
    echo '<div class="nav-dropdown">';
    echo '<button class="nav-toggle" id="navToggle">☰ Menu</button>';
    echo '<div class="nav-menu" id="navMenu">';
    
    // Render navigation items
    foreach ($nav_items as $page => $details) {
        $icon = $details[0];
        $title = $details[1];
        $active_class = ($current_page === $page) ? ' nav-active' : '';
        echo '<a href="' . $page . '" class="nav-item' . $active_class . '">' . $icon . ' ' . $title . '</a>';
    }
    
    if ($is_logged_in) {
        echo '<a href="logout.php" class="nav-item nav-logout">🚪 Logout</a>';
    } else {
        echo '<a href="login.php" class="nav-item nav-login">🔐 Login</a>';
        echo '<a href="register.php" class="nav-item nav-register">📝 Register</a>';
    }
    
    echo '</div></div>';
    echo '</div></div>';
}

function getUserNavbarCSS() {
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
            position: relative;
            z-index: 1;
        }

        .header {
            background-color: var(--container-bg);
            padding: 15px 20px;
            box-shadow: 0 2px 5px var(--shadow-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 10px;
            border-radius: 10px;
            opacity: 0;
            transform: translateY(-10px);
            animation: fadeSlideDown 0.8s forwards;
            position: relative;
            z-index: 100;
        }

        @keyframes fadeSlideDown {
            to { opacity: 1; transform: translateY(0); }
        }

        .header h1 {
            color: var(--primary-pink);
            font-size: 1.5rem;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
        }

        .user-info {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .nav-dropdown {
            position: relative;
            display: inline-block;
        }

        .nav-toggle {
            background-color: var(--primary-pink);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-toggle:hover {
            background-color: var(--secondary-pink);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .nav-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--container-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 8px 25px var(--shadow-color);
            z-index: 1001;
            min-width: 220px;
            overflow: hidden;
            margin-top: 5px;
        }

        .nav-menu.show {
            display: block;
            animation: dropDown 0.3s ease-out;
        }

        @keyframes dropDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-item {
            display: block;
            padding: 12px 16px;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
            font-weight: 500;
        }

        .nav-item:last-child {
            border-bottom: none;
        }

        .nav-item:hover {
            background-color: var(--secondary-pink);
            color: white;
            transform: translateX(5px);
        }

        .nav-active {
            background-color: var(--primary-pink);
            color: white;
            font-weight: bold;
        }

        .nav-logout {
            background-color: var(--danger-color);
            color: white;
            font-weight: bold;
        }

        .nav-logout:hover {
            background-color: #ff3838;
        }

        .nav-login {
            background-color: var(--primary-pink);
            color: white;
            font-weight: bold;
        }

        .nav-register {
            background-color: var(--secondary-pink);
            color: white;
            font-weight: bold;
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

            .nav-menu {
                right: auto;
                left: 0;
                width: 100%;
            }
        }
    ';
}

function getUserNavbarJS() {
    return '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const navToggle = document.getElementById("navToggle");
            const navMenu = document.getElementById("navMenu");
            
            if (navToggle && navMenu) {
                // Make sure the menu has the highest z-index when shown
                navToggle.addEventListener("click", function(e) {
                    e.stopPropagation();
                    navMenu.classList.toggle("show");
                    if (navMenu.classList.contains("show")) {
                        // When menu is shown, ensure it\'s above all content
                        navMenu.style.zIndex = "9999";
                    }
                });
                
                // Close menu when clicking outside
                document.addEventListener("click", function(e) {
                    if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                        navMenu.classList.remove("show");
                    }
                });
                
                // Close menu when clicking on a nav item
                const navItems = navMenu.querySelectorAll(".nav-item");
                navItems.forEach(item => {
                    item.addEventListener("click", function() {
                        navMenu.classList.remove("show");
                    });
                });
            }
        });
        </script>
    ';
}
?>
