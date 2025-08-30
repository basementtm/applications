-- Admin Panel Database Setup
-- Run this SQL script to create the admin_users table

-- Create admin_users table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NULL,
  `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(32) NULL,
  `passkey_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_by` int(11) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_active` (`active`),
  KEY `fk_created_by` (`created_by`),
  CONSTRAINT `fk_admin_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default super admin user
-- Password is 'admin123' - CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN!
INSERT INTO `admin_users` (`username`, `password`, `role`, `active`) 
VALUES ('admin', '$2y$10$YourNewHashHere', 'super_admin', 1)
ON DUPLICATE KEY UPDATE `password` = '$2y$10$YourNewHashHere';

-- Create user_passkeys table for WebAuthn credentials
CREATE TABLE IF NOT EXISTS `user_passkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `credential_id` varchar(255) NOT NULL UNIQUE,
  `public_key` text NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Passkey',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_credential_id` (`credential_id`),
  CONSTRAINT `fk_passkey_username` FOREIGN KEY (`username`) REFERENCES `admin_users` (`username`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create 2fa_backup_codes table
CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create login_attempts table for security logging
CREATE TABLE IF NOT EXISTS `login_attempts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add created_at column to applicants table if it doesn't exist
ALTER TABLE `applicants` 
ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add indexes for better performance (using separate statements for compatibility)
ALTER TABLE `applicants` ADD INDEX `idx_status` (`status`);
ALTER TABLE `applicants` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `applicants` ADD INDEX `idx_application_id` (`application_id`);

-- Optional: Create a view for application statistics
CREATE OR REPLACE VIEW `application_stats` AS
SELECT 
    COUNT(*) as total_applications,
    COUNT(CASE WHEN status = 'unreviewed' THEN 1 END) as unreviewed,
    COUNT(CASE WHEN status = 'stage2' THEN 1 END) as stage2,
    COUNT(CASE WHEN status = 'stage3' THEN 1 END) as stage3,
    COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
    COUNT(CASE WHEN status = 'denied' THEN 1 END) as denied,
    DATE(MIN(created_at)) as first_application,
    DATE(MAX(created_at)) as last_application
FROM applicants;
