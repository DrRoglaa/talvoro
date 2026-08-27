INSERT INTO cms_settings (setting_key,setting_value,updated_at) VALUES
('branding.site_name','',UTC_TIMESTAMP()),
('branding.tagline','Independent publishing',UTC_TIMESTAMP()),
('branding.logo_path','',UTC_TIMESTAMP()),
('homepage.eyebrow','Welcome',UTC_TIMESTAMP()),
('homepage.heading','Your website.\nYour content.\nYour way.',UTC_TIMESTAMP()),
('homepage.intro','A focused, self-hosted website with thoughtful publishing, first-party analytics and no external tracking dependency.',UTC_TIMESTAMP()),
('homepage.primary_enabled','1',UTC_TIMESTAMP()),
('homepage.primary_label','Read the blog',UTC_TIMESTAMP()),
('homepage.primary_url','/blog',UTC_TIMESTAMP()),
('homepage.secondary_enabled','0',UTC_TIMESTAMP()),
('homepage.secondary_label','Learn more',UTC_TIMESTAMP()),
('homepage.secondary_url','/about',UTC_TIMESTAMP()),
('homepage.hero_image_path','',UTC_TIMESTAMP()),
('homepage.features_enabled','1',UTC_TIMESTAMP()),
('homepage.features_eyebrow','Built around your content',UTC_TIMESTAMP()),
('homepage.features_heading','A homepage you can make your own.',UTC_TIMESTAMP()),
('homepage.features_intro','Edit the message, calls to action, imagery and highlights directly from Talvoro.',UTC_TIMESTAMP()),
('homepage.feature1_title','Your story',UTC_TIMESTAMP()),
('homepage.feature1_body','Use this space for the first thing you want visitors to understand about you, your company or your project.',UTC_TIMESTAMP()),
('homepage.feature2_title','Your focus',UTC_TIMESTAMP()),
('homepage.feature2_body','Highlight a service, value, product, kennel, portfolio area or anything else that belongs on your front page.',UTC_TIMESTAMP()),
('homepage.feature3_title','Your difference',UTC_TIMESTAMP()),
('homepage.feature3_body','Explain what makes the website worth exploring without editing a template file.',UTC_TIMESTAMP()),
('homepage.latest_posts_enabled','1',UTC_TIMESTAMP()),
('homepage.latest_posts_heading','Latest from the blog',UTC_TIMESTAMP()),
('homepage.latest_posts_count','3',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

UPDATE cms_settings
SET setting_value='Talvoro',updated_at=UTC_TIMESTAMP()
WHERE setting_key='mail.from_name' AND setting_value='Privacy CMS';

UPDATE themes
SET author='Talvoro',updated_at=UTC_TIMESTAMP()
WHERE is_builtin=1 AND author='Privacy CMS';
