ALTER TABLE pages
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX IF NOT EXISTS idx_pages_deleted (deleted_at);

ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX IF NOT EXISTS idx_posts_deleted (deleted_at);

CREATE TABLE IF NOT EXISTS content_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type VARCHAR(40) NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    revision_no INT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL DEFAULT 'save',
    snapshot_json LONGTEXT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_revision (content_type,content_id,revision_no),
    INDEX idx_content_revision_lookup (content_type,content_id,created_at),
    INDEX idx_content_revision_author (author_id),
    CONSTRAINT fk_content_revision_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_autosaves (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type VARCHAR(40) NOT NULL,
    content_id BIGINT UNSIGNED NULL,
    identity_key VARCHAR(96) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    payload_json LONGTEXT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    saved_at DATETIME NOT NULL,
    UNIQUE KEY uq_content_autosave (content_type,identity_key,user_id),
    INDEX idx_content_autosave_lookup (content_type,content_id,user_id,saved_at),
    CONSTRAINT fk_content_autosave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_settings (setting_key,setting_value,updated_at)
VALUES ('content.trash_retention_days','30',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
