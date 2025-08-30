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
$message = '';
$message_type = '';
$application_data = null;

if (empty($application_id)) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cage = intval($_POST['cage'] ?? 0);
    $isCat = $_POST['isCat'] ?? '';
    $preferredLocation = trim($_POST['preferredLocation'] ?? '');
    $status = $_POST['status'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || $cage < 0 || $cage > 7 || empty($isCat) || empty($status)) {
        $message = "Please fill in all required fields correctly.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        // Update application
        $sql = "UPDATE applicants SET name = ?, email = ?, cage = ?, isCat = ?, preferredLocation = ?, status = ? WHERE application_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissss", $name, $email, $cage, $isCat, $preferredLocation, $status, $application_id);
        
        if ($stmt->execute()) {
            $message = "Application updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating application. Please try again.";
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Fetch application data
$sql = "SELECT * FROM applicants WHERE application_id = ?";
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
    <title>Edit Application - Admin</title>
    <?php include 'navbar.php'; ?>
    <style>
        <?php echo getNavbarCSS(); ?>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 10px var(--shadow-color);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .page-title {
            color: var(--primary-pink);
            margin-bottom: 30px;
            text-align: center;
            font-size: 2rem;
        }

        .app-id {
            text-align: center;
            margin-bottom: 30px;
            padding: 10px;
            background-color: var(--input-bg);
            border-radius: 8px;
            font-weight: bold;
            color: var(--primary-pink);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--primary-pink);
        }

        input[type="text"], input[type="email"], input[type="number"], select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
        }

        select {
            cursor: pointer;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
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
            margin: 5px;
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

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
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

        [data-theme="dark"] .message.success {
            background-color: #1e4620;
            color: #4caf50;
        }

        [data-theme="dark"] .message.error {
            background-color: #4a2c2a;
            color: #ff6b6b;
        }

        .actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-block;
            margin-left: 10px;
        }

        .status-unreviewed { background-color: var(--secondary-pink); color: white; }
        .status-stage2 { background-color: #ffa502; color: white; }
        .status-stage3 { background-color: #3742fa; color: white; }
        .status-accepted { background-color: var(--success-color); color: white; }
        .status-denied { background-color: var(--danger-color); color: white; }

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
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <?php renderAdminNavbar('edit.php'); ?>
    
    <div class="container">
        <h1 style="color: var(--primary-pink); margin-bottom: 30px; text-align: center; font-size: 2rem;">✏️ Edit Application</h1>
        
        <div class="app-id">
            Application ID: <?= htmlspecialchars($application_data['application_id']) ?>
            <span class="status-badge status-<?= htmlspecialchars($application_data['status']) ?>">
                <?= ucfirst(htmlspecialchars($application_data['status'])) ?>
            </span>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($application_data['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($application_data['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="cage">Nights in Cage per Week:</label>
                    <input type="number" id="cage" name="cage" min="0" max="7" value="<?= htmlspecialchars($application_data['cage']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="isCat">Are you a cat?</label>
                    <select id="isCat" name="isCat" required>
                        <option value="Yes" <?= $application_data['isCat'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="No" <?= $application_data['isCat'] === 'No' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Application Status:</label>
                    <select id="status" name="status" required>
                        <option value="unreviewed" <?= $application_data['status'] === 'unreviewed' ? 'selected' : '' ?>>Unreviewed</option>
                        <option value="stage2" <?= $application_data['status'] === 'stage2' ? 'selected' : '' ?>>Stage 2 - Interview</option>
                        <option value="stage3" <?= $application_data['status'] === 'stage3' ? 'selected' : '' ?>>Stage 3 - Final Review</option>
                        <option value="accepted" <?= $application_data['status'] === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="denied" <?= $application_data['status'] === 'denied' ? 'selected' : '' ?>>Denied</option>
                        <option value="invalid" <?= $application_data['status'] === 'invalid' ? 'selected' : '' ?>>Invalid</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="preferredLocation">Preferred Location:</label>
                    <textarea id="preferredLocation" name="preferredLocation" placeholder="Optional"><?= htmlspecialchars($application_data['preferredLocation']) ?></textarea>
                </div>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                <a href="view.php?id=<?= urlencode($application_data['application_id']) ?>" class="btn btn-secondary">👁️ View Application</a>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </form>
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

        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const cage = document.getElementById('cage').value;
            if (cage < 0 || cage > 7) {
                e.preventDefault();
                alert('Cage nights must be between 0 and 7.');
                return false;
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
