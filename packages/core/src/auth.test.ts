import argon2 from "argon2";
import type { AuditRepository, AuthRepository, PendingPasscodeRecord } from "@job/db";
import { describe, expect, it } from "vitest";
import { AuthenticationService } from "./auth.js";

/** Build rate-limit and audit behaviour without a database for authentication unit tests. */
function auditStub(events: string[]): AuditRepository {
  return {
    countRecent: () => Promise.resolve(0),
    record: (event: { action: string }) => {
      events.push(event.action);
      return Promise.resolve();
    },
  } as unknown as AuditRepository;
}

describe("legacy-compatible authentication", () => {
  it("verifies an existing TOTP secret without relying on the pending Argon2id fallback", async () => {
    const events: string[] = [];
    let pending: PendingPasscodeRecord | null = null;
    const repository = {
      createSession: () => Promise.resolve({ expiresAt: new Date("2030-01-01T00:00:00Z"), token: "session-token" }),
      deletePendingPasscode: () => {
        pending = null;
        return Promise.resolve();
      },
      findPendingPasscode: () => Promise.resolve(pending),
      findTotpUserByEmail: () => Promise.resolve({ digits: 6, email: "person@example.com", id: 42n, periodSeconds: 30, secret: "JBSWY3DPEHPK3PXP" }),
      replacePendingPasscode: (input: { digits: number; expiresAt: Date; periodSeconds: number; totpSecret: string | null }) => {
        pending = { codeHash: "deliberately-not-an-argon-hash", digits: input.digits, expiresAt: input.expiresAt, id: 1n, periodSeconds: input.periodSeconds, totpSecret: input.totpSecret };
        return Promise.resolve();
      },
    } as unknown as AuthRepository;
    const service = new AuthenticationService(repository, auditStub(events));

    const challenge = await service.initiateLogin("Person@Example.com", { ipAddress: "127.0.0.1" });
    await expect(service.verifyLogin("person@example.com", challenge.code, { ipAddress: "127.0.0.1" })).resolves.toMatchObject({ token: "session-token" });
    expect(events).toContain("auth.login.success");
  });

  it("verifies, consumes, and rate-limits an existing Argon2id recovery code", async () => {
    const events: string[] = [];
    const hash = await argon2.hash("ABCD1234", { type: argon2.argon2id });
    let consumed = false;
    const repository = {
      consumeBackupCode: () => {
        consumed = true;
        return Promise.resolve(true);
      },
      createSession: () => Promise.resolve({ expiresAt: new Date("2030-01-01T00:00:00Z"), token: "backup-session" }),
      findTotpUserByEmail: () => Promise.resolve({ digits: 6, email: "person@example.com", id: 42n, periodSeconds: 30, secret: "JBSWY3DPEHPK3PXP" }),
      listUnusedBackupCodes: () => Promise.resolve([{ hash, id: 8n }]),
    } as unknown as AuthRepository;
    const service = new AuthenticationService(repository, auditStub(events));

    await expect(service.verifyBackupCode("person@example.com", "abcd-1234", { ipAddress: "127.0.0.1" })).resolves.toMatchObject({ token: "backup-session" });
    expect(consumed).toBe(true);
    expect(events).toEqual(expect.arrayContaining(["backup.verify", "auth.backup_code.used"]));
  });
});
