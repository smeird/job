import type { ColumnType, Insertable, Selectable, Updateable } from "kysely";

export type DatabaseId = ColumnType<bigint, bigint | string | number, bigint | string | number>;
export type DatabaseDate = ColumnType<Date, Date | string, Date | string>;
export type DatabaseJson = ColumnType<unknown, unknown, unknown>;
export type GeneratedDatabaseId = ColumnType<bigint, bigint | string | number | undefined, bigint | string | number>;
export type GeneratedDatabaseDate = ColumnType<Date, Date | string | undefined, Date | string>;

export interface UsersTable {
  id: GeneratedDatabaseId;
  email: string;
  totp_secret: string | null;
  totp_period_seconds: number | null;
  totp_digits: number | null;
  created_at: DatabaseDate;
  updated_at: DatabaseDate;
}

export interface PendingPasscodesTable {
  id: GeneratedDatabaseId;
  email: string;
  action: string;
  code_hash: string;
  totp_secret: string | null;
  period_seconds: number;
  digits: number;
  expires_at: DatabaseDate;
  created_at: DatabaseDate;
}

export interface SessionsTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  token_hash: Buffer;
  created_at: DatabaseDate;
  expires_at: DatabaseDate;
}

export interface DocumentsTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  document_type: string;
  filename: string;
  mime_type: string;
  size_bytes: DatabaseId;
  sha256: string;
  content: Buffer;
  created_at: GeneratedDatabaseDate;
  updated_at: GeneratedDatabaseDate;
}

export interface GenerationsTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  job_document_id: DatabaseId;
  cv_document_id: DatabaseId;
  model: string;
  thinking_time: number;
  status: string;
  progress_percent: number;
  cost_pence: DatabaseId;
  error_message: string | null;
  created_at: GeneratedDatabaseDate;
  updated_at: GeneratedDatabaseDate;
}

export interface GenerationOutputsTable {
  id: GeneratedDatabaseId;
  generation_id: DatabaseId;
  artifact: string;
  mime_type: string | null;
  content: Buffer | null;
  output_text: string | null;
  tokens_used: number | null;
  created_at: GeneratedDatabaseDate;
}

export interface ApiUsageTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  provider: string;
  endpoint: string;
  tokens_used: number | null;
  cost_pence: DatabaseId;
  metadata: DatabaseJson | null;
  created_at: GeneratedDatabaseDate;
}

export interface BackupCodesTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  code_hash: string;
  used_at: DatabaseDate | null;
  created_at: DatabaseDate;
}

export interface AuditLogsTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId | null;
  ip_address: string;
  email: string | null;
  action: string;
  user_agent: string | null;
  details: string | null;
  created_at: DatabaseDate;
}

export interface RetentionSettingsTable {
  id: number;
  purge_after_days: number;
  apply_to: DatabaseJson;
  created_at: GeneratedDatabaseDate;
  updated_at: GeneratedDatabaseDate;
}

export interface JobsTable {
  id: GeneratedDatabaseId;
  type: string;
  payload_json: DatabaseJson;
  runtime_queue: "php" | "typescript";
  run_after: GeneratedDatabaseDate;
  attempts: number;
  status: string;
  error: string | null;
  created_at: GeneratedDatabaseDate;
}

export interface JobApplicationsTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  title: string;
  source_url: string | null;
  description: string;
  status: string;
  applied_at: DatabaseDate | null;
  reason_code: string | null;
  generation_id: DatabaseId | null;
  created_at: GeneratedDatabaseDate;
  updated_at: GeneratedDatabaseDate;
}

export interface JobApplicationResearchTable {
  id: GeneratedDatabaseId;
  user_id: DatabaseId;
  job_application_id: DatabaseId;
  query: string;
  summary: string;
  search_results: string;
  generated_at: DatabaseDate;
  created_at: GeneratedDatabaseDate;
  updated_at: GeneratedDatabaseDate;
}

export interface UserContactDetailsTable {
  user_id: DatabaseId;
  address: string;
  phone: string | null;
  email: string | null;
  created_at: DatabaseDate;
  updated_at: DatabaseDate;
}

export interface SiteSettingsTable {
  name: string;
  value: string | null;
  created_at: GeneratedDatabaseDate;
  updated_at: GeneratedDatabaseDate;
}

export interface SchemaMigrationsTable {
  id: GeneratedDatabaseId;
  migration: string;
  applied_at: GeneratedDatabaseDate;
}

export interface DatabaseSchema {
  api_usage: ApiUsageTable;
  audit_logs: AuditLogsTable;
  backup_codes: BackupCodesTable;
  documents: DocumentsTable;
  generation_outputs: GenerationOutputsTable;
  generations: GenerationsTable;
  job_application_research: JobApplicationResearchTable;
  job_applications: JobApplicationsTable;
  jobs: JobsTable;
  pending_passcodes: PendingPasscodesTable;
  retention_settings: RetentionSettingsTable;
  schema_migrations: SchemaMigrationsTable;
  sessions: SessionsTable;
  site_settings: SiteSettingsTable;
  user_contact_details: UserContactDetailsTable;
  users: UsersTable;
}

export type UserRow = Selectable<UsersTable>;
export type NewUserRow = Insertable<UsersTable>;
export type SessionRow = Selectable<SessionsTable>;
export type DocumentRow = Selectable<DocumentsTable>;
export type NewDocumentRow = Insertable<DocumentsTable>;
export type GenerationRow = Selectable<GenerationsTable>;
export type NewGenerationRow = Insertable<GenerationsTable>;
export type GenerationPatch = Updateable<GenerationsTable>;
export type GenerationOutputRow = Selectable<GenerationOutputsTable>;
export type JobRow = Selectable<JobsTable>;
export type JobApplicationRow = Selectable<JobApplicationsTable>;
export type JobApplicationPatch = Updateable<JobApplicationsTable>;
