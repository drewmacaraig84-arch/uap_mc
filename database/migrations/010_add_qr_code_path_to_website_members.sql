-- Migration 010: Add qr_code_path column to website_members table
ALTER TABLE `website_members` 
ADD COLUMN `qr_code_path` VARCHAR(255) NULL AFTER `projects_json`;
