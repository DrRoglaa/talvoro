INSERT INTO cms_settings (setting_key,setting_value,updated_at) VALUES
('security.admin_path','admin',UTC_TIMESTAMP()),
('security.admin_path_history','[]',UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

INSERT IGNORE INTO permissions (name,label) VALUES
('security.manage','Manage CMS security settings');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name='security.manage'
WHERE r.name='super_administrator';
