<?php

declare(strict_types=1);

use App\Services\DatabaseSchemaVerifier;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/../src/' . $relative . '.php';

    if (is_file($path)) {
        require $path;
    }
});

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$createStatements = [
    'CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        totp_secret TEXT NULL,
        totp_period_seconds INTEGER NULL,
        totp_digits INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )',
    'CREATE TABLE pending_passcodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        action TEXT NOT NULL,
        code_hash TEXT NOT NULL,
        totp_secret TEXT NULL,
        period_seconds INTEGER NOT NULL,
        digits INTEGER NOT NULL,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL
    )',
    'CREATE TABLE sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        token_hash TEXT NOT NULL,
        created_at TEXT NOT NULL,
        expires_at TEXT NOT NULL
    )',
    'CREATE TABLE documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        document_type TEXT NOT NULL,
        filename TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        size_bytes INTEGER NOT NULL,
        sha256 TEXT NOT NULL,
        content BLOB NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )',
    'CREATE TABLE generations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        job_document_id INTEGER NOT NULL,
        cv_document_id INTEGER NOT NULL,
        model TEXT NOT NULL,
        thinking_time INTEGER NOT NULL,
        status TEXT NOT NULL,
        progress_percent INTEGER NOT NULL,
        cost_pence INTEGER NOT NULL,
        error_message TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )',
    'CREATE TABLE generation_outputs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        generation_id INTEGER NOT NULL,
        artifact TEXT NOT NULL,
        mime_type TEXT NULL,
        content BLOB NULL,
        output_text TEXT NULL,
        tokens_used INTEGER NULL,
        created_at TEXT NOT NULL
    )',
    'CREATE TABLE job_applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        source_url TEXT NULL,
        description TEXT NOT NULL,
        status TEXT NOT NULL,
        applied_at TEXT NULL,
        reason_code TEXT NULL,
        generation_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )',
    'CREATE TABLE api_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        provider TEXT NOT NULL,
        endpoint TEXT NOT NULL,
        tokens_used INTEGER NULL,
        cost_pence INTEGER NOT NULL,
        metadata TEXT NULL,
        created_at TEXT NOT NULL
    )',
    'CREATE TABLE backup_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        code_hash TEXT NOT NULL,
        used_at TEXT NULL,
        created_at TEXT NOT NULL
    )',
    'CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        ip_address TEXT NOT NULL,
        email TEXT NULL,
        action TEXT NOT NULL,
        user_agent TEXT NULL,
        details TEXT NULL,
        created_at TEXT NOT NULL
    )',
    'CREATE TABLE retention_settings (
        id INTEGER PRIMARY KEY,
        purge_after_days INTEGER NOT NULL,
        apply_to TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )',
    'CREATE TABLE site_settings (
        name TEXT PRIMARY KEY,
        value TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )',
    'CREATE TABLE jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        run_after TEXT NOT NULL,
        attempts INTEGER NOT NULL,
        status TEXT NOT NULL,
        error TEXT NULL,
        created_at TEXT NOT NULL
    )',
];

foreach ($createStatements as $sql) {
    $pdo->exec($sql);
}

$verifier = new DatabaseSchemaVerifier($pdo);
$report = $verifier->verify();

if (!$report['passed']) {
    throw new RuntimeException('Expected schema to pass immediately after migrations.');
}

$pdo->exec('DROP TABLE documents');
$pdo->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL)');

$reportAfterBreakage = $verifier->verify();

if ($reportAfterBreakage['passed']) {
    throw new RuntimeException('Schema verifier should detect missing columns in documents table.');
}

$documentsResult = null;

foreach ($reportAfterBreakage['results'] as $tableResult) {
    if ($tableResult['table'] === 'documents') {
        $documentsResult = $tableResult;

        break;
    }
}

if ($documentsResult === null) {
    throw new RuntimeException('Schema verifier did not return a result for documents table.');
}

if (!in_array('document_type', $documentsResult['missing_columns'], true)) {
    throw new RuntimeException('Schema verifier did not record missing columns for documents table.');
}

echo 'DatabaseSchemaVerifierTest passed' . PHP_EOL;
