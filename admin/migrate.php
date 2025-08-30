<?php
// Database migration script for existing installations
// Run this to update the database structure for 2FA and passkey support

include('/var/www/config/db_config.php');
$conn = new mysqli($DB_SERVER, $DB_USER, $DB_PASSWORD, $DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>🔧 Database Migration for 2FA/Passkey Support</h2>\n";

// Add new columns to admin_users table if they don't exist
$migrations = [
    "ALTER TABLE `admin_users` ADD COLUMN `email` varchar(255) NULL AFTER `password`",
    "ALTER TABLE `admin_users` ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `active`",
    "ALTER TABLE `admin_users` ADD COLUMN `two_factor_secret` varchar(255) NULL AFTER `two_factor_enabled`",
    "ALTER TABLE `admin_users` ADD COLUMN `passkey_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `two_factor_secret`"
];

foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        echo "✅ " . $sql . "<br>\n";
    } else {
        if (strpos($conn->error, "Duplicate column name") !== false) {
            echo "⚠️ Column already exists: " . $sql . "<br>\n";
        } else {
            echo "❌ Error: " . $conn->error . " - " . $sql . "<br>\n";
        }
    }
}

// Create new tables
$tables = [
    "CREATE TABLE IF NOT EXISTS `user_passkeys` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `credential_id` text NOT NULL,
      `public_key` longtext NOT NULL,
      `name` varchar(100) NOT NULL DEFAULT 'Passkey',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_used` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_username` (`username`),
      CONSTRAINT `fk_passkey_username` FOREIGN KEY (`username`) REFERENCES `admin_users` (`username`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `code` varchar(16) NOT NULL,
      `used` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `used_at` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_username` (`username`),
      KEY `idx_code` (`code`),
      CONSTRAINT `fk_backup_code_username` FOREIGN KEY (`username`) REFERENCES `admin_users` (`username`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS `login_attempts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NULL,
      `ip_address` varchar(45) NOT NULL,
      `user_agent` text NULL,
      `success` tinyint(1) NOT NULL DEFAULT 0,
      `method` enum('password','passkey','2fa') NOT NULL DEFAULT 'password',
      `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_username` (`username`),
      KEY `idx_ip_address` (`ip_address`),
      KEY `idx_attempt_time` (`attempt_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $sql) {
    if ($conn->query($sql)) {
        echo "✅ Table created successfully<br>\n";
    } else {
        echo "❌ Error creating table: " . $conn->error . "<br>\n";
    }
}

echo "<h3>🎉 Migration completed!</h3>\n";
echo "<p><a href='settings.php'>Go to Settings</a> to configure 2FA and passkeys.</p>\n";

$conn->close();
?>
