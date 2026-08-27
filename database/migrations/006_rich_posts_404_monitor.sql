ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS body_html MEDIUMTEXT NULL AFTER body;

CREATE TABLE IF NOT EXISTS not_found_events (
    path VARCHAR(300) NOT NULL PRIMARY KEY,
    hit_count INT UNSIGNED NOT NULL DEFAULT 1,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    referrer_host VARCHAR(255) NULL,
    INDEX idx_not_found_last_seen (last_seen_at),
    INDEX idx_not_found_hits (hit_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name,label) VALUES
('sitehealth.manage','Manage Site Health monitor history');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name IN ('sitehealth.manage','mail.manage')
WHERE r.name='administrator';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name='super_administrator';
