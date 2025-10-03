<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class DatabaseSchemaVerifier
{
    /**
     * A whitelist of tables and the columns they must expose for the application to operate correctly.
     *
     * Keeping the schema definition in code allows the verifier to run in production without relying on external tooling.
     */
    private const EXPECTED_SCHEMA = [
        'users' => [
            'columns' => [
                'id',
                'email',
                'totp_secret',
                'totp_period_seconds',
                'totp_digits',
                'created_at',
                'updated_at',
            ],
        ],
        'pending_passcodes' => [
            'columns' => [
                'id',
                'email',
                'action',
                'code_hash',
                'totp_secret',
                'period_seconds',
                'digits',
                'expires_at',
                'created_at',
            ],
        ],
        'sessions' => [
            'columns' => [
                'id',
                'user_id',
                'token_hash',
                'created_at',
                'expires_at',
            ],
        ],
        'documents' => [
            'columns' => [
                'id',
                'user_id',
                'document_type',
                'filename',
                'mime_type',
                'size_bytes',
                'sha256',
                'content',
                'created_at',
                'updated_at',
            ],
        ],
        'generations' => [
            'columns' => [
                'id',
                'user_id',
                'job_document_id',
                'cv_document_id',
                'model',
                'thinking_time',
                'status',
                'progress_percent',
                'cost_pence',
                'error_message',
                'created_at',
                'updated_at',
            ],
        ],
        'generation_outputs' => [
            'columns' => [
                'id',
                'generation_id',
                'artifact',
                'mime_type',
                'content',
                'output_text',
                'tokens_used',
                'created_at',
            ],
        ],
        'job_applications' => [
            'columns' => [
                'id',
                'user_id',
                'title',
                'source_url',
                'description',
                'status',
                'applied_at',
                'reason_code',
                'generation_id',
                'created_at',
                'updated_at',
            ],
        ],
        'api_usage' => [
            'columns' => [
                'id',
                'user_id',
                'provider',
                'endpoint',
                'tokens_used',
                'cost_pence',
                'metadata',
                'created_at',
            ],
        ],
        'backup_codes' => [
            'columns' => [
                'id',
                'user_id',
                'code_hash',
                'used_at',
                'created_at',
            ],
        ],
        'audit_logs' => [
            'columns' => [
                'id',
                'user_id',
                'ip_address',
                'email',
                'action',
                'user_agent',
                'details',
                'created_at',
            ],
        ],
        'retention_settings' => [
            'columns' => [
                'id',
                'purge_after_days',
                'apply_to',
                'created_at',
                'updated_at',
            ],
        ],
        'site_settings' => [
            'columns' => [
                'name',
                'value',
                'created_at',
                'updated_at',
            ],
        ],
        'jobs' => [
            'columns' => [
                'id',
                'type',
                'payload_json',
                'run_after',
                'attempts',
                'status',
                'error',
                'created_at',
            ],
        ],
    ];

    /** @var PDO */
    private $pdo;

    /**
     * Construct the object with its required dependencies.
     *
     * This ensures collaborating services are available for subsequent method calls.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Execute the schema verification routine.
     *
     * Returning a structured report keeps controllers and views simple while providing enough detail for troubleshooting.
     *
     * @return array{passed: bool, results: array<int, array{table: string, exists: bool, missing_columns: array<int, string>, unexpected_columns: array<int, string>, error: string|null, is_valid: bool}>}
     */
    public function verify(): array
    {
        $results = [];
        $allPassed = true;

        foreach (self::EXPECTED_SCHEMA as $table => $definition) {
            $tableResult = $this->inspectTable($table, $definition['columns']);

            if (!$tableResult['is_valid']) {
                $allPassed = false;
            }

            $results[] = $tableResult;
        }

        return [
            'passed' => $allPassed,
            'results' => $results,
        ];
    }

    /**
     * Inspect the supplied table for expected columns and structure.
     *
     * Capturing per-table output enables the interface to highlight precisely what needs to be fixed.
     *
     * @param array<int, string> $expectedColumns Columns the table must expose.
     * @return array{table: string, exists: bool, missing_columns: array<int, string>, unexpected_columns: array<int, string>, error: string|null, is_valid: bool}
     */
    private function inspectTable(string $table, array $expectedColumns): array
    {
        $result = [
            'table' => $table,
            'exists' => false,
            'missing_columns' => [],
            'unexpected_columns' => [],
            'error' => null,
            'is_valid' => false,
        ];

        try {
            if (!$this->tableExists($table)) {
                $result['missing_columns'] = $expectedColumns;

                return $result;
            }

            $result['exists'] = true;
            $columns = $this->fetchColumns($table);

            $result['missing_columns'] = array_values(array_diff($expectedColumns, $columns));
            $result['unexpected_columns'] = array_values(array_diff($columns, $expectedColumns));
            $result['is_valid'] = $result['missing_columns'] === []
                && $result['unexpected_columns'] === [];
        } catch (PDOException $exception) {
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Determine whether the given table exists in the connected database.
     *
     * Driver-specific inspection keeps the verifier portable between MySQL production and SQLite smoke tests.
     */
    private function tableExists(string $table): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute(['table' => $table]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $this->pdo->prepare(
            'SELECT name FROM sqlite_master WHERE type = "table" AND name = :table LIMIT 1'
        );
        $statement->execute(['table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Retrieve the column names for the supplied table.
     *
     * Normalising column reads across database engines ensures comparison logic stays consistent.
     *
     * @return array<int, string>
     */
    private function fetchColumns(string $table): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $statement = $this->pdo->prepare(
                'SELECT COLUMN_NAME FROM information_schema.columns '
                . 'WHERE table_schema = DATABASE() AND table_name = :table ORDER BY ORDINAL_POSITION'
            );
            $statement->execute(['table' => $table]);

            return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        }

        $query = sprintf('PRAGMA table_info(%s)', $this->quoteIdentifier($table));
        $statement = $this->pdo->query($query);

        if ($statement === false) {
            return [];
        }

        $columns = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['name'])) {
                $columns[] = (string) $row['name'];
            }
        }

        return $columns;
    }

    /**
     * Quote the provided identifier for safe inclusion in a PRAGMA statement.
     *
     * SQLite requires identifiers to be wrapped when they contain special characters, so we escape quotes defensively.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
