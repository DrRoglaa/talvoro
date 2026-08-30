CREATE TABLE theme_starter_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_id BIGINT UNSIGNED NOT NULL,
    schema_version SMALLINT UNSIGNED NOT NULL,
    starter_version VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NULL,
    manifest_json MEDIUMTEXT NOT NULL,
    manifest_sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_theme_starter_definition_theme (theme_id),
    INDEX idx_theme_starter_definition_hash (manifest_sha256),
    CONSTRAINT fk_theme_starter_definition_theme FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE starter_site_installations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_id BIGINT UNSIGNED NOT NULL,
    definition_id BIGINT UNSIGNED NULL,
    starter_version VARCHAR(40) NOT NULL,
    manifest_sha256 CHAR(64) NOT NULL,
    installation_token CHAR(32) NOT NULL,
    status ENUM('installed','removed') NOT NULL DEFAULT 'installed',
    installed_by BIGINT UNSIGNED NULL,
    installed_at DATETIME NOT NULL,
    removed_by BIGINT UNSIGNED NULL,
    removed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_starter_site_installation_token (installation_token),
    INDEX idx_starter_site_installation_theme_status (theme_id,status),
    CONSTRAINT fk_starter_site_installation_theme FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE,
    CONSTRAINT fk_starter_site_installation_definition FOREIGN KEY (definition_id) REFERENCES theme_starter_definitions(id) ON DELETE SET NULL,
    CONSTRAINT fk_starter_site_installation_installed_by FOREIGN KEY (installed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_starter_site_installation_removed_by FOREIGN KEY (removed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE starter_site_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installation_id BIGINT UNSIGNED NOT NULL,
    resource_key VARCHAR(160) NOT NULL,
    resource_type VARCHAR(40) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    record_locator VARCHAR(255) NULL,
    ownership_mode ENUM('created','mutated') NOT NULL DEFAULT 'created',
    definition_sha256 CHAR(64) NOT NULL,
    baseline_sha256 CHAR(64) NULL,
    previous_state_json MEDIUMTEXT NULL,
    state ENUM('owned','detached','removed') NOT NULL DEFAULT 'owned',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_starter_site_resource_key (installation_id,resource_key),
    INDEX idx_starter_site_resource_record (resource_type,record_id),
    INDEX idx_starter_site_resource_state (installation_id,state),
    CONSTRAINT fk_starter_site_resource_installation FOREIGN KEY (installation_id) REFERENCES starter_site_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name,label) VALUES
('starter_sites.manage','Manage theme starter sites');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name='starter_sites.manage'
WHERE r.name='super_administrator';
