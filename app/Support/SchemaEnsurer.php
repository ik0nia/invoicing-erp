<?php

namespace App\Support;

class SchemaEnsurer
{
    private static bool $ensured = false;
    private static array $tableCache = [];
    private static array $columnCache = [];
    private static array $indexCache = [];

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        self::runStep('audit_log_table', static function (): void {
            self::ensureAuditLogTable();
        });

        self::runStep('packages_saga_status', static function (): void {
            self::ensurePackagesSagaStatusColumn();
        });

        self::runStep('partner_flags', static function (): void {
            self::ensurePartnerFlags();
        });

        self::runStep('companies_legal_contract_columns', static function (): void {
            self::ensureCompaniesLegalContractColumns();
        });

        self::runStep('companies_profile_columns', static function (): void {
            self::ensureCompaniesProfileColumns();
        });

        self::runStep('partners_profile_columns', static function (): void {
            self::ensurePartnersProfileColumns();
        });

        self::runStep('enrollment_links_table', static function (): void {
            self::ensureEnrollmentLinksTable();
        });

        self::runStep('partner_relations_table', static function (): void {
            self::ensurePartnerRelationsTable();
        });

        self::runStep('partner_contacts_table', static function (): void {
            self::ensurePartnerContactsTable();
        });

        self::runStep('contract_templates_table', static function (): void {
            self::ensureContractTemplatesTable();
        });

        self::runStep('contracts_table', static function (): void {
            self::ensureContractsTable();
        });

        self::runStep('document_registry_table', static function (): void {
            self::ensureDocumentRegistryTable();
        });

        self::runStep('contracts_doc_no_columns', static function (): void {
            self::ensureContractsDocNoColumns();
        });

        self::runStep('relation_documents_table', static function (): void {
            self::ensureRelationDocumentsTable();
        });
    }

    public static function ensureAuditLogTable(): void
    {
        if (!self::tableExists('audit_log')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS audit_log (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'audit_log_create'
            );
            unset(self::$tableCache['audit_log']);
            self::$tableCache['audit_log'] = self::tableExists('audit_log');
        }

        if (self::tableExists('audit_log')) {
            self::ensureIndex(
                'audit_log',
                'idx_action_created_at',
                'ALTER TABLE audit_log ADD INDEX idx_action_created_at (action, created_at)'
            );
            self::ensureIndex(
                'audit_log',
                'idx_entity',
                'ALTER TABLE audit_log ADD INDEX idx_entity (entity_type, entity_id)'
            );
            self::ensureIndex(
                'audit_log',
                'idx_actor',
                'ALTER TABLE audit_log ADD INDEX idx_actor (actor_user_id, created_at)'
            );
        }
    }

    public static function ensurePackagesSagaStatusColumn(): bool
    {
        if (!self::tableExists('packages')) {
            return false;
        }
        if (!self::columnExists('packages', 'saga_status')) {
            self::safeExecute(
                'ALTER TABLE packages ADD COLUMN saga_status VARCHAR(16) NULL AFTER saga_value',
                [],
                'packages_add_saga_status'
            );
            unset(self::$columnCache['packages.saga_status']);
            self::$columnCache['packages.saga_status'] = self::columnExists('packages', 'saga_status');
        }

        return self::columnExists('packages', 'saga_status');
    }

    public static function ensurePartnerFlags(): void
    {
        if (!self::tableExists('partners')) {
            return;
        }
        if (!self::columnExists('partners', 'is_supplier')) {
            self::safeExecute(
                'ALTER TABLE partners ADD COLUMN is_supplier TINYINT(1) NOT NULL DEFAULT 0 AFTER denumire',
                [],
                'partners_add_is_supplier'
            );
            unset(self::$columnCache['partners.is_supplier']);
        }
        if (!self::columnExists('partners', 'is_client')) {
            self::safeExecute(
                'ALTER TABLE partners ADD COLUMN is_client TINYINT(1) NOT NULL DEFAULT 0 AFTER is_supplier',
                [],
                'partners_add_is_client'
            );
            unset(self::$columnCache['partners.is_client']);
        }
    }

    public static function ensureCompaniesProfileColumns(): void
    {
        if (!self::tableExists('companies')) {
            return;
        }

        $columns = [
            'representative_name' => 'ALTER TABLE companies ADD COLUMN representative_name VARCHAR(128) NULL',
            'representative_function' => 'ALTER TABLE companies ADD COLUMN representative_function VARCHAR(128) NULL',
            'bank_account' => 'ALTER TABLE companies ADD COLUMN bank_account VARCHAR(64) NULL',
        ];

        foreach ($columns as $column => $sql) {
            if (self::columnExists('companies', $column)) {
                continue;
            }
            self::safeExecute($sql, [], 'companies_add_' . $column);
            unset(self::$columnCache['companies.' . $column]);
        }
    }

    public static function ensureCompaniesLegalContractColumns(): void
    {
        if (!self::tableExists('companies')) {
            return;
        }

        $columns = [
            'legal_representative_name' => 'ALTER TABLE companies ADD COLUMN legal_representative_name VARCHAR(255) NOT NULL DEFAULT ""',
            'legal_representative_role' => 'ALTER TABLE companies ADD COLUMN legal_representative_role VARCHAR(255) NOT NULL DEFAULT ""',
            'bank_name' => 'ALTER TABLE companies ADD COLUMN bank_name VARCHAR(255) NOT NULL DEFAULT ""',
            'iban' => 'ALTER TABLE companies ADD COLUMN iban VARCHAR(64) NOT NULL DEFAULT ""',
        ];

        foreach ($columns as $column => $sql) {
            if (self::columnExists('companies', $column)) {
                continue;
            }
            self::safeExecute($sql, [], 'companies_add_' . $column);
            unset(self::$columnCache['companies.' . $column]);
        }

        if (self::columnExists('companies', 'iban')) {
            self::ensureIndex('companies', 'idx_companies_iban', 'ALTER TABLE companies ADD INDEX idx_companies_iban (iban)');
        }
    }

    public static function ensurePartnersProfileColumns(): void
    {
        if (!self::tableExists('partners')) {
            return;
        }

        $columns = [
            'representative_name' => 'ALTER TABLE partners ADD COLUMN representative_name VARCHAR(128) NULL',
            'representative_function' => 'ALTER TABLE partners ADD COLUMN representative_function VARCHAR(128) NULL',
            'bank_account' => 'ALTER TABLE partners ADD COLUMN bank_account VARCHAR(64) NULL',
            'bank_name' => 'ALTER TABLE partners ADD COLUMN bank_name VARCHAR(128) NULL',
        ];

        foreach ($columns as $column => $sql) {
            if (self::columnExists('partners', $column)) {
                continue;
            }
            self::safeExecute($sql, [], 'partners_add_' . $column);
            unset(self::$columnCache['partners.' . $column]);
        }
    }

    public static function ensureEnrollmentLinksTable(): void
    {
        if (!self::tableExists('enrollment_links')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS enrollment_links (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    type ENUM("supplier", "client") NOT NULL,
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
                    status ENUM("active", "disabled") NOT NULL DEFAULT "active",
                    expires_at DATETIME NULL,
                    confirmed_at DATETIME NULL,
                    onboarding_status ENUM("draft", "waiting_signature", "submitted", "approved", "rejected") NOT NULL DEFAULT "draft",
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'enrollment_links_create'
            );
            unset(self::$tableCache['enrollment_links']);
            self::$tableCache['enrollment_links'] = self::tableExists('enrollment_links');
        }

        if (self::tableExists('enrollment_links')) {
            self::ensureIndex('enrollment_links', 'idx_enrollment_status', 'ALTER TABLE enrollment_links ADD INDEX idx_enrollment_status (status)');
            self::ensureIndex('enrollment_links', 'idx_enrollment_supplier', 'ALTER TABLE enrollment_links ADD INDEX idx_enrollment_supplier (supplier_cui)');
            self::ensureIndex('enrollment_links', 'idx_enrollment_created', 'ALTER TABLE enrollment_links ADD INDEX idx_enrollment_created (created_at)');
            if (!self::columnExists('enrollment_links', 'partner_cui')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN partner_cui VARCHAR(32) NULL AFTER supplier_cui', [], 'enrollment_links_partner_cui');
                unset(self::$columnCache['enrollment_links.partner_cui']);
            }
            if (!self::columnExists('enrollment_links', 'relation_supplier_cui')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN relation_supplier_cui VARCHAR(32) NULL AFTER partner_cui', [], 'enrollment_links_relation_supplier');
                unset(self::$columnCache['enrollment_links.relation_supplier_cui']);
            }
            if (!self::columnExists('enrollment_links', 'relation_client_cui')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN relation_client_cui VARCHAR(32) NULL AFTER relation_supplier_cui', [], 'enrollment_links_relation_client');
                unset(self::$columnCache['enrollment_links.relation_client_cui']);
            }
            if (!self::columnExists('enrollment_links', 'permissions_json')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN permissions_json TEXT NULL AFTER prefill_json', [], 'enrollment_links_permissions');
                unset(self::$columnCache['enrollment_links.permissions_json']);
            }
            if (!self::columnExists('enrollment_links', 'current_step')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN current_step TINYINT NOT NULL DEFAULT 1 AFTER uses', [], 'enrollment_links_current_step');
                unset(self::$columnCache['enrollment_links.current_step']);
            }
            if (!self::columnExists('enrollment_links', 'last_used_at')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN last_used_at DATETIME NULL AFTER confirmed_at', [], 'enrollment_links_last_used');
                unset(self::$columnCache['enrollment_links.last_used_at']);
            }
            if (!self::columnExists('enrollment_links', 'updated_at')) {
                self::safeExecute('ALTER TABLE enrollment_links ADD COLUMN updated_at DATETIME NULL AFTER created_at', [], 'enrollment_links_updated_at');
                unset(self::$columnCache['enrollment_links.updated_at']);
            }
            if (!self::columnExists('enrollment_links', 'onboarding_status')) {
                self::safeExecute(
                    'ALTER TABLE enrollment_links ADD COLUMN onboarding_status ENUM("draft", "waiting_signature", "submitted", "approved", "rejected") NOT NULL DEFAULT "draft" AFTER confirmed_at',
                    [],
                    'enrollment_links_onboarding_status'
                );
                unset(self::$columnCache['enrollment_links.onboarding_status']);
            }
            if (!self::columnExists('enrollment_links', 'submitted_at')) {
                self::safeExecute(
                    'ALTER TABLE enrollment_links ADD COLUMN submitted_at DATETIME NULL AFTER onboarding_status',
                    [],
                    'enrollment_links_submitted_at'
                );
                unset(self::$columnCache['enrollment_links.submitted_at']);
            }
            if (!self::columnExists('enrollment_links', 'approved_at')) {
                self::safeExecute(
                    'ALTER TABLE enrollment_links ADD COLUMN approved_at DATETIME NULL AFTER submitted_at',
                    [],
                    'enrollment_links_approved_at'
                );
                unset(self::$columnCache['enrollment_links.approved_at']);
            }
            if (!self::columnExists('enrollment_links', 'approved_by_user_id')) {
                self::safeExecute(
                    'ALTER TABLE enrollment_links ADD COLUMN approved_by_user_id INT NULL AFTER approved_at',
                    [],
                    'enrollment_links_approved_by'
                );
                unset(self::$columnCache['enrollment_links.approved_by_user_id']);
            }
            if (!self::columnExists('enrollment_links', 'checkbox_confirmed')) {
                self::safeExecute(
                    'ALTER TABLE enrollment_links ADD COLUMN checkbox_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER approved_by_user_id',
                    [],
                    'enrollment_links_checkbox_confirmed'
                );
                unset(self::$columnCache['enrollment_links.checkbox_confirmed']);
            }
            self::ensureIndex('enrollment_links', 'idx_enrollment_onboarding', 'ALTER TABLE enrollment_links ADD INDEX idx_enrollment_onboarding (onboarding_status)');
        }
    }

    public static function ensurePartnerRelationsTable(): void
    {
        if (!self::tableExists('partner_relations')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS partner_relations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    supplier_cui VARCHAR(32) NOT NULL,
                    client_cui VARCHAR(32) NOT NULL,
                    invoice_inbox_email VARCHAR(128) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY partner_relation_unique (supplier_cui, client_cui)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'partner_relations_create'
            );
            unset(self::$tableCache['partner_relations']);
            self::$tableCache['partner_relations'] = self::tableExists('partner_relations');
        }
    }

    public static function ensurePartnerContactsTable(): void
    {
        if (!self::tableExists('partner_contacts')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS partner_contacts (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'partner_contacts_create'
            );
            unset(self::$tableCache['partner_contacts']);
            self::$tableCache['partner_contacts'] = self::tableExists('partner_contacts');
        }

        if (self::tableExists('partner_contacts')) {
            self::ensureIndex('partner_contacts', 'idx_contacts_partner', 'ALTER TABLE partner_contacts ADD INDEX idx_contacts_partner (partner_cui)');
            self::ensureIndex('partner_contacts', 'idx_contacts_relation', 'ALTER TABLE partner_contacts ADD INDEX idx_contacts_relation (supplier_cui, client_cui)');
            self::ensureIndex('partner_contacts', 'idx_contacts_created', 'ALTER TABLE partner_contacts ADD INDEX idx_contacts_created (created_at)');
            if (!self::columnExists('partner_contacts', 'is_primary')) {
                self::safeExecute(
                    'ALTER TABLE partner_contacts ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
                    [],
                    'partner_contacts_is_primary'
                );
                unset(self::$columnCache['partner_contacts.is_primary']);
            }
        }
    }

    public static function ensureContractTemplatesTable(): void
    {
        if (!self::tableExists('contract_templates')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS contract_templates (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(128) NOT NULL,
                    template_type VARCHAR(32) NOT NULL,
                    doc_type VARCHAR(64) NULL,
                    applies_to ENUM("client", "supplier", "both") NOT NULL DEFAULT "both",
                    auto_on_enrollment TINYINT(1) NOT NULL DEFAULT 0,
                    required_onboarding TINYINT(1) NOT NULL DEFAULT 0,
                    doc_kind ENUM("contract", "acord", "anexa") NOT NULL DEFAULT "contract",
                    priority INT NOT NULL DEFAULT 100,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    stamp_image_path VARCHAR(255) NULL,
                    stamp_image_meta TEXT NULL,
                    html_content TEXT NULL,
                    created_by_user_id INT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    INDEX idx_templates_type (template_type),
                    INDEX idx_templates_doc_type (doc_type),
                    INDEX idx_templates_auto (auto_on_enrollment, applies_to),
                    INDEX idx_templates_active (is_active),
                    INDEX idx_templates_priority (priority),
                    INDEX idx_templates_stamp_path (stamp_image_path)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'contract_templates_create'
            );
            unset(self::$tableCache['contract_templates']);
            self::$tableCache['contract_templates'] = self::tableExists('contract_templates');
        }

        if (self::tableExists('contract_templates')) {
            if (!self::columnExists('contract_templates', 'applies_to')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN applies_to ENUM("client", "supplier", "both") NOT NULL DEFAULT "both" AFTER template_type', [], 'contract_templates_applies_to');
                unset(self::$columnCache['contract_templates.applies_to']);
            }
            if (!self::columnExists('contract_templates', 'auto_on_enrollment')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN auto_on_enrollment TINYINT(1) NOT NULL DEFAULT 0 AFTER applies_to', [], 'contract_templates_auto');
                unset(self::$columnCache['contract_templates.auto_on_enrollment']);
            }
            if (!self::columnExists('contract_templates', 'required_onboarding')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN required_onboarding TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_on_enrollment', [], 'contract_templates_required');
                unset(self::$columnCache['contract_templates.required_onboarding']);
            }
            if (!self::columnExists('contract_templates', 'doc_kind')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN doc_kind ENUM("contract", "acord", "anexa") NOT NULL DEFAULT "contract" AFTER required_onboarding', [], 'contract_templates_doc_kind');
                unset(self::$columnCache['contract_templates.doc_kind']);
            }
            if (!self::columnExists('contract_templates', 'doc_type')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN doc_type VARCHAR(64) NULL AFTER template_type', [], 'contract_templates_doc_type');
                unset(self::$columnCache['contract_templates.doc_type']);
            }
            if (!self::columnExists('contract_templates', 'priority')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN priority INT NOT NULL DEFAULT 100 AFTER doc_kind', [], 'contract_templates_priority');
                unset(self::$columnCache['contract_templates.priority']);
            }
            if (!self::columnExists('contract_templates', 'is_active')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER priority', [], 'contract_templates_active');
                unset(self::$columnCache['contract_templates.is_active']);
            }
            if (!self::columnExists('contract_templates', 'stamp_image_path')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN stamp_image_path VARCHAR(255) NULL AFTER is_active', [], 'contract_templates_stamp_image_path');
                unset(self::$columnCache['contract_templates.stamp_image_path']);
            }
            if (!self::columnExists('contract_templates', 'stamp_image_meta')) {
                self::safeExecute('ALTER TABLE contract_templates ADD COLUMN stamp_image_meta TEXT NULL AFTER stamp_image_path', [], 'contract_templates_stamp_image_meta');
                unset(self::$columnCache['contract_templates.stamp_image_meta']);
            }
            if (self::columnExists('contract_templates', 'doc_type')) {
                self::safeExecute(
                    'UPDATE contract_templates
                     SET doc_type = COALESCE(NULLIF(doc_type, ""), NULLIF(template_type, ""), NULLIF(doc_kind, ""), "contract")
                     WHERE doc_type IS NULL OR doc_type = ""',
                    [],
                    'contract_templates_doc_type_backfill'
                );
            }
            if (self::columnExists('contract_templates', 'doc_type') && self::columnExists('contract_templates', 'doc_kind')) {
                self::safeExecute(
                    'UPDATE contract_templates
                     SET doc_type = "contract"
                     WHERE LOWER(TRIM(doc_kind)) = "contract"
                       AND (doc_type IS NULL OR LOWER(TRIM(doc_type)) <> "contract")',
                    [],
                    'contract_templates_contract_doc_type_normalize'
                );
            }
            if (self::columnExists('contract_templates', 'required_onboarding') && self::columnExists('contract_templates', 'auto_on_enrollment')) {
                self::safeExecute(
                    'UPDATE contract_templates
                     SET required_onboarding = 1
                     WHERE auto_on_enrollment = 1 AND required_onboarding = 0',
                    [],
                    'contract_templates_required_backfill'
                );
            }
            if (self::columnExists('contract_templates', 'template_type')) {
                self::ensureIndex('contract_templates', 'idx_templates_type', 'ALTER TABLE contract_templates ADD INDEX idx_templates_type (template_type)');
            }
            if (self::columnExists('contract_templates', 'auto_on_enrollment') && self::columnExists('contract_templates', 'applies_to')) {
                self::ensureIndex('contract_templates', 'idx_templates_auto', 'ALTER TABLE contract_templates ADD INDEX idx_templates_auto (auto_on_enrollment, applies_to)');
            }
            if (self::columnExists('contract_templates', 'is_active')) {
                self::ensureIndex('contract_templates', 'idx_templates_active', 'ALTER TABLE contract_templates ADD INDEX idx_templates_active (is_active)');
            }
            if (self::columnExists('contract_templates', 'priority')) {
                self::ensureIndex('contract_templates', 'idx_templates_priority', 'ALTER TABLE contract_templates ADD INDEX idx_templates_priority (priority)');
            }
            if (self::columnExists('contract_templates', 'doc_type')) {
                self::ensureIndex('contract_templates', 'idx_templates_doc_type', 'ALTER TABLE contract_templates ADD INDEX idx_templates_doc_type (doc_type)');
            }
            if (self::columnExists('contract_templates', 'stamp_image_path')) {
                self::ensureIndex('contract_templates', 'idx_templates_stamp_path', 'ALTER TABLE contract_templates ADD INDEX idx_templates_stamp_path (stamp_image_path)');
            }
        }
    }

    public static function ensureContractsTable(): void
    {
        if (!self::tableExists('contracts')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS contracts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    template_id BIGINT UNSIGNED NULL,
                    partner_cui VARCHAR(32) NULL,
                    supplier_cui VARCHAR(32) NULL,
                    client_cui VARCHAR(32) NULL,
                    title VARCHAR(255) NOT NULL,
                    doc_type VARCHAR(64) NOT NULL DEFAULT "contract",
                    contract_date DATE NULL,
                    doc_no INT NULL,
                    doc_series VARCHAR(16) NULL,
                    doc_full_no VARCHAR(64) NULL,
                    doc_assigned_at DATETIME NULL,
                    required_onboarding TINYINT(1) NOT NULL DEFAULT 0,
                    status ENUM("draft", "generated", "sent", "signed_uploaded", "approved") NOT NULL DEFAULT "draft",
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'contracts_create'
            );
            unset(self::$tableCache['contracts']);
            self::$tableCache['contracts'] = self::tableExists('contracts');
        }

        if (self::tableExists('contracts')) {
            if (self::columnExists('contracts', 'status')) {
                self::ensureIndex('contracts', 'idx_contracts_status', 'ALTER TABLE contracts ADD INDEX idx_contracts_status (status)');
            }
            if (self::columnExists('contracts', 'partner_cui')) {
                self::ensureIndex('contracts', 'idx_contracts_partner', 'ALTER TABLE contracts ADD INDEX idx_contracts_partner (partner_cui)');
            }
            if (self::columnExists('contracts', 'supplier_cui') && self::columnExists('contracts', 'client_cui')) {
                self::ensureIndex('contracts', 'idx_contracts_relation', 'ALTER TABLE contracts ADD INDEX idx_contracts_relation (supplier_cui, client_cui)');
            }
            if (self::columnExists('contracts', 'created_at')) {
                self::ensureIndex('contracts', 'idx_contracts_created', 'ALTER TABLE contracts ADD INDEX idx_contracts_created (created_at)');
            }
            if (!self::columnExists('contracts', 'doc_type')) {
                self::safeExecute(
                    'ALTER TABLE contracts ADD COLUMN doc_type VARCHAR(64) NOT NULL DEFAULT "contract" AFTER title',
                    [],
                    'contracts_doc_type'
                );
                unset(self::$columnCache['contracts.doc_type']);
            }
            if (!self::columnExists('contracts', 'contract_date')) {
                self::safeExecute(
                    'ALTER TABLE contracts ADD COLUMN contract_date DATE NULL AFTER doc_type',
                    [],
                    'contracts_contract_date'
                );
                unset(self::$columnCache['contracts.contract_date']);
            }
            if (!self::columnExists('contracts', 'required_onboarding')) {
                self::safeExecute(
                    'ALTER TABLE contracts ADD COLUMN required_onboarding TINYINT(1) NOT NULL DEFAULT 0 AFTER contract_date',
                    [],
                    'contracts_required_onboarding'
                );
                unset(self::$columnCache['contracts.required_onboarding']);
            }
            if (!self::columnExists('contracts', 'generated_pdf_path')) {
                self::safeExecute(
                    'ALTER TABLE contracts ADD COLUMN generated_pdf_path VARCHAR(255) NULL AFTER generated_file_path',
                    [],
                    'contracts_generated_pdf_path'
                );
                unset(self::$columnCache['contracts.generated_pdf_path']);
            }
            if (!self::columnExists('contracts', 'signed_upload_path')) {
                self::safeExecute(
                    'ALTER TABLE contracts ADD COLUMN signed_upload_path VARCHAR(255) NULL AFTER signed_file_path',
                    [],
                    'contracts_signed_upload_path'
                );
                unset(self::$columnCache['contracts.signed_upload_path']);
            }
            if (self::columnExists('contracts', 'doc_type')) {
                if (self::tableExists('contract_templates')) {
                    $hasTemplateDocType = self::columnExists('contract_templates', 'doc_type');
                    $templateDocTypeSql = $hasTemplateDocType ? 'NULLIF(t.doc_type, "")' : 'NULL';
                    self::safeExecute(
                        'UPDATE contracts c
                         LEFT JOIN contract_templates t ON t.id = c.template_id
                         SET c.doc_type = COALESCE(NULLIF(c.doc_type, ""), ' . $templateDocTypeSql . ', NULLIF(t.template_type, ""), NULLIF(t.doc_kind, ""), "contract")
                         WHERE c.doc_type IS NULL OR c.doc_type = ""',
                        [],
                        'contracts_doc_type_backfill'
                    );
                } else {
                    self::safeExecute(
                        'UPDATE contracts SET doc_type = "contract" WHERE doc_type IS NULL OR doc_type = ""',
                        [],
                        'contracts_doc_type_default'
                    );
                }
            }
            if (self::columnExists('contracts', 'contract_date')) {
                self::safeExecute(
                    'UPDATE contracts
                     SET contract_date = DATE(created_at)
                     WHERE contract_date IS NULL',
                    [],
                    'contracts_contract_date_backfill'
                );
            }
            if (self::columnExists('contracts', 'signed_upload_path') && self::columnExists('contracts', 'signed_file_path')) {
                self::safeExecute(
                    'UPDATE contracts
                     SET signed_upload_path = signed_file_path
                     WHERE (signed_upload_path IS NULL OR signed_upload_path = "")
                       AND signed_file_path IS NOT NULL
                       AND signed_file_path <> ""',
                    [],
                    'contracts_signed_upload_backfill'
                );
            }
            if (self::columnExists('contracts', 'doc_type') && self::columnExists('contracts', 'contract_date')) {
                self::ensureIndex('contracts', 'idx_contracts_doc_date', 'ALTER TABLE contracts ADD INDEX idx_contracts_doc_date (doc_type, contract_date)');
            }
        }
    }

    public static function ensureDocumentRegistryTable(): void
    {
        if (!self::tableExists('document_registry')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS document_registry (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    doc_type VARCHAR(64) NOT NULL,
                    series VARCHAR(16) NULL,
                    next_no INT NOT NULL DEFAULT 1,
                    start_no INT NOT NULL DEFAULT 1,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_document_registry_doc_type (doc_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'document_registry_create'
            );
            unset(self::$tableCache['document_registry']);
            self::$tableCache['document_registry'] = self::tableExists('document_registry');
        }

        if (self::tableExists('document_registry')) {
            self::ensureIndex(
                'document_registry',
                'uq_document_registry_doc_type',
                'ALTER TABLE document_registry ADD UNIQUE INDEX uq_document_registry_doc_type (doc_type)'
            );
            if (!self::columnExists('document_registry', 'series')) {
                self::safeExecute('ALTER TABLE document_registry ADD COLUMN series VARCHAR(16) NULL AFTER doc_type', [], 'document_registry_series');
                unset(self::$columnCache['document_registry.series']);
            }
            if (!self::columnExists('document_registry', 'next_no')) {
                self::safeExecute('ALTER TABLE document_registry ADD COLUMN next_no INT NOT NULL DEFAULT 1 AFTER series', [], 'document_registry_next_no');
                unset(self::$columnCache['document_registry.next_no']);
            }
            if (!self::columnExists('document_registry', 'start_no')) {
                self::safeExecute('ALTER TABLE document_registry ADD COLUMN start_no INT NOT NULL DEFAULT 1 AFTER next_no', [], 'document_registry_start_no');
                unset(self::$columnCache['document_registry.start_no']);
            }
            if (!self::columnExists('document_registry', 'updated_at')) {
                self::safeExecute(
                    'ALTER TABLE document_registry ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER start_no',
                    [],
                    'document_registry_updated_at'
                );
                unset(self::$columnCache['document_registry.updated_at']);
            }
            self::safeExecute(
                'UPDATE document_registry
                 SET start_no = CASE WHEN start_no < 1 THEN 1 ELSE start_no END,
                     next_no = CASE WHEN next_no < 1 THEN 1 WHEN next_no < start_no THEN start_no ELSE next_no END',
                [],
                'document_registry_numbers_backfill'
            );
            $globalRow = Database::fetchOne(
                'SELECT id FROM document_registry WHERE doc_type = :doc_type LIMIT 1',
                ['doc_type' => 'global']
            );
            if (!$globalRow) {
                $seed = Database::fetchOne(
                    'SELECT series, start_no, next_no
                     FROM document_registry
                     ORDER BY next_no DESC, id DESC
                     LIMIT 1'
                ) ?? [];
                $startNo = max(1, (int) ($seed['start_no'] ?? 1));
                $nextNo = max($startNo, (int) ($seed['next_no'] ?? $startNo));
                $series = trim((string) ($seed['series'] ?? ''));
                self::safeExecute(
                    'INSERT INTO document_registry (doc_type, series, next_no, start_no, updated_at)
                     VALUES (:doc_type, :series, :next_no, :start_no, :updated_at)',
                    [
                        'doc_type' => 'global',
                        'series' => $series !== '' ? $series : null,
                        'next_no' => $nextNo,
                        'start_no' => $startNo,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'document_registry_seed_global'
                );
            }
        }
    }

    public static function ensureContractsDocNoColumns(): void
    {
        if (!self::tableExists('contracts')) {
            return;
        }

        if (!self::columnExists('contracts', 'doc_no')) {
            self::safeExecute('ALTER TABLE contracts ADD COLUMN doc_no INT NULL AFTER contract_date', [], 'contracts_doc_no');
            unset(self::$columnCache['contracts.doc_no']);
        }
        if (!self::columnExists('contracts', 'doc_series')) {
            self::safeExecute('ALTER TABLE contracts ADD COLUMN doc_series VARCHAR(16) NULL AFTER doc_no', [], 'contracts_doc_series');
            unset(self::$columnCache['contracts.doc_series']);
        }
        if (!self::columnExists('contracts', 'doc_full_no')) {
            self::safeExecute('ALTER TABLE contracts ADD COLUMN doc_full_no VARCHAR(64) NULL AFTER doc_series', [], 'contracts_doc_full_no');
            unset(self::$columnCache['contracts.doc_full_no']);
        }
        if (!self::columnExists('contracts', 'doc_assigned_at')) {
            self::safeExecute('ALTER TABLE contracts ADD COLUMN doc_assigned_at DATETIME NULL AFTER doc_full_no', [], 'contracts_doc_assigned_at');
            unset(self::$columnCache['contracts.doc_assigned_at']);
        }
        self::ensureIndex('contracts', 'idx_contracts_doc_no', 'ALTER TABLE contracts ADD INDEX idx_contracts_doc_no (doc_type, doc_no)');
    }

    public static function ensureRelationDocumentsTable(): void
    {
        if (!self::tableExists('relation_documents')) {
            self::safeExecute(
                'CREATE TABLE IF NOT EXISTS relation_documents (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                [],
                'relation_documents_create'
            );
            unset(self::$tableCache['relation_documents']);
            self::$tableCache['relation_documents'] = self::tableExists('relation_documents');
        }

        if (self::tableExists('relation_documents')) {
            self::ensureIndex('relation_documents', 'idx_relation_docs', 'ALTER TABLE relation_documents ADD INDEX idx_relation_docs (supplier_cui, client_cui)');
            self::ensureIndex('relation_documents', 'idx_relation_docs_created', 'ALTER TABLE relation_documents ADD INDEX idx_relation_docs_created (created_at)');
        }
    }

    private static function runStep(string $step, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Logger::logWarning('schema_ensure_failed', [
                'step' => $step,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }
        try {
            $exists = Database::tableExists($table);
        } catch (\Throwable $exception) {
            Logger::logWarning('schema_check_failed', [
                'check' => 'table_exists',
                'table' => $table,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
        self::$tableCache[$table] = $exists;

        return $exists;
    }

    private static function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $exists = Database::columnExists($table, $column);
        } catch (\Throwable $exception) {
            Logger::logWarning('schema_check_failed', [
                'check' => 'column_exists',
                'table' => $table,
                'column' => $column,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
        self::$columnCache[$key] = $exists;

        return $exists;
    }

    private static function indexExists(string $table, string $index): bool
    {
        $key = $table . '.' . $index;
        if (array_key_exists($key, self::$indexCache)) {
            return self::$indexCache[$key];
        }
        if (!self::tableExists($table)) {
            self::$indexCache[$key] = false;
            return false;
        }
        try {
            $row = Database::fetchOne('SHOW INDEX FROM `' . $table . '` WHERE Key_name = :name', [
                'name' => $index,
            ]);
            $exists = $row !== null;
        } catch (\Throwable $exception) {
            Logger::logWarning('schema_check_failed', [
                'check' => 'index_exists',
                'table' => $table,
                'index' => $index,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
        self::$indexCache[$key] = $exists;

        return $exists;
    }

    private static function ensureIndex(string $table, string $index, string $sql): void
    {
        if (self::indexExists($table, $index)) {
            return;
        }
        self::safeExecute($sql, [], $table . '_index_' . $index);
        unset(self::$indexCache[$table . '.' . $index]);
        self::$indexCache[$table . '.' . $index] = self::indexExists($table, $index);
    }

    private static function safeExecute(string $sql, array $params, string $step): bool
    {
        try {
            return Database::execute($sql, $params);
        } catch (\Throwable $exception) {
            Logger::logWarning('schema_ensure_failed', [
                'step' => $step,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
