CREATE TABLE IF NOT EXISTS payments_in (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_cui VARCHAR(32) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_at DATE NOT NULL,
    reference VARCHAR(64) NULL,
    notes TEXT NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_in_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_in_id BIGINT UNSIGNED NOT NULL,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments_out (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_at DATE NOT NULL,
    reference VARCHAR(64) NULL,
    notes TEXT NULL,
    email_sent_at DATETIME NULL,
    email_status VARCHAR(32) NULL,
    email_message TEXT NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_out_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_out_id BIGINT UNSIGNED NOT NULL,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
