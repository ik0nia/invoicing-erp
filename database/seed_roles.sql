INSERT INTO roles (`key`, `label`) VALUES
('admin', 'Administrator'),
('staff', 'Angajat firma'),
('supplier_user', 'Utilizator furnizor'),
('client_user', 'Utilizator client'),
('intermediary_user', 'Utilizator intermediar')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
