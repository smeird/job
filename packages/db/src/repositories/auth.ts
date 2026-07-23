import { createHash, randomBytes } from "node:crypto";
import type { Kysely, Transaction } from "kysely";
import { asBigInt } from "../ids.js";
import type { DatabaseSchema } from "../types.js";

export interface AuthenticatedUser {
  email: string;
  id: bigint;
  sessionExpiresAt: Date;
}

export interface TotpUser {
  digits: number | null;
  email: string;
  id: bigint;
  periodSeconds: number | null;
  secret: string | null;
}

export interface PendingPasscodeRecord {
  codeHash: string;
  digits: number;
  expiresAt: Date;
  id: bigint;
  periodSeconds: number;
  totpSecret: string | null;
}

export interface CreatedSession {
  expiresAt: Date;
  token: string;
}

/** Hash a public session token identically to the PHP application: raw SHA-256 bytes. */
export function hashSessionToken(token: string): Buffer {
  return createHash("sha256").update(token, "utf8").digest();
}

export class AuthRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Resolve an unexpired legacy or TypeScript-created job_session token to its owning user. */
  public async findAuthenticatedUser(token: string, now = new Date()): Promise<AuthenticatedUser | null> {
    const row = await this.database
      .selectFrom("sessions")
      .innerJoin("users", "users.id", "sessions.user_id")
      .select(["users.id", "users.email", "sessions.expires_at"])
      .where("sessions.token_hash", "=", hashSessionToken(token))
      .where("sessions.expires_at", ">", now)
      .executeTakeFirst();

    return row === undefined
      ? null
      : {
          email: row.email,
          id: asBigInt(row.id, "user id"),
          sessionExpiresAt: row.expires_at,
        };
  }

  /** Find a user and their existing TOTP configuration by normalized email address. */
  public async findTotpUserByEmail(email: string): Promise<TotpUser | null> {
    const row = await this.database
      .selectFrom("users")
      .select(["id", "email", "totp_secret", "totp_period_seconds", "totp_digits"])
      .where("email", "=", email)
      .executeTakeFirst();

    return row === undefined
      ? null
      : {
          digits: row.totp_digits,
          email: row.email,
          id: asBigInt(row.id, "user id"),
          periodSeconds: row.totp_period_seconds,
          secret: row.totp_secret,
        };
  }

  /** Create a user inside an optional surrounding transaction and return its lossless identifier. */
  public async createUser(email: string, executor: Kysely<DatabaseSchema> | Transaction<DatabaseSchema> = this.database): Promise<bigint> {
    const now = new Date();
    const result = await executor
      .insertInto("users")
      .values({ created_at: now, email, updated_at: now })
      .executeTakeFirstOrThrow();

    return asBigInt(result.insertId, "new user id");
  }

  /** Persist TOTP details without replacing any existing account or session data. */
  public async updateTotpConfiguration(userId: bigint, secret: string, periodSeconds: number, digits: number): Promise<void> {
    await this.database
      .updateTable("users")
      .set({
        totp_digits: digits,
        totp_period_seconds: periodSeconds,
        totp_secret: secret,
        updated_at: new Date(),
      })
      .where("id", "=", userId)
      .executeTakeFirst();
  }

  /** Replace a pending registration or login challenge for an email and action. */
  public async replacePendingPasscode(input: {
    action: string;
    codeHash: string;
    digits: number;
    email: string;
    expiresAt: Date;
    periodSeconds: number;
    totpSecret: string | null;
  }): Promise<void> {
    await this.database.transaction().execute(async (transaction) => {
      await transaction
        .deleteFrom("pending_passcodes")
        .where("email", "=", input.email)
        .where("action", "=", input.action)
        .execute();
      await transaction
        .insertInto("pending_passcodes")
        .values({
          action: input.action,
          code_hash: input.codeHash,
          created_at: new Date(),
          digits: input.digits,
          email: input.email,
          expires_at: input.expiresAt,
          period_seconds: input.periodSeconds,
          totp_secret: input.totpSecret,
        })
        .executeTakeFirstOrThrow();
    });
  }

  /** Load the newest unexpired pending passcode for verification. */
  public async findPendingPasscode(email: string, action: string, now = new Date()): Promise<PendingPasscodeRecord | null> {
    const row = await this.database
      .selectFrom("pending_passcodes")
      .select(["id", "code_hash", "totp_secret", "period_seconds", "digits", "expires_at"])
      .where("email", "=", email)
      .where("action", "=", action)
      .where("expires_at", ">", now)
      .orderBy("created_at", "desc")
      .executeTakeFirst();

    return row === undefined
      ? null
      : {
          codeHash: row.code_hash,
          digits: row.digits,
          expiresAt: row.expires_at,
          id: asBigInt(row.id, "pending passcode id"),
          periodSeconds: row.period_seconds,
          totpSecret: row.totp_secret,
        };
  }

  /** Consume a verified pending passcode so it cannot be replayed. */
  public async deletePendingPasscode(id: bigint): Promise<void> {
    await this.database.deleteFrom("pending_passcodes").where("id", "=", id).executeTakeFirst();
  }

  /** Create a 30-day session compatible with the legacy SHA-256 token lookup. */
  public async createSession(userId: bigint, lifetimeSeconds = 2_592_000): Promise<CreatedSession> {
    const token = randomBytes(32).toString("base64url");
    const createdAt = new Date();
    const expiresAt = new Date(createdAt.getTime() + lifetimeSeconds * 1_000);
    await this.database
      .insertInto("sessions")
      .values({
        created_at: createdAt,
        expires_at: expiresAt,
        token_hash: hashSessionToken(token),
        user_id: userId,
      })
      .executeTakeFirstOrThrow();

    return { expiresAt, token };
  }

  /** Extend an authenticated session without changing its public token. */
  public async refreshSession(token: string, expiresAt: Date): Promise<boolean> {
    const result = await this.database
      .updateTable("sessions")
      .set({ expires_at: expiresAt })
      .where("token_hash", "=", hashSessionToken(token))
      .executeTakeFirst();

    return Number(result.numUpdatedRows) === 1;
  }

  /** Delete the current session on logout. */
  public async deleteSession(token: string): Promise<void> {
    await this.database.deleteFrom("sessions").where("token_hash", "=", hashSessionToken(token)).executeTakeFirst();
  }

  /** Delete expired session rows as an operational cleanup step. */
  public async deleteExpiredSessions(now = new Date()): Promise<bigint> {
    const result = await this.database.deleteFrom("sessions").where("expires_at", "<=", now).executeTakeFirst();
    return result.numDeletedRows;
  }

  /** Replace all recovery codes for a user with already-hashed Argon2id values. */
  public async replaceBackupCodes(userId: bigint, codeHashes: readonly string[]): Promise<void> {
    await this.database.transaction().execute(async (transaction) => {
      await transaction.deleteFrom("backup_codes").where("user_id", "=", userId).execute();
      if (codeHashes.length > 0) {
        const createdAt = new Date();
        await transaction
          .insertInto("backup_codes")
          .values(codeHashes.map((codeHash) => ({ code_hash: codeHash, created_at: createdAt, user_id: userId })))
          .execute();
      }
    });
  }

  /** Return unused backup-code hashes so the domain service can verify legacy Argon2id values. */
  public async listUnusedBackupCodes(userId: bigint): Promise<Array<{ hash: string; id: bigint }>> {
    const rows = await this.database
      .selectFrom("backup_codes")
      .select(["id", "code_hash"])
      .where("user_id", "=", userId)
      .where("used_at", "is", null)
      .orderBy("id", "asc")
      .execute();

    return rows.map((row) => ({ hash: row.code_hash, id: asBigInt(row.id, "backup code id") }));
  }

  /** Atomically mark one verified recovery code as used if another request has not consumed it. */
  public async consumeBackupCode(id: bigint, userId: bigint): Promise<boolean> {
    const result = await this.database
      .updateTable("backup_codes")
      .set({ used_at: new Date() })
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .where("used_at", "is", null)
      .executeTakeFirst();

    return Number(result.numUpdatedRows) === 1;
  }
}
