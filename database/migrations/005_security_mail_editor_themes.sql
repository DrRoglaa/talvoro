ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password,
    ADD COLUMN mfa_secret_encrypted TEXT NULL AFTER mfa_enabled,
    ADD COLUMN mfa_pending_secret_encrypted TEXT NULL AFTER mfa_secret_encrypted,
    ADD COLUMN mfa_recovery_hashes JSON NULL AFTER mfa_pending_secret_encrypted;

ALTER TABLE user_sessions
    ADD COLUMN mfa_verified_at DATETIME NULL AFTER last_seen_at;

ALTER TABLE pages
    ADD COLUMN eyebrow VARCHAR(120) NULL AFTER excerpt,
    ADD COLUMN body_html MEDIUMTEXT NULL AFTER body;

CREATE TABLE mail_delivery_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mail_type VARCHAR(80) NOT NULL,
    recipient VARCHAR(320) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    delivery_status ENUM('sent','failed') NOT NULL,
    delivery_error VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_mail_delivery_created (created_at),
    INDEX idx_mail_delivery_recipient (recipient(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    version VARCHAR(40) NOT NULL DEFAULT '1.0.0',
    author VARCHAR(120) NULL,
    description VARCHAR(500) NULL,
    css_text MEDIUMTEXT NOT NULL,
    is_builtin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_themes_active (is_active),
    CONSTRAINT fk_themes_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO themes
(name,slug,version,author,description,css_text,is_builtin,is_active,created_at,updated_at)
VALUES
('Trenlume Light','trenlume-light','1.0.0','Talvoro','Warm ivory, coral accents and soft glass surfaces.','',1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP());

INSERT INTO cms_settings (setting_key,setting_value,updated_at) VALUES
('blog.enabled','1',UTC_TIMESTAMP()),
('mail.enabled','0',UTC_TIMESTAMP()),
('mail.smtp_host','',UTC_TIMESTAMP()),
('mail.smtp_port','587',UTC_TIMESTAMP()),
('mail.smtp_encryption','starttls',UTC_TIMESTAMP()),
('mail.smtp_username','',UTC_TIMESTAMP()),
('mail.smtp_password_enc','',UTC_TIMESTAMP()),
('mail.from_email','',UTC_TIMESTAMP()),
('mail.from_name','Talvoro',UTC_TIMESTAMP()),
('mail.envelope_from','',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

INSERT IGNORE INTO permissions (name,label) VALUES
('mail.manage','Manage email delivery'),
('audit.purge','Purge audit logs'),
('blog.manage','Manage blog availability'),
('themes.import','Import frontend themes');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name='super_administrator';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name='blog.manage'
WHERE r.name='administrator';
