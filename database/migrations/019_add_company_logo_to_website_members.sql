-- Migration 019: Add company_logo_path column to website_members table
ALTER TABLE website_members 
ADD COLUMN company_logo_path VARCHAR(255) NULL AFTER company_name;
