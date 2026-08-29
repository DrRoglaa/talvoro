-- Redesign 01c: move only known Talvoro default design values to the current
-- warm/light Trenlume palette. User-custom colors that do not match one of
-- Talvoro's shipped defaults are intentionally left untouched.

UPDATE cms_settings
SET setting_value = '#ff6b52', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.brand' OR setting_key = 'design.brand')
  AND LOWER(TRIM(setting_value)) IN ('#d66f5b','#b75544','#d78068','#ff6f59');

UPDATE cms_settings
SET setting_value = '#3bcfc8', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.accent' OR setting_key = 'design.accent')
  AND LOWER(TRIM(setting_value)) IN ('#b75544','#5c8f86','#6c958f','#62cfc2');

UPDATE cms_settings
SET setting_value = '#8d7af0', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.depth' OR setting_key = 'design.depth')
  AND LOWER(TRIM(setting_value)) IN ('#5c4c7c','#756f9a','#9f86ff');

UPDATE cms_settings
SET setting_value = '#faf7f3', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.background' OR setting_key = 'design.background')
  AND LOWER(TRIM(setting_value)) IN ('#fffaf5','#f7f2ea','#f6f2ec','#f7f3ee');

UPDATE cms_settings
SET setting_value = '#fffdfa', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.surface' OR setting_key = 'design.surface')
  AND LOWER(TRIM(setting_value)) IN ('#ffffff','#fffdf9','#fffdfa');

UPDATE cms_settings
SET setting_value = '#26221f', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.text' OR setting_key = 'design.text')
  AND LOWER(TRIM(setting_value)) IN ('#2f2926','#24201e','#201c19','#24211f');

UPDATE cms_settings
SET setting_value = '#8d837c', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.muted' OR setting_key = 'design.muted')
  AND LOWER(TRIM(setting_value)) IN ('#766b65','#6f655f','#746a63','#77716c');

UPDATE cms_settings
SET setting_value = '#eae6e2', updated_at = UTC_TIMESTAMP()
WHERE (setting_key LIKE 'design.theme.%.border' OR setting_key = 'design.border')
  AND LOWER(TRIM(setting_value)) IN ('#e8ddd5','#e4d9ce','#e7ddd2');
