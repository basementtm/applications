<?php
// Generate correct password hash for admin123
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h1>🔐 Password Hash Generator</h1>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<p><strong>Generated Hash:</strong></p>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block; margin: 10px 0; word-break: break-all;'>$hash</code>";

echo "<h2>📝 SQL Update Command:</h2>";
echo "<p>Run this SQL command to update the admin password:</p>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;'>";
echo "UPDATE admin_users SET password = '$hash' WHERE username = 'admin';";
echo "</code>";

echo "<h2>🔧 Alternative: Manual Database Update</h2>";
echo "<p>If you can access your database directly, run the SQL command above.</p>";

echo "<h2>🆕 Or: Create New Admin User</h2>";
echo "<p>SQL to insert/replace the admin user:</p>";
echo "<code style='background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;'>";
echo "INSERT INTO admin_users (username, password, role, active) VALUES ('admin', '$hash', 'super_admin', 1) ON DUPLICATE KEY UPDATE password = '$hash';";
echo "</code>";

// Test the hash
if (password_verify('admin123', $hash)) {
    echo "<p style='color: green;'>✅ Hash verification test: PASSED</p>";
} else {
    echo "<p style='color: red;'>❌ Hash verification test: FAILED</p>";
}

echo "<br><a href='login.php'>← Back to Login</a>";
?>