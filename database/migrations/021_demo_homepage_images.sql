-- Talvoro bundled demo homepage images.
-- Only an untouched v0.15.x polished demo Home page is rewritten.
-- Customized Home pages and non-empty image settings are not overwritten.
CREATE TEMPORARY TABLE _talvoro_demo_image_targets (id BIGINT UNSIGNED PRIMARY KEY);

INSERT INTO _talvoro_demo_image_targets (id)
SELECT id FROM pages
WHERE path='/' AND deleted_at IS NULL
  AND blocks_json='[{"id":"legacyhero01","enabled":true,"type":"hero","eyebrow":"Make it yours.","heading":"Create a *beautiful* place for what matters.","intro":"Start with a clear message, shape every section around your story, and give visitors an easy path to what matters next.","primary_enabled":true,"primary_label":"About us","primary_url":"/about","secondary_enabled":true,"secondary_label":"Read the journal","secondary_url":"/blog","image_path":"","image_alt":"","style_tone":"default","style_width":"wide","style_spacing":"compact","style_alignment":"left","style_variant":"default"},{"id":"legacyvalues01","enabled":true,"type":"values","items":[{"icon":"sparkles","title":"Clear by design","body":"Give every visitor an immediate sense of who you are and why your work matters."},{"icon":"home","title":"Made to adapt","body":"Shape sections, imagery and content around the way you actually communicate."},{"icon":"award","title":"Thoughtful details","body":"Use considered typography, spacing and structure to make every page feel intentional."},{"icon":"shield","title":"Built on trust","body":"Set clear expectations and guide people toward the next useful step with confidence."},{"icon":"support","title":"Ready to grow","body":"Start simple, then add stories, services or new ideas without rebuilding everything."}],"style_tone":"default","style_width":"wide","style_spacing":"compact","style_alignment":"left","style_variant":"default"},{"id":"legacycards001","enabled":true,"type":"cards","eyebrow":"Selected","heading":"A place for your best work.","view_label":"Explore more","view_url":"/about","items":[{"title":"Latest story","meta":"Journal","url":"/blog","image_path":"","image_alt":""},{"title":"Featured project","meta":"Selected","url":"/about","image_path":"","image_alt":""},{"title":"What we offer","meta":"Services","url":"/about","image_path":"","image_alt":""},{"title":"Our story","meta":"About","url":"/about","image_path":"","image_alt":""}],"style_tone":"default","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"},{"id":"legacylatest01","enabled":true,"type":"latest_posts","eyebrow":"From the journal","heading":"Latest stories","view_label":"Read the journal","count":3,"style_tone":"default","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"},{"id":"legacycta001","enabled":true,"type":"cta","eyebrow":"Your next step","heading":"Ready to shape this space around your story?","button_label":"Discover more","button_url":"/about","style_tone":"soft","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"}]';

UPDATE cms_settings
SET setting_value='/assets/demo/talvoro-home-hero.webp', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.hero_image_path' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='/assets/demo/talvoro-featured-1.webp', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card1_image_path' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='Open notebook and coffee on a warm desk', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card1_image_alt' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='/assets/demo/talvoro-featured-2.webp', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card2_image_path' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='Modern home surrounded by trees and mountains', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card2_image_alt' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='/assets/demo/talvoro-featured-3.webp', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card3_image_path' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='Warm workspace with a laptop, plant and coffee', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card3_image_alt' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='/assets/demo/talvoro-featured-4.webp', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card4_image_path' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE cms_settings
SET setting_value='Minimal interior with framed art and a ceramic vase', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card4_image_alt' AND setting_value=''
  AND (
      NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
      OR EXISTS (SELECT 1 FROM _talvoro_demo_image_targets)
  );

UPDATE pages p
JOIN _talvoro_demo_image_targets t ON t.id=p.id
SET p.blocks_json='[{"id":"legacyhero01","enabled":true,"type":"hero","eyebrow":"Make it yours.","heading":"Create a *beautiful* place for what matters.","intro":"Start with a clear message, shape every section around your story, and give visitors an easy path to what matters next.","primary_enabled":true,"primary_label":"About us","primary_url":"/about","secondary_enabled":true,"secondary_label":"Read the journal","secondary_url":"/blog","image_path":"/assets/demo/talvoro-home-hero.webp","image_alt":"Warm workspace with a notebook, coffee and leafy branches","style_tone":"default","style_width":"wide","style_spacing":"compact","style_alignment":"left","style_variant":"default"},{"id":"legacyvalues01","enabled":true,"type":"values","items":[{"icon":"sparkles","title":"Clear by design","body":"Give every visitor an immediate sense of who you are and why your work matters."},{"icon":"home","title":"Made to adapt","body":"Shape sections, imagery and content around the way you actually communicate."},{"icon":"award","title":"Thoughtful details","body":"Use considered typography, spacing and structure to make every page feel intentional."},{"icon":"shield","title":"Built on trust","body":"Set clear expectations and guide people toward the next useful step with confidence."},{"icon":"support","title":"Ready to grow","body":"Start simple, then add stories, services or new ideas without rebuilding everything."}],"style_tone":"default","style_width":"wide","style_spacing":"compact","style_alignment":"left","style_variant":"default"},{"id":"legacycards001","enabled":true,"type":"cards","eyebrow":"Selected","heading":"A place for your best work.","view_label":"Explore more","view_url":"/about","items":[{"title":"Latest story","meta":"Journal","url":"/blog","image_path":"/assets/demo/talvoro-featured-1.webp","image_alt":"Open notebook and coffee on a warm desk"},{"title":"Featured project","meta":"Selected","url":"/about","image_path":"/assets/demo/talvoro-featured-2.webp","image_alt":"Modern home surrounded by trees and mountains"},{"title":"What we offer","meta":"Services","url":"/about","image_path":"/assets/demo/talvoro-featured-3.webp","image_alt":"Warm workspace with a laptop, plant and coffee"},{"title":"Our story","meta":"About","url":"/about","image_path":"/assets/demo/talvoro-featured-4.webp","image_alt":"Minimal interior with framed art and a ceramic vase"}],"style_tone":"default","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"},{"id":"legacylatest01","enabled":true,"type":"latest_posts","eyebrow":"From the journal","heading":"Latest stories","view_label":"Read the journal","count":3,"style_tone":"default","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"},{"id":"legacycta001","enabled":true,"type":"cta","eyebrow":"Your next step","heading":"Ready to shape this space around your story?","button_label":"Discover more","button_url":"/about","style_tone":"soft","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"}]', p.updated_at=UTC_TIMESTAMP();

DROP TEMPORARY TABLE _talvoro_demo_image_targets;
