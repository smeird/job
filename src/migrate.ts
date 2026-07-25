import fs from 'node:fs';
import path from 'node:path';
import { createDatabasePool } from './db';

/** Applies the ordered SQL migration files to the configured MySQL database. */
async function migrate(): Promise<void> {
  const pool = createDatabasePool();
  const directory = path.join(process.cwd(), 'database', 'migrations');
  const files = fs.readdirSync(directory).filter((file) => file.endsWith('.sql')).sort();
  for (const file of files) {
    const source = fs.readFileSync(path.join(directory, file), 'utf8');
    const statements = source.split(/;\s*(?:\r?\n|$)/).map((statement) => statement.replace(/^--.*$/gm, '').trim()).filter(Boolean);
    for (const statement of statements) await pool.query(statement);
    console.log(`Applied ${file}`);
  }
  await pool.end();
}

/** Starts migrations and returns a conventional non-zero code if MySQL rejects them. */
migrate().catch((error: Error) => { console.error(error.message); process.exitCode = 1; });
