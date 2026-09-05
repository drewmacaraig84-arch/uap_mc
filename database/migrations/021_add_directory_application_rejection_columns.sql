ALTER TABLE directory_applications
    ADD COLUMN reapply_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER notes,
    ADD COLUMN reapply_after DATE NULL AFTER reapply_allowed,
    ADD COLUMN dismissed_notification TINYINT(1) NOT NULL DEFAULT 0 AFTER reapply_after,
    ADD COLUMN rejected_at TIMESTAMP NULL AFTER dismissed_notification;
