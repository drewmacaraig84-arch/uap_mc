-- Migration 007: Add company name and clickable link fields to website_members table

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_members' AND COLUMN_NAME = 'company_name';
SET @query = IF(@col_exists = 0, 'ALTER TABLE website_members ADD COLUMN company_name VARCHAR(255) NULL AFTER location', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_members' AND COLUMN_NAME = 'link_url';
SET @query = IF(@col_exists = 0, 'ALTER TABLE website_members ADD COLUMN link_url VARCHAR(500) NULL AFTER company_name', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_members' AND COLUMN_NAME = 'link_type';
SET @query = IF(@col_exists = 0, 'ALTER TABLE website_members ADD COLUMN link_type VARCHAR(50) NULL DEFAULT \'auto\' AFTER link_url', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
