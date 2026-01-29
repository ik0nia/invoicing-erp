CREATE TABLE IF NOT EXISTS invoices_in (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(64) NOT NULL,
    supplier_cui VARCHAR(32) NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    customer_cui VARCHAR(32) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    currency VARCHAR(8) NOT NULL,
    total_without_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    xml_path VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(64) NULL,
    vat_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10000;

CREATE TABLE IF NOT EXISTS invoice_in_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NULL,
    line_no VARCHAR(32) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_code VARCHAR(16) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    line_total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
