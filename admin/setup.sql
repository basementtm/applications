-- Admin Panel Database Setup
-- Run this SQL script to create the admin_users table

-- Create admin_users table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `active` tinyint(1) NOT NULL DEFAULT 1,
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
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1)
ON DUPLICATE KEY UPDATE `username` = `username`;

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
