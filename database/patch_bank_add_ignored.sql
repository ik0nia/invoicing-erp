-- Patch: adauga coloana ignored la bank_transactions (safe to re-run pe MySQL 8+ / MariaDB 10.3+)
ALTER TABLE bank_transactions
    ADD COLUMN IF NOT EXISTS ignored TINYINT(1) NOT NULL DEFAULT 0;
