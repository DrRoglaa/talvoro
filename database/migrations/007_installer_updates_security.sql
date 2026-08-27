INSERT IGNORE INTO permissions (name,label) VALUES
('system.manage','Manage system updates and recovery');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name='system.manage'
WHERE r.name='super_administrator';

CREATE TABLE IF NOT EXISTS system_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_version VARCHAR(40) NOT NULL,
    to_version VARCHAR(40) NOT NULL,
    status ENUM('applying','completed','failed','restored') NOT NULL,
    backup_path VARCHAR(500) NULL,
    details_json JSON NULL,
    started_by BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    INDEX idx_system_updates_started (started_at),
    INDEX idx_system_updates_status (status),
    CONSTRAINT fk_system_updates_user FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
