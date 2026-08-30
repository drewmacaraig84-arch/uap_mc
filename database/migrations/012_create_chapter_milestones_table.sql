-- 012: Create chapter_milestones table
CREATE TABLE IF NOT EXISTS chapter_milestones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    year        VARCHAR(10)  NOT NULL,
    title       VARCHAR(255) NOT NULL,
    content     TEXT         NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default milestones from the original hardcoded list
INSERT IGNORE INTO chapter_milestones (year, title, content, sort_order) VALUES
('2016', 'Chapter Founded',           'UAP Mindoro Chapter established as IAPOA Chapter 121, bringing together registered architects across the Mindoro provinces.', 1),
('2018', 'Growing Membership',        'Membership expanded significantly with architects from Calapan City, Puerto Galera, and Occidental Mindoro joining the chapter.', 2),
('2020', 'Digital Transformation',    'Adopted digital systems for member management, dues processing, and chapter communications.', 3),
('2023', 'New Leadership',            'A new Board of Directors was elected, bringing fresh perspectives and initiatives for chapter growth.', 4),
('2024', 'Online Architect Directory','Launched the public Architect Directory to connect clients with verified UAP Mindoro architects.', 5);
