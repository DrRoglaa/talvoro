-- Remove the retired Trenlume Light compatibility theme. Historical migrations
-- remain unchanged so older installs can still upgrade through the full chain.
-- Custom active themes are preserved; Talvoro Editorial is activated only when
-- the retired theme was the only active frontend theme.

UPDATE themes
SET is_active=0,updated_at=UTC_TIMESTAMP()
WHERE slug='trenlume-light' AND is_active=1;

SET @talvoro_active_theme_count := (
    SELECT COUNT(*) FROM themes WHERE is_active=1 AND slug<>'trenlume-light'
);

UPDATE themes
SET is_active=1,updated_at=UTC_TIMESTAMP()
WHERE slug='talvoro-editorial' AND @talvoro_active_theme_count=0;

UPDATE cms_settings
SET setting_value='talvoro-editorial',updated_at=UTC_TIMESTAMP()
WHERE setting_key='frontend.theme'
  AND setting_value IN ('trenlume','trenlume-light');

DELETE FROM themes
WHERE slug='trenlume-light';
