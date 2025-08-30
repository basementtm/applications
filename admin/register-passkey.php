<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$username = $_SESSION['admin_username'];

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
    $attestationObject = $credential['response']['attestationObject'];
    $clientDataJSON = $credential['response']['clientDataJSON'];

    // Convert arrays back to binary
    $rawIdBinary = pack('C*', ...$rawId);
    $attestationObjectBinary = pack('C*', ...$attestationObject);
    $clientDataJSONBinary = pack('C*', ...$clientDataJSON);

    // Basic validation of clientDataJSON
    $clientData = json_decode($clientDataJSONBinary, true);
    if (!$clientData || $clientData['type'] !== 'webauthn.create') {
        throw new Exception('Invalid client data');
    }

    // For production, you should perform full WebAuthn validation
    // This is a simplified version for demonstration
    
    // Generate a name for the passkey
    $passkeyName = 'Passkey ' . date('M j, Y g:i A');
    
    // Store the credential
    $publicKey = base64_encode($attestationObjectBinary); // Simplified storage
    
    $sql = "INSERT INTO user_passkeys (username, credential_id, public_key, name) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $credentialId, $publicKey, $passkeyName);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Passkey registered successfully',
            'name' => $passkeyName
        ]);
    } else {
        throw new Exception('Failed to store credential');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
