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

try {
    // Generate a random challenge
    $challenge = random_bytes(32);
    
    // Store challenge in session for later verification
    $_SESSION['webauthn_challenge'] = base64_encode($challenge);
    
    // Get the current origin
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $origin = $protocol . $host;
    
    // Create the credential creation options
    $options = [
        'challenge' => array_values(unpack('C*', $challenge)),
        'rp' => [
            'name' => 'Basement Admin',
            'id' => $_SERVER['HTTP_HOST']
        ],
        'user' => [
            'id' => array_values(unpack('C*', hash('sha256', $username, true))),
            'name' => $username,
            'displayName' => $username
        ],
        'pubKeyCredParams' => [
            ['alg' => -7, 'type' => 'public-key'], // ES256
            ['alg' => -257, 'type' => 'public-key'] // RS256
        ],
        'authenticatorSelection' => [
            'userVerification' => 'preferred',
            'residentKey' => 'preferred'
        ],
        'timeout' => 60000,
        'attestation' => 'none'
    ];
    
    // Get existing credentials to exclude
    $sql = "SELECT credential_id FROM user_passkeys WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $excludeCredentials = [];
    while ($row = $result->fetch_assoc()) {
        $excludeCredentials[] = [
            'id' => array_values(unpack('C*', base64_decode($row['credential_id']))),
            'type' => 'public-key'
        ];
    }
    $stmt->close();
    
    if (!empty($excludeCredentials)) {
        $options['excludeCredentials'] = $excludeCredentials;
    }
    
    echo json_encode($options);
    
} catch (Exception $e) {
    error_log("WebAuthn challenge error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate challenge']);
}

$conn->close();
?>
