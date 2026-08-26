CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    id_number VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('member','admin') NOT NULL DEFAULT 'member',
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    profile_photo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE,
    term VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS member_dues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    due_id INT NOT NULL,
    status ENUM('unpaid','pending','partial','paid','rejected') NOT NULL DEFAULT 'unpaid',
    payment_type ENUM('full','first_half','second_half','partial') DEFAULT NULL,
    installment_months INT DEFAULT NULL,
    total_paid DECIMAL(10,2) DEFAULT 0.00,
    custom_title VARCHAR(150) DEFAULT NULL,
    custom_description VARCHAR(255) DEFAULT NULL,
    custom_amount DECIMAL(10,2) DEFAULT NULL,
    custom_due_date DATE DEFAULT NULL,
    custom_term VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (due_id) REFERENCES dues(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_due (user_id, due_id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_due_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_stage ENUM('full','first_half','second_half','partial') DEFAULT NULL,
    method ENUM('gcash','maya','card','bank') NOT NULL,
    reference_number VARCHAR(100),
    proof_image VARCHAR(255),
    installment_number INT DEFAULT NULL,
    status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    remarks VARCHAR(255),
    FOREIGN KEY (member_due_id) REFERENCES member_dues(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(30) NOT NULL UNIQUE,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method ENUM('gcash','maya','bank') NOT NULL UNIQUE,
    image_path VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255)
);

INSERT IGNORE INTO site_settings (setting_key, setting_value)
VALUES ('logo', 'uploads/uap_logo.jpg');
