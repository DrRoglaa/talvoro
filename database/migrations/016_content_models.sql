CREATE TABLE IF NOT EXISTS content_models (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    singular_name VARCHAR(120) NOT NULL,
    plural_name VARCHAR(120) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    has_archive TINYINT(1) NOT NULL DEFAULT 1,
    has_urls TINYINT(1) NOT NULL DEFAULT 1,
    searchable TINYINT(1) NOT NULL DEFAULT 1,
    sitemap_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_content_models_status (status),
    CONSTRAINT fk_content_models_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_content_components_status (status),
    CONSTRAINT fk_content_components_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    label VARCHAR(120) NOT NULL,
    field_type VARCHAR(40) NOT NULL,
    help_text VARCHAR(500) NULL,
    placeholder VARCHAR(255) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_field_key (model_id,field_key),
    INDEX idx_content_fields_order (model_id,sort_order,id),
    CONSTRAINT fk_content_fields_model FOREIGN KEY (model_id) REFERENCES content_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS component_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    label VARCHAR(120) NOT NULL,
    field_type VARCHAR(40) NOT NULL,
    help_text VARCHAR(500) NULL,
    placeholder VARCHAR(255) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_component_field_key (component_id,field_key),
    INDEX idx_component_fields_order (component_id,sort_order,id),
    CONSTRAINT fk_component_fields_component FOREIGN KEY (component_id) REFERENCES content_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    field_values_json LONGTEXT NOT NULL,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(500) NULL,
    published_at DATETIME NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_entry_slug (model_id,slug),
    INDEX idx_content_entries_model_status (model_id,status,deleted_at),
    INDEX idx_content_entries_author (author_id),
    INDEX idx_content_entries_updated (model_id,updated_at),
    CONSTRAINT fk_content_entries_model FOREIGN KEY (model_id) REFERENCES content_models(id) ON DELETE RESTRICT,
    CONSTRAINT fk_content_entries_author FOREIGN KEY (author_id) REFERENCES users(id),
    CONSTRAINT fk_content_entries_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_media_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(191) NOT NULL,
    media_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    UNIQUE KEY uq_content_media_usage (entry_id,field_key,media_id),
    INDEX idx_content_media_entry (entry_id,field_key,sort_order),
    INDEX idx_content_media_asset (media_id),
    CONSTRAINT fk_content_media_entry FOREIGN KEY (entry_id) REFERENCES content_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_media_asset FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_revision_media_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(191) NOT NULL,
    media_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    UNIQUE KEY uq_revision_media_usage (revision_id,field_key,media_id),
    INDEX idx_revision_media_revision (revision_id,field_key,sort_order),
    INDEX idx_revision_media_asset (media_id),
    CONSTRAINT fk_revision_media_revision FOREIGN KEY (revision_id) REFERENCES content_revisions(id) ON DELETE CASCADE,
    CONSTRAINT fk_revision_media_asset FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_entry_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    target_entry_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    UNIQUE KEY uq_content_relation (source_entry_id,field_key,target_entry_id),
    INDEX idx_content_relation_source (source_entry_id,field_key,sort_order),
    INDEX idx_content_relation_target (target_entry_id),
    CONSTRAINT fk_content_relation_source FOREIGN KEY (source_entry_id) REFERENCES content_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_relation_target FOREIGN KEY (target_entry_id) REFERENCES content_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name,label) VALUES
('content_models.manage','Manage content models and components'),
('custom_content.view','View structured content'),
('custom_content.edit','Edit structured content'),
('custom_content.publish','Publish structured content');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name IN ('content_models.manage','custom_content.view','custom_content.edit','custom_content.publish')
WHERE r.name IN ('super_administrator','administrator');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name IN ('custom_content.view','custom_content.edit','custom_content.publish')
WHERE r.name='editor';
