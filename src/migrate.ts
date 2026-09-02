import fs from 'node:fs';
import path from 'node:path';
import { createDatabasePool } from './db';

/** Applies the ordered PostgreSQL schema migrations to the configured database. */
async function migrate(): Promise<void> {
  const pool = createDatabasePool();
  const directory = path.join(process.cwd(), 'database', 'postgresql');
  const files = fs.readdirSync(directory).filter((file) => file.endsWith('.sql')).sort();
  await pool.query('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(255) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
  for (const file of files) {
    const [applied] = await pool.query<import('./db').RowDataPacket[]>('SELECT filename FROM schema_migrations WHERE filename=?', [file]);
    if (applied.length) { console.log(`Skipped ${file} (already applied)`); continue; }
    const source = fs.readFileSync(path.join(directory, file), 'utf8');
    const statements = source.split(/;\s*(?:\r?\n|$)/).map((statement) => statement.replace(/^--.*$/gm, '').trim()).filter(Boolean);
    for (const statement of statements) await pool.query(statement);
    await pool.query('INSERT INTO schema_migrations (filename) VALUES (?)', [file]);
    console.log(`Applied ${file}`);
  }
  await pool.end();
}

/** Starts migrations and returns a conventional non-zero code if PostgreSQL rejects them. */
migrate().catch((error: Error) => { console.error(error.message); process.exitCode = 1; });
