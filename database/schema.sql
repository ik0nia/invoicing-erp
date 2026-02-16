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
    representative_name VARCHAR(128) NULL,
    representative_function VARCHAR(128) NULL,
    banca VARCHAR(255) NULL,
    iban VARCHAR(64) NULL,
    bank_account VARCHAR(64) NULL,
    bank_name VARCHAR(128) NULL,
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
    show_payment_details TINYINT(1) NOT NULL DEFAULT 1,
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
    representative_name VARCHAR(128) NULL,
    representative_function VARCHAR(128) NULL,
    bank_account VARCHAR(64) NULL,
    bank_name VARCHAR(128) NULL,
    default_commission DECIMAL(6,2) NOT NULL DEFAULT 0,
    is_supplier TINYINT(1) NOT NULL DEFAULT 0,
    is_client TINYINT(1) NOT NULL DEFAULT 0,
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
    saga_value DECIMAL(12,2) NULL,
    saga_status VARCHAR(16) NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10000;

CREATE TABLE invoice_in_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_in_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NULL,
    line_no VARCHAR(32) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    cod_saga VARCHAR(64) NULL,
    stock_saga DECIMAL(12,3) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_code VARCHAR(16) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    line_total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saga_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_key VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    cod_saga VARCHAR(64) NOT NULL,
    stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
    updated_at DATETIME NULL
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

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_user_id INT NULL,
    actor_role VARCHAR(32) NULL,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id BIGINT NULL,
    context_json TEXT NULL,
    INDEX idx_action_created_at (action, created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enrollment_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    type ENUM('supplier', 'client') NOT NULL,
    created_by_user_id INT NULL,
    supplier_cui VARCHAR(32) NULL,
    partner_cui VARCHAR(32) NULL,
    relation_supplier_cui VARCHAR(32) NULL,
    relation_client_cui VARCHAR(32) NULL,
    commission_percent DECIMAL(8,4) NULL,
    prefill_json TEXT NULL,
    permissions_json TEXT NULL,
    max_uses INT NOT NULL DEFAULT 1,
    uses INT NOT NULL DEFAULT 0,
    current_step TINYINT NOT NULL DEFAULT 1,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    expires_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    onboarding_status ENUM('draft', 'waiting_signature', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by_user_id INT NULL,
    checkbox_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_enrollment_status (status),
    INDEX idx_enrollment_supplier (supplier_cui),
    INDEX idx_enrollment_created (created_at),
    INDEX idx_enrollment_onboarding (onboarding_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partner_relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    client_cui VARCHAR(32) NOT NULL,
    invoice_inbox_email VARCHAR(128) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY partner_relation_unique (supplier_cui, client_cui)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE partner_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_cui VARCHAR(32) NULL,
    supplier_cui VARCHAR(32) NULL,
    client_cui VARCHAR(32) NULL,
    name VARCHAR(128) NOT NULL,
    email VARCHAR(128) NULL,
    phone VARCHAR(64) NULL,
    role VARCHAR(64) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contacts_partner (partner_cui),
    INDEX idx_contacts_relation (supplier_cui, client_cui),
    INDEX idx_contacts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contract_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    template_type VARCHAR(32) NOT NULL,
    doc_type VARCHAR(64) NULL,
    applies_to ENUM('client', 'supplier', 'both') NOT NULL DEFAULT 'both',
    auto_on_enrollment TINYINT(1) NOT NULL DEFAULT 0,
    required_onboarding TINYINT(1) NOT NULL DEFAULT 0,
    doc_kind ENUM('contract', 'acord', 'anexa') NOT NULL DEFAULT 'contract',
    priority INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    html_content TEXT NULL,
    created_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_templates_type (template_type),
    INDEX idx_templates_doc_type (doc_type),
    INDEX idx_templates_auto (auto_on_enrollment, applies_to),
    INDEX idx_templates_active (is_active),
    INDEX idx_templates_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NULL,
    partner_cui VARCHAR(32) NULL,
    supplier_cui VARCHAR(32) NULL,
    client_cui VARCHAR(32) NULL,
    title VARCHAR(255) NOT NULL,
    doc_type VARCHAR(64) NOT NULL DEFAULT 'contract',
    contract_date DATE NULL,
    doc_no INT NULL,
    doc_series VARCHAR(16) NULL,
    doc_full_no VARCHAR(64) NULL,
    doc_assigned_at DATETIME NULL,
    required_onboarding TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'generated', 'sent', 'signed_uploaded', 'approved') NOT NULL DEFAULT 'draft',
    generated_file_path VARCHAR(255) NULL,
    generated_pdf_path VARCHAR(255) NULL,
    signed_file_path VARCHAR(255) NULL,
    signed_upload_path VARCHAR(255) NULL,
    metadata_json TEXT NULL,
    created_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_contracts_status (status),
    INDEX idx_contracts_partner (partner_cui),
    INDEX idx_contracts_relation (supplier_cui, client_cui),
    INDEX idx_contracts_created (created_at),
    INDEX idx_contracts_doc_date (doc_type, contract_date),
    INDEX idx_contracts_doc_no (doc_type, doc_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_registry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(64) NOT NULL,
    series VARCHAR(16) NULL,
    next_no INT NOT NULL DEFAULT 1,
    start_no INT NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_registry_doc_type (doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE relation_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_cui VARCHAR(32) NOT NULL,
    client_cui VARCHAR(32) NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    metadata_json TEXT NULL,
    created_by_user_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_relation_docs (supplier_cui, client_cui),
    INDEX idx_relation_docs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
