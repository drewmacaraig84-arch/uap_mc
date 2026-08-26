-- 005_add_profile_photo_to_users.sql
-- Add profile_photo column to users table for Admin and Member profile avatars

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL AFTER status;
