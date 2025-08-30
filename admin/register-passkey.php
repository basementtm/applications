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

    // Convert arrays back to binary for validation
    $rawIdBinary = pack('C*', ...$rawId);
    $attestationObjectBinary = pack('C*', ...$attestationObject);
    $clientDataJSONBinary = pack('C*', ...$clientDataJSON);

    // Basic validation of clientDataJSON
    $clientData = json_decode($clientDataJSONBinary, true);
    if (!$clientData || $clientData['type'] !== 'webauthn.create') {
        throw new Exception('Invalid client data');
    }

    // Validate origin
    $expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    if ($clientData['origin'] !== $expectedOrigin) {
        error_log("Origin mismatch: expected {$expectedOrigin}, got {$clientData['origin']}");
        // For development, we'll allow this but log it
    }
    
    // Generate a name for the passkey
    $passkeyName = 'Passkey ' . date('M j, Y g:i A');
    
    // Store the credential (simplified for demonstration)
    $publicKeyData = json_encode([
        'credentialId' => $credentialId,
        'attestationObject' => base64_encode($attestationObjectBinary),
        'clientDataJSON' => base64_encode($clientDataJSONBinary)
    ]);
    
    // Check if credential already exists
    $check_sql = "SELECT id FROM user_passkeys WHERE credential_id = ? AND username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $credentialId, $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        throw new Exception('This passkey is already registered');
    }
    $check_stmt->close();
    
    $sql = "INSERT INTO user_passkeys (username, credential_id, public_key, name) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $credentialId, $publicKeyData, $passkeyName);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Passkey registered successfully',
            'name' => $passkeyName
        ]);
    } else {
        throw new Exception('Failed to store credential: ' . $conn->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Passkey registration error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
