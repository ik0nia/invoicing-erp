<?php

namespace App\Domain\Payments\Services;

use App\Support\Database;

class BankImportService
{
    /** Parsare CSV ING (separator ";", encoding UTF-8 sau ISO-8859-1) */
    public function parseCsv(string $content): array
    {
        // Normalizeaza encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        // Scoate BOM
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = preg_split('/\r?\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        // Prima linie = header
        $header = str_getcsv(array_shift($lines), ';');
        $header = array_map(static fn($h) => mb_strtolower(trim((string) $h), 'UTF-8'), $header);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, ';');
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($cols[$i] ?? ''));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** Normalizeaza suma: '5.049,45' sau '5049,45' sau '5049.45' → float */
    public function parseAmount(string $raw): float
    {
        $raw = trim($raw);
        // Format european: 5.049,45
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }

    /** Extrage data din format DD.MM.YYYY → YYYY-MM-DD */
    public function parseDate(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return $raw;
    }

    /** Normalizeaza un rand CSV ING intr-un array structurat */
    public function normalizeRow(array $row): array
    {
        $amountRaw = $row['suma'] ?? $row['amount'] ?? '0';
        $dateRaw   = $row['data procesarii'] ?? $row['data'] ?? '';
        $cui       = preg_replace('/\D+/', '', (string) ($row['cui contrapartida'] ?? ''));

        return [
            'account_no'          => (string) ($row['numar cont'] ?? ''),
            'processed_at'        => $this->parseDate($dateRaw),
            'amount'              => $this->parseAmount($amountRaw),
            'currency'            => strtoupper(trim((string) ($row['valuta'] ?? 'RON'))),
            'transaction_type'    => (string) ($row['tip tranzactie'] ?? $row['tip tranzactie '] ?? ''),
            'counterpart_name'    => (string) ($row['nume beneficiar/ordonator'] ?? ''),
            'counterpart_address' => (string) ($row['adresa beneficiar/ordonator'] ?? ''),
            'counterpart_account' => (string) ($row['cont beneficiar/ordonator'] ?? ''),
            'counterpart_bank'    => (string) ($row['banca beneficiar/ordonator'] ?? ''),
            'details'             => (string) ($row['detalii tranzactie'] ?? ''),
            'balance'             => isset($row['sold intermediar']) && $row['sold intermediar'] !== ''
                ? $this->parseAmount($row['sold intermediar'])
                : null,
            'counterpart_cui'     => $cui,
        ];
    }

    /** Hash unic per tranzactie pentru deduplicare */
    public function hashRow(array $normalized): string
    {
        return md5(implode('|', [
            $normalized['account_no'],
            $normalized['processed_at'],
            $normalized['amount'],
            $normalized['counterpart_name'],
            $normalized['details'],
        ]));
    }

    /** Filtreaza numai incasarile (suma > 0) */
    public function filterIncoming(array $normalized): array
    {
        return array_values(array_filter($normalized, static fn($r) => $r['amount'] > 0.001));
    }

    /**
     * Salveaza tranzactiile in DB (ignora duplicate prin UNIQUE pe row_hash).
     * Returneaza numarul de randuri nou inserate.
     */
    public function storeRows(array $normalizedRows): int
    {
        $inserted = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($normalizedRows as $row) {
            $hash = $this->hashRow($row);
            $existing = Database::fetchOne(
                'SELECT id FROM bank_transactions WHERE row_hash = :h LIMIT 1',
                ['h' => $hash]
            );
            if ($existing) {
                continue;
            }

            Database::execute(
                'INSERT INTO bank_transactions
                    (account_no, processed_at, amount, currency, transaction_type,
                     counterpart_name, counterpart_address, counterpart_account,
                     counterpart_bank, details, balance, counterpart_cui,
                     row_hash, imported_at, created_at)
                 VALUES
                    (:account_no, :processed_at, :amount, :currency, :transaction_type,
                     :counterpart_name, :counterpart_address, :counterpart_account,
                     :counterpart_bank, :details, :balance, :counterpart_cui,
                     :row_hash, :imported_at, :created_at)',
                [
                    'account_no'          => $row['account_no'],
                    'processed_at'        => $row['processed_at'],
                    'amount'              => $row['amount'],
                    'currency'            => $row['currency'],
                    'transaction_type'    => $row['transaction_type'],
                    'counterpart_name'    => $row['counterpart_name'],
                    'counterpart_address' => $row['counterpart_address'],
                    'counterpart_account' => $row['counterpart_account'],
                    'counterpart_bank'    => $row['counterpart_bank'],
                    'details'             => $row['details'],
                    'balance'             => $row['balance'],
                    'counterpart_cui'     => $row['counterpart_cui'],
                    'row_hash'            => $hash,
                    'imported_at'         => $now,
                    'created_at'          => $now,
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Potriveste un client din sistem dupa CUI sau dupa nume.
     * Returneaza ['cui' => ..., 'name' => ...] sau null.
     */
    public function matchClient(array $normalized): ?array
    {
        $cui = $normalized['counterpart_cui'];

        // 1. Match exact dupa CUI din companies
        if ($cui !== '') {
            $company = Database::fetchOne(
                'SELECT cui, denumire FROM companies WHERE cui = :cui LIMIT 1',
                ['cui' => $cui]
            );
            if ($company) {
                return ['cui' => $company['cui'], 'name' => $company['denumire']];
            }

            // 2. Match dupa CUI din partners
            $partner = Database::fetchOne(
                'SELECT cui, denumire FROM partners WHERE cui = :cui LIMIT 1',
                ['cui' => $cui]
            );
            if ($partner) {
                return ['cui' => $partner['cui'], 'name' => $partner['denumire']];
            }

            // 3. Match dupa CUI in commissions (clienti activi)
            $commission = Database::fetchOne(
                'SELECT client_cui FROM commissions WHERE client_cui = :cui LIMIT 1',
                ['cui' => $cui]
            );
            if ($commission) {
                return ['cui' => $cui, 'name' => $normalized['counterpart_name']];
            }
        }

        // 4. Match aproximativ dupa nume (LIKE)
        $name = trim($normalized['counterpart_name']);
        if ($name !== '') {
            $company = Database::fetchOne(
                'SELECT cui, denumire FROM companies WHERE denumire LIKE :name LIMIT 1',
                ['name' => '%' . $name . '%']
            );
            if ($company) {
                return ['cui' => $company['cui'], 'name' => $company['denumire']];
            }
        }

        return null;
    }

    /** Construieste propunerile din toate tranzactiile (incoming + outgoing) */
    public function buildProposals(array $normalizedRows): array
    {
        $proposals = [];

        foreach ($normalizedRows as $row) {
            $hash = $this->hashRow($row);
            $existing = Database::fetchOne(
                'SELECT id, payment_in_id, ignored FROM bank_transactions WHERE row_hash = :h LIMIT 1',
                ['h' => $hash]
            );

            $status      = 'new';
            $txId        = null;
            $paymentInId = null;
            if ($existing) {
                $txId        = (int) $existing['id'];
                $paymentInId = !empty($existing['payment_in_id']) ? (int) $existing['payment_in_id'] : null;
                if ((int) ($existing['ignored'] ?? 0) === 1) {
                    $status = 'ignored';
                } elseif ($paymentInId) {
                    $status = 'processed';
                } else {
                    $status = 'imported';
                }
            }

            $client   = $this->matchClient($row);
            $rowType  = $row['amount'] > 0.001 ? 'incoming' : 'outgoing';

            $proposals[] = [
                'id'               => $txId,
                'row_hash'         => $hash,
                'processed_at'     => $row['processed_at'],
                'amount'           => $row['amount'],
                'currency'         => $row['currency'],
                'transaction_type' => $row['transaction_type'],
                'counterpart_name' => $row['counterpart_name'],
                'counterpart_cui'  => $row['counterpart_cui'],
                'details'          => $row['details'],
                'balance'          => $row['balance'],
                'status'           => $status,
                'client'           => $client,
                'row_type'         => $rowType,
                'payment_in_id'    => $paymentInId,
            ];
        }

        return $proposals;
    }

    /** Adauga coloane noi la bank_transactions daca lipsesc (pentru instalari existente) */
    public function ensureColumns(): void
    {
        if (!Database::tableExists('bank_transactions')) {
            return;
        }
        if (!Database::columnExists('bank_transactions', 'ignored')) {
            Database::execute(
                'ALTER TABLE bank_transactions ADD COLUMN ignored TINYINT(1) NOT NULL DEFAULT 0'
            );
        }
    }

    public function ensureTable(): bool
    {
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS bank_transactions (
                    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_no      VARCHAR(64)  NOT NULL DEFAULT \'\',
                    processed_at    DATE         NOT NULL,
                    amount          DECIMAL(12,2) NOT NULL,
                    currency        VARCHAR(8)   NOT NULL DEFAULT \'RON\',
                    transaction_type VARCHAR(128) NOT NULL DEFAULT \'\',
                    counterpart_name VARCHAR(255) NOT NULL DEFAULT \'\',
                    counterpart_address VARCHAR(255) NOT NULL DEFAULT \'\',
                    counterpart_account VARCHAR(64) NOT NULL DEFAULT \'\',
                    counterpart_bank VARCHAR(128) NOT NULL DEFAULT \'\',
                    details         TEXT         NOT NULL,
                    balance         DECIMAL(12,2) NULL,
                    counterpart_cui VARCHAR(32)  NOT NULL DEFAULT \'\',
                    row_hash        VARCHAR(64)  NOT NULL DEFAULT \'\',
                    payment_in_id   BIGINT UNSIGNED NULL,
                    ignored         TINYINT(1)   NOT NULL DEFAULT 0,
                    imported_at     DATETIME     NOT NULL,
                    created_at      DATETIME     NULL,
                    UNIQUE KEY uq_row_hash (row_hash)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            // Adauga coloane noi pe instalari existente (care aveau deja tabela)
            $this->ensureColumns();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
