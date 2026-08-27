CREATE TABLE IF NOT EXISTS menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    menu_key VARCHAR(100) NOT NULL UNIQUE,
    location ENUM('primary','footer','mobile','unassigned') NOT NULL DEFAULT 'unassigned',
    description VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_menu_location (location),
    CONSTRAINT fk_menus_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL,
    target_type ENUM('custom','page','post','content_entry') NOT NULL DEFAULT 'custom',
    target_id BIGINT UNSIGNED NULL,
    target_model_id BIGINT UNSIGNED NULL,
    custom_url VARCHAR(1000) NULL,
    open_new_tab TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_menu_items_tree (menu_id,parent_id,sort_order,id),
    INDEX idx_menu_items_target (target_type,target_id),
    CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_items_parent FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_items_model FOREIGN KEY (target_model_id) REFERENCES content_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE media_assets
    ADD COLUMN folder_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN title VARCHAR(255) NULL AFTER original_name,
    ADD COLUMN caption VARCHAR(500) NULL AFTER alt_text,
    ADD COLUMN focal_x DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER height,
    ADD COLUMN focal_y DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER focal_x,
    ADD COLUMN replaced_at DATETIME NULL AFTER updated_at,
    ADD INDEX idx_media_folder_created (folder_id,created_at);

CREATE TABLE IF NOT EXISTS media_folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    folder_key VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_media_folder_sibling (parent_id,folder_key),
    INDEX idx_media_folder_parent (parent_id,sort_order,name),
    CONSTRAINT fk_media_folder_parent FOREIGN KEY (parent_id) REFERENCES media_folders(id) ON DELETE RESTRICT,
    CONSTRAINT fk_media_folder_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE media_assets
    ADD CONSTRAINT fk_media_folder FOREIGN KEY (folder_id) REFERENCES media_folders(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS media_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id BIGINT UNSIGNED NOT NULL,
    variant_key VARCHAR(80) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_media_variant (media_id,variant_key),
    UNIQUE KEY uq_media_variant_path (storage_path),
    CONSTRAINT fk_media_variant_asset FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE seo_pages
    ADD COLUMN social_media_id BIGINT UNSIGNED NULL AFTER social_description,
    ADD COLUMN schema_type VARCHAR(80) NULL AFTER sitemap_enabled,
    ADD CONSTRAINT fk_seo_social_media FOREIGN KEY (social_media_id) REFERENCES media_assets(id) ON DELETE SET NULL;

INSERT IGNORE INTO permissions (name,label) VALUES
('menus.manage','Manage menus');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name='menus.manage'
WHERE r.name IN ('super_administrator','administrator');
