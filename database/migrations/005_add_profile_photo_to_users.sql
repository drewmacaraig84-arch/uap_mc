-- 005_add_profile_photo_to_users.sql
-- Add profile_photo column to users table for Admin and Member profile avatars

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'profile_photo';

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status', 
    'SELECT 1');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
