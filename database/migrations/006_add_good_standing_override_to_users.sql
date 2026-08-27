-- Migration 006: Add good standing override and reason columns to users table

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'good_standing_override';
SET @query = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN good_standing_override ENUM(\'auto\', \'revoked\', \'granted\') NOT NULL DEFAULT \'auto\' AFTER status', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'good_standing_reason';
SET @query = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN good_standing_reason VARCHAR(255) NULL AFTER good_standing_override', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'good_standing_updated_at';
SET @query = IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN good_standing_updated_at TIMESTAMP NULL AFTER good_standing_reason', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
