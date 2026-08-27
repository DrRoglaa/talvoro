ALTER TABLE pages
    ADD COLUMN IF NOT EXISTS page_template VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER path,
    ADD COLUMN IF NOT EXISTS show_in_footer TINYINT(1) NOT NULL DEFAULT 0 AFTER navigation_order,
    ADD COLUMN IF NOT EXISTS footer_label VARCHAR(120) NULL AFTER show_in_footer,
    ADD COLUMN IF NOT EXISTS footer_order INT NOT NULL DEFAULT 100 AFTER footer_label;

ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS featured_image_path VARCHAR(255) NULL AFTER excerpt;

INSERT INTO cms_settings (setting_key,setting_value,updated_at) VALUES
('homepage.values_enabled','1',UTC_TIMESTAMP()),
('homepage.value1_title','Thoughtful first',UTC_TIMESTAMP()),
('homepage.value1_body','Lead with the value or promise that matters most to your visitors.',UTC_TIMESTAMP()),
('homepage.value2_title','Made with care',UTC_TIMESTAMP()),
('homepage.value2_body','Use this space for a second trust point, benefit or principle.',UTC_TIMESTAMP()),
('homepage.value3_title','Clear standards',UTC_TIMESTAMP()),
('homepage.value3_body','Explain the quality, process or standard behind what you do.',UTC_TIMESTAMP()),
('homepage.value4_title','Experience',UTC_TIMESTAMP()),
('homepage.value4_body','Share the knowledge or history that gives visitors confidence.',UTC_TIMESTAMP()),
('homepage.value5_title','Ongoing support',UTC_TIMESTAMP()),
('homepage.value5_body','Finish with the long-term relationship or support you provide.',UTC_TIMESTAMP()),
('homepage.featured_enabled','1',UTC_TIMESTAMP()),
('homepage.featured_eyebrow','Featured',UTC_TIMESTAMP()),
('homepage.featured_heading','Meet what matters most.',UTC_TIMESTAMP()),
('homepage.featured_view_label','View all',UTC_TIMESTAMP()),
('homepage.featured_view_url','/about',UTC_TIMESTAMP()),
('homepage.featured_card1_enabled','1',UTC_TIMESTAMP()),
('homepage.featured_card1_title','First highlight',UTC_TIMESTAMP()),
('homepage.featured_card1_meta','Featured',UTC_TIMESTAMP()),
('homepage.featured_card1_url','/about',UTC_TIMESTAMP()),
('homepage.featured_card1_image_path','',UTC_TIMESTAMP()),
('homepage.featured_card1_image_alt','',UTC_TIMESTAMP()),
('homepage.featured_card2_enabled','1',UTC_TIMESTAMP()),
('homepage.featured_card2_title','Second highlight',UTC_TIMESTAMP()),
('homepage.featured_card2_meta','Featured',UTC_TIMESTAMP()),
('homepage.featured_card2_url','/about',UTC_TIMESTAMP()),
('homepage.featured_card2_image_path','',UTC_TIMESTAMP()),
('homepage.featured_card2_image_alt','',UTC_TIMESTAMP()),
('homepage.featured_card3_enabled','1',UTC_TIMESTAMP()),
('homepage.featured_card3_title','Third highlight',UTC_TIMESTAMP()),
('homepage.featured_card3_meta','Featured',UTC_TIMESTAMP()),
('homepage.featured_card3_url','/about',UTC_TIMESTAMP()),
('homepage.featured_card3_image_path','',UTC_TIMESTAMP()),
('homepage.featured_card3_image_alt','',UTC_TIMESTAMP()),
('homepage.featured_card4_enabled','1',UTC_TIMESTAMP()),
('homepage.featured_card4_title','Fourth highlight',UTC_TIMESTAMP()),
('homepage.featured_card4_meta','Featured',UTC_TIMESTAMP()),
('homepage.featured_card4_url','/about',UTC_TIMESTAMP()),
('homepage.featured_card4_image_path','',UTC_TIMESTAMP()),
('homepage.featured_card4_image_alt','',UTC_TIMESTAMP()),
('homepage.latest_posts_eyebrow','From the journal',UTC_TIMESTAMP()),
('homepage.latest_posts_view_label','View all news',UTC_TIMESTAMP()),
('homepage.cta_enabled','1',UTC_TIMESTAMP()),
('homepage.cta_eyebrow','Why choose us',UTC_TIMESTAMP()),
('homepage.cta_heading','A clear final thought that gives people a reason to continue.',UTC_TIMESTAMP()),
('homepage.cta_button_label','Discover more',UTC_TIMESTAMP()),
('homepage.cta_button_url','/about',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

UPDATE cms_settings SET setting_value='Care. Craft. Purpose.',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.eyebrow' AND setting_value='Welcome';

UPDATE cms_settings SET setting_value='Build a *beautiful* home for your story.',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.heading' AND setting_value='Your website.\nYour content.\nYour way.';

UPDATE cms_settings SET setting_value='A warm, focused front page that introduces what you do, why it matters and where visitors should go next.',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.intro' AND setting_value='A focused, self-hosted website with thoughtful publishing, first-party analytics and no external tracking dependency.';

UPDATE cms_settings SET setting_value='About us',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.primary_label' AND setting_value='Read the blog';

UPDATE cms_settings SET setting_value='/about',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.primary_url' AND setting_value='/blog';

UPDATE cms_settings SET setting_value='1',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.secondary_enabled' AND setting_value='0';

UPDATE cms_settings SET setting_value='Read the blog',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.secondary_label' AND setting_value='Learn more';

UPDATE cms_settings SET setting_value='/blog',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.secondary_url' AND setting_value='/about';

UPDATE cms_settings SET setting_value='Latest news',updated_at=UTC_TIMESTAMP()
WHERE setting_key='homepage.latest_posts_heading' AND setting_value='Latest from the blog';
