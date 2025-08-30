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

try {
    // Generate a random challenge
    $challenge = random_bytes(32);
    
    // Store challenge in session for later verification
    $_SESSION['webauthn_auth_challenge'] = base64_encode($challenge);
    
    // Get all registered passkeys for authentication
    $sql = "SELECT DISTINCT username FROM user_passkeys";
    $result = $conn->query($sql);
    
    $allowCredentials = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get credentials for this user
            $cred_sql = "SELECT credential_id FROM user_passkeys WHERE username = ?";
            $cred_stmt = $conn->prepare($cred_sql);
            $cred_stmt->bind_param("s", $row['username']);
            $cred_stmt->execute();
            $cred_result = $cred_stmt->get_result();
            
            while ($cred_row = $cred_result->fetch_assoc()) {
                $allowCredentials[] = [
                    'id' => array_values(unpack('C*', base64_decode($cred_row['credential_id']))),
                    'type' => 'public-key'
                ];
            }
            $cred_stmt->close();
        }
    }
    
    // Create the credential request options
    $options = [
        'challenge' => array_values(unpack('C*', $challenge)),
        'timeout' => 60000,
        'userVerification' => 'preferred',
        'rpId' => $_SERVER['HTTP_HOST']
    ];
    
    if (!empty($allowCredentials)) {
        $options['allowCredentials'] = $allowCredentials;
    }
    
    echo json_encode($options);
    
} catch (Exception $e) {
    error_log("WebAuthn auth challenge error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate authentication challenge']);
}

$conn->close();
?>
