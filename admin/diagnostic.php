<?php
// Quick diagnostic script to check 2FA and passkey functionality
session_start();

// Mock session for testing
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'test_user';

echo "<h2>🔍 Diagnostic Test for 2FA/Passkey Implementation</h2>\n";

// Test database connection
echo "<h3>1. Database Connection Test</h3>\n";
try {
    include('/var/www/config/db_config.php');
    $conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);
    
    if ($conn->connect_error) {
        echo "❌ Database connection failed: " . $conn->connect_error . "<br>\n";
    } else {
        echo "✅ Database connection successful<br>\n";
        
        // Test table existence
        echo "<h3>2. Table Structure Test</h3>\n";
        $tables = ['admin_users', 'user_passkeys', 'two_factor_backup_codes', 'login_attempts'];
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "✅ Table '$table' exists<br>\n";
                
                // Check columns for admin_users
                if ($table === 'admin_users') {
                    $columns = $conn->query("DESCRIBE $table");
                    $found_columns = [];
                    while ($col = $columns->fetch_assoc()) {
                        $found_columns[] = $col['Field'];
                    }
                    
                    $required_columns = ['email', 'two_factor_enabled', 'two_factor_secret', 'passkey_enabled'];
                    foreach ($required_columns as $req_col) {
                        if (in_array($req_col, $found_columns)) {
                            echo "  ✅ Column '$req_col' exists<br>\n";
                        } else {
                            echo "  ❌ Column '$req_col' missing<br>\n";
                        }
                    }
                }
            } else {
                echo "❌ Table '$table' does not exist<br>\n";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>\n";
}

// Test 2FA functions
echo "<h3>3. 2FA Function Test</h3>\n";

function generateRandomSecret($length = 20) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

function base32_decode($data) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $v <<= 5;
        $v += strpos($chars, $data[$i]);
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr(($v >> ($vbits - 8)) & 255);
            $vbits -= 8;
        }
    }
    
    return $output;
}

function verifyTOTP($secret, $code, $window = 1) {
    $time = floor(time() / 30);
    
    for ($i = -$window; $i <= $window; $i++) {
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $time + $i), base32_decode($secret), true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        if (sprintf('%06d', $otp) === $code) {
            return true;
        }
    }
    return false;
}

try {
    $test_secret = generateRandomSecret();
    echo "✅ Secret generation: " . htmlspecialchars($test_secret) . "<br>\n";
    
    $decoded = base32_decode($test_secret);
    echo "✅ Base32 decode: " . (strlen($decoded) > 0 ? "Success" : "Failed") . "<br>\n";
    
    // Generate current TOTP for testing
    $time = floor(time() / 30);
    $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $time), $decoded, true);
    $offset = ord($hash[19]) & 0xf;
    $otp = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    $current_code = sprintf('%06d', $otp);
    
    echo "✅ Current TOTP: " . $current_code . "<br>\n";
    
    $verify_result = verifyTOTP($test_secret, $current_code);
    echo "✅ TOTP Verification: " . ($verify_result ? "Success" : "Failed") . "<br>\n";
    
} catch (Exception $e) {
    echo "❌ 2FA Function Error: " . $e->getMessage() . "<br>\n";
}

// Test JavaScript/WebAuthn availability
echo "<h3>4. Browser Compatibility Test</h3>\n";
echo '<script>
console.log("Testing browser compatibility...");

// Test WebAuthn support
if (window.PublicKeyCredential) {
    document.write("✅ WebAuthn is supported<br>");
    
    // Test if platform authenticator is available
    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
        .then(available => {
            if (available) {
                document.write("✅ Platform authenticator available (Face ID, Touch ID, Windows Hello)<br>");
            } else {
                document.write("⚠️ Platform authenticator not available<br>");
            }
        })
        .catch(err => {
            document.write("❌ Error checking platform authenticator: " + err.message + "<br>");
        });
} else {
    document.write("❌ WebAuthn is not supported in this browser<br>");
}

// Test if page is served over HTTPS (required for WebAuthn)
if (location.protocol === "https:" || location.hostname === "localhost") {
    document.write("✅ Secure context (HTTPS or localhost)<br>");
} else {
    document.write("❌ Not a secure context - WebAuthn requires HTTPS<br>");
}

// Test QR Code library
if (typeof QRCode !== "undefined") {
    document.write("✅ QRCode library loaded<br>");
} else {
    document.write("❌ QRCode library not loaded<br>");
}
</script>';

echo "<h3>5. Common Issues & Solutions</h3>\n";
echo "<ul>\n";
echo "<li><strong>2FA not working:</strong> Check if secret is generated and stored correctly</li>\n";
echo "<li><strong>Passkeys not working:</strong> Ensure HTTPS and proper origin validation</li>\n";
echo "<li><strong>Database errors:</strong> Run migrate.php to create missing tables/columns</li>\n";
echo "<li><strong>QR codes not showing:</strong> Check if QRCode.js library is loading</li>\n";
echo "</ul>\n";

if (isset($conn)) {
    $conn->close();
}
?>
