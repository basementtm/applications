<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['admin_username'];
$message = '';
$message_type = '';

// Handle passkey deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $passkey_id = intval($_POST['passkey_id'] ?? 0);
    
    if ($passkey_id > 0) {
        $delete_sql = "DELETE FROM user_passkeys WHERE id = ? AND username = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("is", $passkey_id, $username);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            $message = "Passkey deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting passkey or passkey not found.";
            $message_type = "error";
        }
        $delete_stmt->close();
    }
}

// Handle passkey renaming
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $passkey_id = intval($_POST['passkey_id'] ?? 0);
    $new_name = trim($_POST['new_name'] ?? '');
    
    if ($passkey_id > 0 && !empty($new_name)) {
        $rename_sql = "UPDATE user_passkeys SET name = ? WHERE id = ? AND username = ?";
        $rename_stmt = $conn->prepare($rename_sql);
        $rename_stmt->bind_param("sis", $new_name, $passkey_id, $username);
        
        if ($rename_stmt->execute()) {
            $message = "Passkey renamed successfully!";
            $message_type = "success";
        } else {
            $message = "Error renaming passkey.";
            $message_type = "error";
        }
        $rename_stmt->close();
    }
}

// Fetch user's passkeys
$sql = "SELECT id, credential_id, name, created_at, last_used FROM user_passkeys WHERE username = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$passkeys = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Passkeys - Admin</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
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

        h1 {
            color: var(--primary-pink);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .passkey-list {
            margin-top: 30px;
        }

        .passkey-item {
            background-color: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .passkey-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-color);
        }

        .passkey-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .passkey-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-pink);
        }

        .passkey-actions {
            display: flex;
            gap: 10px;
        }

        .passkey-details {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px 20px;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .detail-label {
            font-weight: bold;
            color: var(--primary-pink);
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
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

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        .add-passkey {
            background-color: var(--input-bg);
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            border: 2px dashed var(--border-color);
            margin-bottom: 30px;
        }

        .add-passkey h3 {
            color: var(--primary-pink);
            margin-bottom: 15px;
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

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-color);
            opacity: 0.7;
        }

        .empty-state h3 {
            margin-bottom: 15px;
            color: var(--primary-pink);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--container-bg);
            margin: 15% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            color: var(--primary-pink);
            font-size: 1.3rem;
            font-weight: bold;
        }

        .close {
            color: var(--text-color);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary-pink);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--primary-pink);
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 5px rgba(255, 20, 147, 0.3);
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
            .passkey-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .passkey-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .passkey-details {
                grid-template-columns: 1fr;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-switcher" id="themeSwitcher" title="Toggle Dark Mode">🌙</div>
    
    <div class="container">
        <h1>🔑 Manage Passkeys</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="add-passkey">
            <h3>Add New Passkey</h3>
            <p>Register a new passkey for passwordless authentication</p>
            <button class="btn btn-primary" onclick="registerPasskey()">➕ Register New Passkey</button>
        </div>

        <div class="passkey-list">
            <h2 style="color: var(--primary-pink); margin-bottom: 20px;">Your Passkeys</h2>
            
            <?php if (empty($passkeys)): ?>
                <div class="empty-state">
                    <h3>No Passkeys Registered</h3>
                    <p>You haven't registered any passkeys yet. Add your first passkey above to enable passwordless authentication.</p>
                </div>
            <?php else: ?>
                <?php foreach ($passkeys as $passkey): ?>
                    <div class="passkey-item">
                        <div class="passkey-header">
                            <div class="passkey-name"><?= htmlspecialchars($passkey['name']) ?></div>
                            <div class="passkey-actions">
                                <button class="btn btn-secondary btn-sm" onclick="openRenameModal(<?= $passkey['id'] ?>, '<?= htmlspecialchars($passkey['name']) ?>')">✏️ Rename</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this passkey?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="passkey_id" value="<?= $passkey['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                        <div class="passkey-details">
                            <span class="detail-label">Created:</span>
                            <span><?= date('F j, Y g:i A', strtotime($passkey['created_at'])) ?></span>
                            <span class="detail-label">Last Used:</span>
                            <span><?= $passkey['last_used'] ? date('F j, Y g:i A', strtotime($passkey['last_used'])) : 'Never' ?></span>
                            <span class="detail-label">Credential ID:</span>
                            <span style="font-family: monospace; word-break: break-all;"><?= substr(htmlspecialchars($passkey['credential_id']), 0, 32) ?>...</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="settings.php" class="btn btn-secondary">← Back to Settings</a>
        </div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Rename Passkey</div>
                <span class="close" onclick="closeRenameModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="passkey_id" id="renamePasskeyId">
                <div class="form-group">
                    <label for="new_name">New Name:</label>
                    <input type="text" id="new_name" name="new_name" required maxlength="100">
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeRenameModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save</button>
                </div>
            </form>
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

        // Modal functions
        function openRenameModal(passkeyId, currentName) {
            document.getElementById('renamePasskeyId').value = passkeyId;
            document.getElementById('new_name').value = currentName;
            document.getElementById('renameModal').style.display = 'block';
            document.getElementById('new_name').focus();
        }

        function closeRenameModal() {
            document.getElementById('renameModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('renameModal');
            if (event.target == modal) {
                closeRenameModal();
            }
        }

        // WebAuthn Passkey Registration
        async function registerPasskey() {
            if (!window.PublicKeyCredential) {
                alert('WebAuthn is not supported in this browser.');
                return;
            }

            try {
                // Generate random challenge
                const challenge = new Uint8Array(32);
                crypto.getRandomValues(challenge);

                // Create credential options
                const publicKeyCredentialCreationOptions = {
                    challenge: challenge,
                    rp: {
                        name: "Basement Admin",
                        id: window.location.hostname,
                    },
                    user: {
                        id: new TextEncoder().encode('<?= htmlspecialchars($username) ?>'),
                        name: '<?= htmlspecialchars($username) ?>',
                        displayName: '<?= htmlspecialchars($username) ?>',
                    },
                    pubKeyCredParams: [
                        {alg: -7, type: "public-key"},  // ES256
                        {alg: -257, type: "public-key"} // RS256
                    ],
                    authenticatorSelection: {
                        userVerification: "preferred"
                    },
                    timeout: 60000,
                    attestation: "direct"
                };

                // Create the credential
                const credential = await navigator.credentials.create({
                    publicKey: publicKeyCredentialCreationOptions
                });

                // Send to server for storage
                const response = await fetch('register-passkey.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        credential: {
                            id: credential.id,
                            rawId: Array.from(new Uint8Array(credential.rawId)),
                            type: credential.type,
                            response: {
                                attestationObject: Array.from(new Uint8Array(credential.response.attestationObject)),
                                clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON))
                            }
                        }
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    alert('Passkey registered successfully!');
                    location.reload();
                } else {
                    alert('Failed to register passkey: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error registering passkey:', error);
                if (error.name === 'NotAllowedError') {
                    alert('Passkey registration was cancelled or not allowed.');
                } else {
                    alert('Error registering passkey: ' + error.message);
                }
            }
        }
    </script>
</body>
</html>
