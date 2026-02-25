-- Schema: tranzactii bancare importate din extrase CSV
-- Folosit pentru import ING si detectie incasari noi

CREATE TABLE IF NOT EXISTS bank_transactions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_no      VARCHAR(64)  NOT NULL DEFAULT '',
    processed_at    DATE         NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    currency        VARCHAR(8)   NOT NULL DEFAULT 'RON',
    transaction_type VARCHAR(128) NOT NULL DEFAULT '',
    counterpart_name VARCHAR(255) NOT NULL DEFAULT '',
    counterpart_address VARCHAR(255) NOT NULL DEFAULT '',
    counterpart_account VARCHAR(64) NOT NULL DEFAULT '',
    counterpart_bank VARCHAR(128) NOT NULL DEFAULT '',
    details         TEXT         NOT NULL,
    balance         DECIMAL(12,2) NULL,
    counterpart_cui VARCHAR(32)  NOT NULL DEFAULT '',
    row_hash        VARCHAR(64)  NOT NULL DEFAULT '',
    payment_in_id   BIGINT UNSIGNED NULL,
    ignored         TINYINT(1)   NOT NULL DEFAULT 0,
    imported_at     DATETIME     NOT NULL,
    created_at      DATETIME     NULL,
    UNIQUE KEY uq_row_hash (row_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
