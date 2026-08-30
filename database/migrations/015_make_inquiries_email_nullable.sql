-- 015: Make email column nullable in contact_inquiries table to allow submitting with phone number
ALTER TABLE contact_inquiries MODIFY COLUMN email VARCHAR(150) NULL;
