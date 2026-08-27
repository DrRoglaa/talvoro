-- Talvoro demo homepage polish.
-- Only the untouched seeded demo Home page is rewritten.
CREATE TEMPORARY TABLE _talvoro_demo_home_targets (id BIGINT UNSIGNED PRIMARY KEY);

INSERT INTO _talvoro_demo_home_targets (id)
SELECT id FROM pages
WHERE path='/' AND deleted_at IS NULL
  AND blocks_json='[{"id":"legacyhero01","enabled":true,"type":"hero","eyebrow":"Care. Craft. Purpose.","heading":"Build a *beautiful* home for your story.","intro":"A warm, focused front page that introduces what you do, why it matters and where visitors should go next.","primary_enabled":true,"primary_label":"About us","primary_url":"/about","secondary_enabled":true,"secondary_label":"Read the blog","secondary_url":"/blog","image_path":"","image_alt":""},{"id":"legacyvalues01","enabled":true,"type":"values","items":[{"icon":"heart","title":"Thoughtful first","body":"Lead with the value or promise that matters most to your visitors."},{"icon":"home","title":"Made with care","body":"Use this space for a second trust point, benefit or principle."},{"icon":"award","title":"Clear standards","body":"Explain the quality, process or standard behind what you do."},{"icon":"clock","title":"Experience","body":"Share the knowledge or history that gives visitors confidence."},{"icon":"heart","title":"Ongoing support","body":"Finish with the long-term relationship or support you provide."}]},{"id":"legacycards001","enabled":true,"type":"cards","eyebrow":"Featured","heading":"Meet what matters most.","view_label":"View all","view_url":"/about","items":[{"title":"First highlight","meta":"Featured","url":"/about","image_path":"","image_alt":""},{"title":"Second highlight","meta":"Featured","url":"/about","image_path":"","image_alt":""},{"title":"Third highlight","meta":"Featured","url":"/about","image_path":"","image_alt":""},{"title":"Fourth highlight","meta":"Featured","url":"/about","image_path":"","image_alt":""}]},{"id":"legacylatest01","enabled":true,"type":"latest_posts","eyebrow":"From the journal","heading":"Latest news","view_label":"View all news","count":3},{"id":"legacycta001","enabled":true,"type":"cta","eyebrow":"Why choose us","heading":"A clear final thought that gives people a reason to continue.","button_label":"Discover more","button_url":"/about"}]';

UPDATE cms_settings
SET setting_value='Make it yours.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.eyebrow' AND setting_value='Care. Craft. Purpose.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Create a *beautiful* place for what matters.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.heading' AND setting_value='Build a *beautiful* home for your story.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Start with a clear message, shape every section around your story, and give visitors an easy path to what matters next.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.intro' AND setting_value='A warm, focused front page that introduces what you do, why it matters and where visitors should go next.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Read the journal', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.secondary_label' AND setting_value='Read the blog'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Latest stories', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.latest_posts_heading' AND setting_value='Latest news'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Read the journal', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.latest_posts_view_label' AND setting_value='View all news'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Selected', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_eyebrow' AND setting_value='Featured'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='A place for your best work.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_heading' AND setting_value='Meet what matters most.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Explore more', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_view_label' AND setting_value='View all'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Your next step', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.cta_eyebrow' AND setting_value='Why choose us'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Ready to shape this space around your story?', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.cta_heading' AND setting_value='A clear final thought that gives people a reason to continue.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Clear by design', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value1_title' AND setting_value='Thoughtful first'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Give every visitor an immediate sense of who you are and why your work matters.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value1_body' AND setting_value='Lead with the value or promise that matters most to your visitors.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Made to adapt', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value2_title' AND setting_value='Made with care'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Shape sections, imagery and content around the way you actually communicate.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value2_body' AND setting_value='Use this space for a second trust point, benefit or principle.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Thoughtful details', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value3_title' AND setting_value='Clear standards'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Use considered typography, spacing and structure to make every page feel intentional.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value3_body' AND setting_value='Explain the quality, process or standard behind what you do.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Built on trust', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value4_title' AND setting_value='Experience'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Set clear expectations and guide people toward the next useful step with confidence.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value4_body' AND setting_value='Share the knowledge or history that gives visitors confidence.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Ready to grow', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value5_title' AND setting_value='Ongoing support'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Start simple, then add stories, services or new ideas without rebuilding everything.', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.value5_body' AND setting_value='Finish with the long-term relationship or support you provide.'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Latest story', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card1_title' AND setting_value='First highlight'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Journal', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card1_meta' AND setting_value='Featured'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='/blog', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card1_url' AND setting_value='/about'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Featured project', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card2_title' AND setting_value='Second highlight'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Selected', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card2_meta' AND setting_value='Featured'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='What we offer', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card3_title' AND setting_value='Third highlight'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Services', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card3_meta' AND setting_value='Featured'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='Our story', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card4_title' AND setting_value='Fourth highlight'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE cms_settings
SET setting_value='About', updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.featured_card4_meta' AND setting_value='Featured'
  AND (
    NOT EXISTS (SELECT 1 FROM pages WHERE path='/' AND deleted_at IS NULL)
    OR EXISTS (SELECT 1 FROM _talvoro_demo_home_targets)
  );

UPDATE pages p
JOIN _talvoro_demo_home_targets t ON t.id=p.id
SET p.blocks_json='[{"id":"legacyhero01","enabled":true,"type":"hero","eyebrow":"Make it yours.","heading":"Create a *beautiful* place for what matters.","intro":"Start with a clear message, shape every section around your story, and give visitors an easy path to what matters next.","primary_enabled":true,"primary_label":"About us","primary_url":"/about","secondary_enabled":true,"secondary_label":"Read the journal","secondary_url":"/blog","image_path":"","image_alt":"","style_tone":"default","style_width":"wide","style_spacing":"compact","style_alignment":"left","style_variant":"default"},{"id":"legacyvalues01","enabled":true,"type":"values","items":[{"icon":"sparkles","title":"Clear by design","body":"Give every visitor an immediate sense of who you are and why your work matters."},{"icon":"home","title":"Made to adapt","body":"Shape sections, imagery and content around the way you actually communicate."},{"icon":"award","title":"Thoughtful details","body":"Use considered typography, spacing and structure to make every page feel intentional."},{"icon":"shield","title":"Built on trust","body":"Set clear expectations and guide people toward the next useful step with confidence."},{"icon":"support","title":"Ready to grow","body":"Start simple, then add stories, services or new ideas without rebuilding everything."}],"style_tone":"default","style_width":"wide","style_spacing":"compact","style_alignment":"left","style_variant":"default"},{"id":"legacycards001","enabled":true,"type":"cards","eyebrow":"Selected","heading":"A place for your best work.","view_label":"Explore more","view_url":"/about","items":[{"title":"Latest story","meta":"Journal","url":"/blog","image_path":"","image_alt":""},{"title":"Featured project","meta":"Selected","url":"/about","image_path":"","image_alt":""},{"title":"What we offer","meta":"Services","url":"/about","image_path":"","image_alt":""},{"title":"Our story","meta":"About","url":"/about","image_path":"","image_alt":""}],"style_tone":"default","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"},{"id":"legacylatest01","enabled":true,"type":"latest_posts","eyebrow":"From the journal","heading":"Latest stories","view_label":"Read the journal","count":3,"style_tone":"default","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"},{"id":"legacycta001","enabled":true,"type":"cta","eyebrow":"Your next step","heading":"Ready to shape this space around your story?","button_label":"Discover more","button_url":"/about","style_tone":"soft","style_width":"wide","style_spacing":"normal","style_alignment":"left","style_variant":"default"}]', p.updated_at=UTC_TIMESTAMP();

DROP TEMPORARY TABLE _talvoro_demo_home_targets;
