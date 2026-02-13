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
