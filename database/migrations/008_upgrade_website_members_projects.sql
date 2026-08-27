-- Migration 008: Add projects_json column to website_members table for structured Completed Works

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_members' AND COLUMN_NAME = 'projects_json';
SET @query = IF(@col_exists = 0, 'ALTER TABLE website_members ADD COLUMN projects_json LONGTEXT NULL AFTER gallery_json', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
