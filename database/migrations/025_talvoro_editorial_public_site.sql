-- Redesign 02: introduce Talvoro Editorial as the canonical built-in theme.
-- Keep Trenlume Light installed for upgrade compatibility. Custom active themes
-- remain untouched; only the previously active legacy built-in is migrated.

INSERT IGNORE INTO themes
(name,slug,version,author,description,css_text,is_builtin,is_active,created_at,updated_at)
VALUES
('Talvoro Editorial','talvoro-editorial','1.0.0','Talvoro','Warm, light editorial publishing with calm typography, restrained coral actions and privacy-first self-hosting.','',1,0,UTC_TIMESTAMP(),UTC_TIMESTAMP());

UPDATE themes
SET is_active=0,updated_at=UTC_TIMESTAMP()
WHERE slug='trenlume-light' AND is_active=1;

UPDATE themes
SET is_active=1,updated_at=UTC_TIMESTAMP()
WHERE slug='talvoro-editorial'
  AND NOT EXISTS (SELECT 1 FROM themes t2 WHERE t2.is_active=1 AND t2.id<>themes.id);

-- Preserve a compatibility breadcrumb for support/diagnostics without using it
-- as a user-visible default name.
INSERT INTO cms_settings (setting_key,setting_value,updated_at)
VALUES ('redesign.editorial_theme_installed','1',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
