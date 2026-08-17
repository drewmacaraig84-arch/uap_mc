-- ====================================================================
-- UPGRADE SCRIPT — run this ONLY if you already imported schema.sql
-- before and have an existing database (so you don't lose your data).
-- If you are setting up a brand-new database, just import schema.sql
-- instead and skip this file entirely.
-- ====================================================================

-- 1. Add the new id_number column
ALTER TABLE users ADD COLUMN id_number VARCHAR(50) NULL AFTER name;

-- 2. Copy existing student_id values into id_number as a starting point
UPDATE users SET id_number = student_id WHERE student_id IS NOT NULL AND student_id != '';

-- 3. For the default admin account (and any user with no student_id), set a fallback ID
UPDATE users SET id_number = 'ADMIN001' WHERE id_number IS NULL AND role = 'admin';
UPDATE users SET id_number = CONCAT('TEMP', id) WHERE id_number IS NULL;

-- 4. Make id_number required and unique now that every row has a value
ALTER TABLE users MODIFY id_number VARCHAR(50) NOT NULL;
ALTER TABLE users ADD UNIQUE KEY unique_id_number (id_number);

-- 5. Drop the old email and student_id columns (id_number replaces both)
ALTER TABLE users DROP COLUMN email;
ALTER TABLE users DROP COLUMN student_id;

-- 6. Reset the default admin's ID number and password to the new defaults
--    (ID Number: ADMIN001 / password: admin123)
UPDATE users SET id_number = 'ADMIN001',
    password = '$2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS'
    WHERE role = 'admin' LIMIT 1;

-- 7. Create the qr_codes table for admin-uploaded payment QR images
CREATE TABLE IF NOT EXISTS qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method ENUM('gcash','maya','bank') NOT NULL UNIQUE,
    image_path VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
