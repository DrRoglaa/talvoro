-- Redesign 01d: move only Talvoro's shipped typography defaults to the
-- Trenlume-style system sans stack. Explicit custom choices are untouched.

UPDATE cms_settings
SET setting_value = 'system', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.heading_font' OR setting_key = 'design.heading_font')
  AND LOWER(TRIM(setting_value)) IN ('editorial');

UPDATE cms_settings
SET setting_value = 'system', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.body_font' OR setting_key = 'design.body_font')
  AND LOWER(TRIM(setting_value)) IN ('humanist');
