CREATE TABLE IF NOT EXISTS partners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cui VARCHAR(32) NOT NULL UNIQUE,
    denumire VARCHAR(255) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    client_cui VARCHAR(32) NOT NULL,
    commission DECIMAL(8,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY commissions_supplier_client_unique (supplier_cui, client_cui)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO partners (cui, denumire, created_at, updated_at)
SELECT CAST(p.cui AS CHAR), p.denumire_firma, NOW(), NOW()
FROM parteneri p
ON DUPLICATE KEY UPDATE denumire = VALUES(denumire), updated_at = NOW();

INSERT INTO commissions (supplier_cui, client_cui, commission, created_at, updated_at)
SELECT CAST(c.furnizor AS CHAR), CAST(c.client AS CHAR), c.comision, NOW(), NOW()
FROM comisioane c
ON DUPLICATE KEY UPDATE commission = VALUES(commission), updated_at = NOW();
