ALTER TABLE content_models
    ADD COLUMN model_key VARCHAR(100) NULL AFTER plural_name,
    ADD COLUMN icon VARCHAR(40) NOT NULL DEFAULT 'collection' AFTER slug,
    ADD COLUMN enable_revisions TINYINT(1) NOT NULL DEFAULT 1 AFTER sitemap_enabled,
    ADD COLUMN enable_autosave TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_revisions,
    ADD COLUMN enable_trash TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_autosave,
    ADD COLUMN enable_seo TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_trash,
    ADD COLUMN enable_featured_image TINYINT(1) NOT NULL DEFAULT 0 AFTER enable_seo,
    ADD COLUMN enable_scheduling TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_featured_image;

UPDATE content_models
SET model_key=CASE
    WHEN REPLACE(slug,'-','_') REGEXP '^[a-z]' THEN REPLACE(slug,'-','_')
    ELSE CONCAT('model_',REPLACE(slug,'-','_'))
END
WHERE model_key IS NULL OR model_key='';

ALTER TABLE content_models
    MODIFY COLUMN model_key VARCHAR(100) NOT NULL,
    ADD UNIQUE KEY uq_content_models_key (model_key);

ALTER TABLE content_fields
    ADD COLUMN archived_at DATETIME NULL AFTER sort_order,
    ADD INDEX idx_content_fields_active (model_id,archived_at,sort_order,id);

ALTER TABLE component_fields
    ADD COLUMN archived_at DATETIME NULL AFTER sort_order,
    ADD INDEX idx_component_fields_active (component_id,archived_at,sort_order,id);

ALTER TABLE content_entries
    MODIFY COLUMN status ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
    ADD COLUMN featured_media_id BIGINT UNSIGNED NULL AFTER field_values_json,
    ADD COLUMN canonical_url VARCHAR(500) NULL AFTER seo_description,
    ADD COLUMN robots VARCHAR(40) NOT NULL DEFAULT 'index,follow' AFTER canonical_url,
    ADD COLUMN social_title VARCHAR(255) NULL AFTER robots,
    ADD COLUMN social_description VARCHAR(500) NULL AFTER social_title,
    ADD COLUMN social_media_id BIGINT UNSIGNED NULL AFTER social_description,
    ADD INDEX idx_content_entries_schedule (status,published_at,deleted_at),
    ADD CONSTRAINT fk_content_entries_featured_media FOREIGN KEY (featured_media_id) REFERENCES media_assets(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_content_entries_social_media FOREIGN KEY (social_media_id) REFERENCES media_assets(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS content_search_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id BIGINT UNSIGNED NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    value_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_search_value (entry_id,field_key),
    INDEX idx_content_search_model_field (model_id,field_key),
    FULLTEXT KEY ft_content_search_value (value_text),
    CONSTRAINT fk_content_search_entry FOREIGN KEY (entry_id) REFERENCES content_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_search_model FOREIGN KEY (model_id) REFERENCES content_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_unique_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id BIGINT UNSIGNED NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    value_hash CHAR(64) NOT NULL,
    value_text VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_unique_entry_field (entry_id,field_key),
    UNIQUE KEY uq_content_unique_model_field_hash (model_id,field_key,value_hash),
    CONSTRAINT fk_content_unique_entry FOREIGN KEY (entry_id) REFERENCES content_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_unique_model FOREIGN KEY (model_id) REFERENCES content_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_model_role_permissions (
    model_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_publish TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (model_id,role_id),
    CONSTRAINT fk_content_model_role_model FOREIGN KEY (model_id) REFERENCES content_models(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_model_role_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO content_model_role_permissions
    (model_id,role_id,can_view,can_create,can_edit,can_publish,can_delete,created_at,updated_at)
SELECT m.id,r.id,1,1,1,1,CASE WHEN r.name='editor' THEN 0 ELSE 1 END,UTC_TIMESTAMP(),UTC_TIMESTAMP()
FROM content_models m
JOIN roles r ON r.name IN ('administrator','editor');

INSERT IGNORE INTO permissions (name,label) VALUES
('custom_content.create','Create structured content'),
('custom_content.delete','Delete structured content');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name IN ('custom_content.create','custom_content.delete')
WHERE r.name IN ('super_administrator','administrator','editor');
