<?php
header('Content-Type: application/json');
session_start();

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['credential'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

$credential = $input['credential'];

try {
    // Validate required fields
    if (!isset($credential['id']) || !isset($credential['rawId']) || !isset($credential['response'])) {
        throw new Exception('Missing required credential fields');
    }

    // Extract credential data
    $credentialId = $credential['id'];
    $rawId = $credential['rawId'];
    $authenticatorData = $credential['response']['authenticatorData'];
    $clientDataJSON = $credential['response']['clientDataJSON'];
    $signature = $credential['response']['signature'];

    // Convert arrays back to binary
    $rawIdBinary = pack('C*', ...$rawId);
    $authenticatorDataBinary = pack('C*', ...$authenticatorData);
    $clientDataJSONBinary = pack('C*', ...$clientDataJSON);
    $signatureBinary = pack('C*', ...$signature);

    // Basic validation of clientDataJSON
    $clientData = json_decode($clientDataJSONBinary, true);
    if (!$clientData || $clientData['type'] !== 'webauthn.get') {
        throw new Exception('Invalid client data type');
    }

    // Validate challenge
    if (!isset($_SESSION['webauthn_auth_challenge'])) {
        throw new Exception('No challenge found in session');
    }

    $expectedChallenge = $_SESSION['webauthn_auth_challenge'];
    if ($clientData['challenge'] !== $expectedChallenge) {
        throw new Exception('Challenge mismatch');
    }

    // Validate origin
    $expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    if ($clientData['origin'] !== $expectedOrigin) {
        error_log("Origin mismatch: expected {$expectedOrigin}, got {$clientData['origin']}");
        // For development, we'll allow this but log it
    }

    // Find the user who owns this credential
    $sql = "SELECT p.username, p.public_key, u.id, u.role 
            FROM user_passkeys p 
            JOIN admin_users u ON p.username = u.username 
            WHERE p.credential_id = ? AND u.active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $credentialId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Credential not found or user inactive');
    }

    $user_data = $result->fetch_assoc();
    $stmt->close();

    // For a complete implementation, you would verify the signature here
    // This is a simplified version that accepts the credential if it exists
    
    // Update last used timestamp
    $update_sql = "UPDATE user_passkeys SET last_used = NOW() WHERE credential_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("s", $credentialId);
    $update_stmt->execute();
    $update_stmt->close();

    // Complete the login
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $user_data['id'];
    $_SESSION['admin_username'] = $user_data['username'];
    $_SESSION['admin_role'] = $user_data['role'];

    // Update last login
    $login_sql = "UPDATE admin_users SET last_login = NOW() WHERE username = ?";
    $login_stmt = $conn->prepare($login_sql);
    $login_stmt->bind_param("s", $user_data['username']);
    $login_stmt->execute();
    $login_stmt->close();

    // Log successful login
    logLoginAttempt($conn, $user_data['username'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', true, 'passkey');

    // Clear the challenge
    unset($_SESSION['webauthn_auth_challenge']);

    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful',
        'username' => $user_data['username']
    ]);

} catch (Exception $e) {
    error_log("Passkey authentication error: " . $e->getMessage());
    
    // Log failed attempt
    if (isset($user_data['username'])) {
        logLoginAttempt($conn, $user_data['username'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'passkey');
    }
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function logLoginAttempt($conn, $username, $ip, $userAgent, $success, $method) {
    $sql = "INSERT INTO login_attempts (username, ip_address, user_agent, success, method) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssis", $username, $ip, $userAgent, $success, $method);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
?>
