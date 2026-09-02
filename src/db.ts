import { Pool as PgPool, PoolClient } from 'pg';
import dotenv from 'dotenv';
dotenv.config();

export type RowDataPacket = Record<string, any>;
export type ResultSetHeader = { affectedRows: number; insertId: number };
type QueryResult<T> = [T, []];

function postgresSql(sql: string): string {
  let parameter = 0;
  const conflictTargets: Record<string, string> = {
    site_settings: 'setting_key', document_metadata: 'user_id, document_type, document_id',
    user_profiles: 'user_id', user_preferences: 'user_id',
    career_facts: 'user_id, role_id, fact_hash', career_questions: 'user_id, role_id, question_hash',
    career_role_sources: 'user_id, role_id, source_cv_id', career_fact_sources: 'user_id, fact_id, source_cv_id',
  };
  let converted = sql.replace(/`([^`]+)`/g, '"$1"').replace(/\?/g, () => `$${++parameter}`)
    .replace(/DATE_SUB\(NOW\(\), INTERVAL (\d+) (DAY|MINUTE)\)/g, "NOW() - INTERVAL '$1 $2'")
    .replace(/DATE_ADD\(NOW\(\), INTERVAL (\d+) (DAY|MINUTE)\)/g, "NOW() + INTERVAL '$1 $2'")
    .replace(/CURDATE\(\)/g, 'CURRENT_DATE');
  const table = converted.match(/^INSERT(?: IGNORE)? INTO\s+"?([a-z_]+)"?/i)?.[1];
  if (/^INSERT IGNORE/i.test(converted)) converted = converted.replace(/^INSERT IGNORE/i, 'INSERT') + ' ON CONFLICT DO NOTHING';
  if (table && /ON DUPLICATE KEY UPDATE/i.test(converted)) {
    const target = conflictTargets[table];
    if (!target) throw new Error(`No PostgreSQL conflict target configured for ${table}.`);
    converted = converted.replace(/ON DUPLICATE KEY UPDATE/i, `ON CONFLICT (${target}) DO UPDATE SET`)
      .replace(/VALUES\(([^)]+)\)/g, 'EXCLUDED.$1')
      .replace(/id=LAST_INSERT_ID\(id\)/g, `id=${table}.id`);
  }
  return /^INSERT INTO (?:users|cv_documents|tailored_cvs|career_roles|career_facts|career_questions|webauthn_challenges|job_applications|sent_document_emails)\b/i.test(converted) && !/RETURNING/i.test(converted) ? `${converted} RETURNING id` : converted;
}

export class PoolConnection {
  constructor(private readonly client: PoolClient) {}
  async query<T = RowDataPacket[]>(sql: string, values: unknown[] = []): Promise<QueryResult<T>> {
    const result = await this.client.query(postgresSql(sql), values);
    const header = { affectedRows: result.rowCount ?? 0, insertId: Number(result.rows[0]?.id ?? 0) };
    return [(result.rows.length ? result.rows : header) as T, []];
  }
  async beginTransaction(): Promise<void> { await this.client.query('BEGIN'); }
  async commit(): Promise<void> { await this.client.query('COMMIT'); }
  async rollback(): Promise<void> { await this.client.query('ROLLBACK'); }
  release(): void { this.client.release(); }
}

export class Pool {
  constructor(private readonly pool: PgPool) {}
  async query<T = RowDataPacket[]>(sql: string, values: unknown[] = []): Promise<QueryResult<T>> {
    const client = await this.pool.connect(); try { return await new PoolConnection(client).query<T>(sql, values); } finally { client.release(); }
  }
  async getConnection(): Promise<PoolConnection> { return new PoolConnection(await this.pool.connect()); }
  async end(): Promise<void> { await this.pool.end(); }
}

export function createDatabasePool(): Pool {
  return new Pool(new PgPool({ host: process.env.DB_HOST || '127.0.0.1', port: Number(process.env.DB_PORT || 5432), user: process.env.DB_USER, password: process.env.DB_PASSWORD, database: process.env.DB_NAME, max: 10 }));
}
