CREATE TABLE contact_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id BIGINT UNSIGNED NULL,
    form_owner_id VARCHAR(32) NOT NULL,
    block_id VARCHAR(32) NOT NULL,
    source_label VARCHAR(255) NOT NULL,
    source_path VARCHAR(191) NOT NULL,
    sender_name VARCHAR(120) NOT NULL,
    sender_email VARCHAR(254) NOT NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    status ENUM('new','read') NOT NULL DEFAULT 'new',
    delivery_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_contact_status_created (status,created_at),
    INDEX idx_contact_delivery_created (delivery_status,created_at),
    INDEX idx_contact_created (created_at),
    INDEX idx_contact_page (page_id),
    CONSTRAINT fk_contact_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_settings (setting_key,setting_value,updated_at) VALUES
('contact.default_recipient','',UTC_TIMESTAMP()),
('contact.subject_prefix','Website contact',UTC_TIMESTAMP()),
('contact.store_submissions','0',UTC_TIMESTAMP()),
('contact.retention_days','30',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

INSERT IGNORE INTO permissions (name,label) VALUES
('contact.view','View contact submissions'),
('contact.manage','Manage contact forms and submissions');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name IN ('contact.view','contact.manage')
WHERE r.name='super_administrator';
