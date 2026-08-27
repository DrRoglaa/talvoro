INSERT IGNORE INTO roles (name,label) VALUES
('super_administrator','Super Administrator');

INSERT IGNORE INTO permissions (name,label) VALUES
('pages.view','View pages'),
('pages.edit','Edit pages'),
('pages.publish','Publish pages'),
('themes.manage','Manage frontend theme'),
('users.security','Manage per-user security'),
('users.delete','Delete users');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name='super_administrator';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p
  ON p.name IN (
    'pages.view',
    'pages.edit',
    'pages.publish',
    'themes.manage',
    'users.security'
  )
WHERE r.name='administrator';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p
  ON p.name IN ('pages.view','pages.edit','pages.publish','users.security')
WHERE r.name='editor';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p
  ON p.name='users.security'
WHERE r.name='analyst';

CREATE TABLE pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    path VARCHAR(191) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    show_in_navigation TINYINT(1) NOT NULL DEFAULT 0,
    navigation_label VARCHAR(120) NULL,
    navigation_order INT NOT NULL DEFAULT 100,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_pages_status (status),
    INDEX idx_pages_navigation (show_in_navigation,navigation_order),
    INDEX idx_pages_author (author_id),
    CONSTRAINT fk_pages_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_hash CHAR(64) NOT NULL UNIQUE,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    INDEX idx_user_sessions_user (user_id,last_seen_at),
    INDEX idx_user_sessions_revoked (revoked_at),
    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_settings (setting_key,setting_value,updated_at)
VALUES ('frontend.theme','trenlume',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

UPDATE users u
JOIN roles old_role ON old_role.id=u.role_id AND old_role.name='administrator'
JOIN roles super_role ON super_role.name='super_administrator'
JOIN (
    SELECT candidate.id
    FROM (
        SELECT u2.id
        FROM users u2
        JOIN roles r2 ON r2.id=u2.role_id
        WHERE r2.name='administrator'
          AND u2.status='active'
        ORDER BY u2.id ASC
        LIMIT 1
    ) candidate
) first_admin ON first_admin.id=u.id
SET u.role_id=super_role.id,
    u.updated_at=UTC_TIMESTAMP();
