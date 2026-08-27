
ALTER TABLE analytics_events
    ADD COLUMN utm_source VARCHAR(120) NULL AFTER os,
    ADD COLUMN utm_medium VARCHAR(120) NULL AFTER utm_source,
    ADD COLUMN utm_campaign VARCHAR(190) NULL AFTER utm_medium,
    ADD COLUMN utm_content VARCHAR(190) NULL AFTER utm_campaign,
    ADD COLUMN utm_term VARCHAR(190) NULL AFTER utm_content,
    ADD INDEX idx_analytics_campaign_time (utm_campaign, occurred_at);

CREATE TABLE cms_settings (
    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_cms_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_settings (setting_key, setting_value, updated_at) VALUES
('site.mode', 'live', UTC_TIMESTAMP()),
('site.development_message', 'We are making a few improvements. Please check back soon.', UTC_TIMESTAMP());

CREATE TABLE seo_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(191) NOT NULL UNIQUE,
    search_phrase VARCHAR(255) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    social_title VARCHAR(255) NULL,
    social_description VARCHAR(500) NULL,
    canonical_url VARCHAR(500) NULL,
    robots VARCHAR(50) NOT NULL DEFAULT 'index,follow',
    sitemap_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_seo_sitemap (sitemap_enabled),
    CONSTRAINT fk_seo_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(191) NOT NULL UNIQUE,
    destination VARCHAR(1000) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_redirects_active (is_active),
    CONSTRAINT fk_redirects_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name,label) VALUES
('site.manage','Manage site mode'),
('seo.manage','Manage SEO'),
('redirects.manage','Manage redirects'),
('sitehealth.view','View site health');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name IN ('site.manage','seo.manage','redirects.manage','sitehealth.view')
WHERE r.name='administrator';
