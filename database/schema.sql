-- Schema ERP Romania (MySQL)

CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    denumire VARCHAR(255) NOT NULL,
    tip_firma ENUM('SRL', 'SA', 'PFA', 'II', 'IF') NOT NULL,
    cui VARCHAR(64) NOT NULL UNIQUE,
    nr_reg_comertului VARCHAR(64) NOT NULL,
    platitor_tva TINYINT(1) NOT NULL DEFAULT 0,
    adresa VARCHAR(255) NOT NULL,
    localitate VARCHAR(255) NOT NULL,
    judet VARCHAR(255) NOT NULL,
    tara VARCHAR(255) NOT NULL DEFAULT 'România',
    email VARCHAR(255) NOT NULL,
    telefon VARCHAR(64) NOT NULL,
    banca VARCHAR(255) NULL,
    iban VARCHAR(64) NULL,
    tip_companie ENUM('client', 'furnizor', 'intermediar') NOT NULL,
    activ TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_user (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_role_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_user_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_suppliers (
    user_id BIGINT UNSIGNED NOT NULL,
    supplier_cui VARCHAR(32) NOT NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (user_id, supplier_cui),
    CONSTRAINT fk_user_suppliers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(191) NOT NULL UNIQUE,
    value JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cui VARCHAR(32) NOT NULL UNIQUE,
    denumire VARCHAR(255) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    client_cui VARCHAR(32) NOT NULL,
    commission DECIMAL(8,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY commissions_supplier_client_unique (supplier_cui, client_cui)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices_in (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(64) NOT NULL,
    invoice_series VARCHAR(32) NOT NULL DEFAULT "",
    invoice_no VARCHAR(32) NOT NULL DEFAULT "",
    supplier_cui VARCHAR(32) NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    customer_cui VARCHAR(32) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    selected_client_cui VARCHAR(32) NULL,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    currency VARCHAR(8) NOT NULL,
    total_without_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    xml_path VARCHAR(255) NULL,
    packages_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    packages_confirmed_at DATETIME NULL,
    fgo_series VARCHAR(32) NULL,
    fgo_number VARCHAR(32) NULL,
    fgo_date DATE NULL,
    fgo_generated_at DATETIME NULL,
    fgo_link VARCHAR(255) NULL,
    fgo_storno_series VARCHAR(32) NULL,
    fgo_storno_number VARCHAR(32) NULL,
    fgo_storno_link VARCHAR(255) NULL,
    fgo_storno_at DATETIME NULL,
    order_note_no INT NULL,
    order_note_date DATE NULL,
    commission_percent DECIMAL(6,2) NULL,
    supplier_request_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    package_no INT NOT NULL DEFAULT 0,
    label VARCHAR(64) NULL,
    vat_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10000;

CREATE TABLE invoice_in_lines (
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

CREATE TABLE payments_in (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_cui VARCHAR(32) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_at DATE NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_in_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_in_id BIGINT UNSIGNED NOT NULL,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments_out (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_at DATE NOT NULL,
    notes TEXT NULL,
    email_sent_at DATETIME NULL,
    email_status VARCHAR(32) NULL,
    email_message TEXT NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_out_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_out_id BIGINT UNSIGNED NOT NULL,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    invoice_numbers TEXT NULL,
    generated_at DATETIME NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
