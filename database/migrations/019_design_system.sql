INSERT IGNORE INTO permissions (name,label) VALUES
('design.manage','Manage design styles');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.name='design.manage'
WHERE r.name IN ('super_administrator','administrator');
