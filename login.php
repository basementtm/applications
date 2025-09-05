<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Try different database config paths
$db_config_paths = [
    '/var/www/config/db_config.php',
    '../config/db_config.php',
    './config/db_config.php',
    'config/db_config.php'
];

$conn = null;
foreach ($db_config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Debug: Check if connection was established
if (!isset($conn)) {
    die('Database connection variable $conn not found. Tried paths: ' . implode(', ', $db_config_paths));
}
if ($conn === null) {
    die('Database connection is null. Check database credentials.');
}

require_once 'user_auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = isAdmin() ? 'admin/dashboard.php' : 'dashboard.php';
    header("Location: $redirect");
    exit();
}

$error_message = '';
$success_message = '';

// Check for query parameters
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'account_disabled':
            $error_message = 'Your account has been disabled. Please contact an administrator.';
            break;
        case 'session_expired':
            $error_message = 'Your session has expired. Please log in again.';
            break;
        case 'access_denied':
            $error_message = 'Access denied. Please log in with appropriate permissions.';
            break;
    }
}

if (isset($_GET['registered'])) {
    $success_message = 'Account created successfully! Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        // Check if database connection exists
        if (!isset($conn) || $conn === null) {
            $error_message = 'Database connection error. Please try again later.';
        } else {
            // Check user credentials
            $sql = "SELECT id, username, email, password_hash, role, active, two_factor_enabled FROM users WHERE (username = ? OR email = ?) AND active = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                // Successful login - start session
                startUserSession($user, $remember_me);
                
                // Determine redirect location
                $redirect_url = 'dashboard.php';
                if (isAdmin()) {
                    $redirect_url = 'admin/dashboard.php';
                }
                
                // Check for redirect parameter
                if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                    $redirect_url = $_GET['redirect'];
                }
                
                header("Location: $redirect_url");
                exit();
            } else {
                $error_message = 'Invalid username or password.';
            }
        } else {
            $error_message = 'Invalid username or password.';
        }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - girlskissing.dev</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #ffc0cb;
            color: #333;
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }
        
        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            flex-direction: column;
        }
        
        .container {
            max-width: 400px;
            background-color: #fff0f5;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-top: 60px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #ff1493;
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }
        
        .login-header p {
            color: #333;
            margin: 0;
            opacity: 0.8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            color: #333;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #ff1493;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            font-size: 0.9rem;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #ff1493;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            background-color: #ff69b4;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
        }
        
        .register-link a {
            color: #ff1493;
            text-decoration: none;
            font-weight: bold;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background-color: #dc3545;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background-color: #28a745;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .login-options {
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .login-options a {
            color: var(--primary-pink);
            text-decoration: none;
        }
        
        .login-options a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
        <div class="login-header">
            <h2>🔐 Welcome Back</h2>
            <p>Sign in to your account</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Remember me for 30 days</label>
            </div>
            
            <button type="submit" class="login-btn">Sign In</button>
        </form>
        
        <div class="login-options">
            <a href="forgot-password.php">Forgot your password?</a>
        </div>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Create one here</a>
        </div>
    </div>
    </div>
</body>
</html>
