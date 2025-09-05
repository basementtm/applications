<?php
session_start();

// Include database connection
require_once '/var/www/config/db_config.php';
require_once 'user_auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error_message = 'Username can only contain letters, numbers, and underscores.';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error_message = 'Username must be between 3 and 20 characters.';
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = 'Username or email already exists.';
        } else {
            // Create new user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (username, email, password_hash, role, active, two_factor_enabled, created_at) 
                          VALUES (?, ?, ?, 'user', 1, 0, CURRENT_TIMESTAMP)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $username, $email, $password_hash);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Get the new user data
                $user_data = getUserData($user_id);
                
                if ($user_data) {
                    // Start user session
                    startUserSession($user_data);
                    
                    // Redirect to dashboard
                    header("Location: dashboard.php?welcome=1");
                    exit();
                } else {
                    $error_message = 'Account created but login failed. Please try logging in manually.';
                }
            } else {
                $error_message = 'Error creating account. Please try again.';
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - girlskissing.dev</title>
    <style>
        <?php echo getUserNavbarCSS(); ?>
        
        .container {
            max-width: 400px;
            margin: 50px auto;
            background-color: var(--container-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: var(--primary-pink);
            margin: 0 0 10px 0;
            font-size: 1.8rem;
        }
        
        .register-header p {
            color: var(--text-color);
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
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-pink);
        }
        
        .register-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-pink);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .register-btn:hover {
            background-color: var(--secondary-pink);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .login-link a {
            color: var(--primary-pink);
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background-color: var(--danger-color);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background-color: var(--success-color);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .password-requirements {
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .back-link {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .back-link a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="back-link">
        <a href="index.php">← Back to Application Form</a>
    </div>
    
    <div class="container">
        <div class="register-header">
            <h2>🌸 Create Account</h2>
            <p>Join us to track your applications</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                <div class="password-requirements">3-20 characters, letters, numbers, and underscores only</div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <div class="password-requirements">At least 8 characters</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="register-btn">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
