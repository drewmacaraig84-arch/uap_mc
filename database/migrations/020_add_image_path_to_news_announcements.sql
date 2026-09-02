-- Migration 020: Add image_path column to news_announcements table
ALTER TABLE news_announcements
ADD COLUMN image_path VARCHAR(255) NULL AFTER summary;
