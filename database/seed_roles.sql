INSERT INTO roles (`key`, `label`) VALUES
('super_admin', 'Super admin'),
('admin', 'Administrator'),
('contabil', 'Contabil'),
('operator', 'Operator'),
('supplier_user', 'Utilizator furnizor'),
('staff', 'Angajat firma'),
('client_user', 'Utilizator client'),
('intermediary_user', 'Utilizator intermediar')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
