INSERT IGNORE INTO users (name, id_number, password, role, status)
VALUES ('Admin', 'ADMIN001', '$2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS', 'admin', 'approved');

INSERT IGNORE INTO site_settings (setting_key, setting_value)
VALUES ('logo', 'uploads/uap_logo.jpg');
