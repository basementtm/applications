# 🔐 2FA & Passkey Setup Guide

## Quick Fix Steps

### 1. Run Database Migration
First, update your database structure:
```
http://yourdomain.com/admin/migrate.php
```

### 2. Test System
Check if everything is working:
```
http://yourdomain.com/admin/test-auth.html
```

### 3. Diagnostic Check
If issues persist, run diagnostics:
```
http://yourdomain.com/admin/diagnostic.php
```

## Common Issues & Solutions

### 🔴 "2FA doesn't work"
**Likely causes:**
- Database columns missing (run migrate.php)
- QR code library not loading
- TOTP secret generation failing

**Quick fix:**
1. Check if `two_factor_secret` column exists in `admin_users` table
2. Ensure QRCode.js loads (check browser console)
3. Verify secret is stored in database after enabling 2FA

### 🔴 "Passkeys don't work"
**Likely causes:**
- Not using HTTPS (except localhost)
- Browser doesn't support WebAuthn
- Challenge endpoint missing

**Quick fix:**
1. Use HTTPS or localhost
2. Test with Chrome/Firefox/Safari/Edge
3. Check if `get-passkey-challenge.php` exists
4. Verify browser console for JavaScript errors

### 🔴 "Database errors"
**Likely causes:**
- Missing tables or columns
- Wrong field sizes
- Connection issues

**Quick fix:**
1. Run migrate.php to create/update tables
2. Check database credentials in config
3. Ensure MySQL/MariaDB is running

## Testing Workflow

### Step 1: Enable 2FA
1. Go to Settings → Security
2. Toggle "Enable Two-Factor Authentication"
3. Scan QR code with authenticator app
4. Enter verification code
5. Save backup codes

### Step 2: Add Passkey
1. In Settings → Passkeys section
2. Click "Add New Passkey"
3. Follow browser prompts
4. Verify passkey appears in list

### Step 3: Test Login
1. Logout
2. Login with username/password
3. Enter 2FA code when prompted
4. Or use passkey option

## Debug Information

### Browser Requirements
- **2FA:** Any modern browser
- **Passkeys:** Chrome 67+, Firefox 60+, Safari 14+, Edge 85+

### Server Requirements
- **2FA:** PHP 7.0+, MySQL 5.6+
- **Passkeys:** HTTPS (except localhost), PHP 7.0+

### File Checklist
- ✅ `admin/settings.php` - Main settings interface
- ✅ `admin/setup-2fa.php` - 2FA configuration
- ✅ `admin/register-passkey.php` - Passkey registration
- ✅ `admin/get-passkey-challenge.php` - WebAuthn challenge
- ✅ `admin/migrate.php` - Database migration
- ✅ `admin/diagnostic.php` - System diagnostics
- ✅ `admin/test-auth.html` - Testing interface

## Manual Database Updates

If migrate.php doesn't work, run these SQL commands manually:

```sql
-- Add columns to admin_users
ALTER TABLE `admin_users` ADD COLUMN `email` varchar(255) NULL;
ALTER TABLE `admin_users` ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `admin_users` ADD COLUMN `two_factor_secret` varchar(255) NULL;
ALTER TABLE `admin_users` ADD COLUMN `passkey_enabled` tinyint(1) NOT NULL DEFAULT 0;

-- Create passkeys table
CREATE TABLE `user_passkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `credential_id` text NOT NULL,
  `public_key` longtext NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Passkey',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`)
);

-- Create backup codes table
CREATE TABLE `two_factor_backup_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `code` varchar(16) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_code` (`code`)
);

-- Create login attempts table
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `method` enum('password','passkey','2fa') NOT NULL DEFAULT 'password',
  `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_ip_address` (`ip_address`)
);
```

## Security Notes

⚠️ **Important:**
- Backup codes are single-use only
- Passkeys require user interaction
- 2FA secrets should be kept secure
- Always use HTTPS in production
- Test on actual devices, not just desktop

✅ **Success indicators:**
- QR codes display properly
- Authenticator apps generate codes
- Passkey registration prompts appear
- Database tables populate correctly
- Login flow works end-to-end
