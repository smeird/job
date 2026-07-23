import { randomBytes } from "node:crypto";
import argon2 from "argon2";
import * as OTPAuth from "otpauth";
import QRCode from "qrcode";
import type { AuditRepository, AuthRepository, AuthenticatedUser, CreatedSession } from "@job/db";
import { DatabaseRateLimiter } from "./rate-limit.js";
import type { AuthChallengeNotifier } from "./mail.js";

const OTP_ISSUER = "job.smeird.com";
const OTP_PERIOD_SECONDS = 30;
const OTP_DIGITS = 6;

export interface AuthChallenge {
  code: string;
  digits: number;
  expiresAt: Date;
  periodSeconds: number;
  secret: string;
  uri: string;
}

/** Normalize and validate an email in the same lower-case form used by PHP. */
function normalizeEmail(value: string): string {
  const email = value.trim().toLowerCase();
  if (email.length === 0 || email.length > 255 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    throw new Error("Please enter a valid email address.");
  }
  return email;
}

/** Strip formatting characters from a submitted TOTP passcode. */
function normalizePasscode(value: string): string {
  return value.replace(/\D+/g, "");
}

/** Build an RFC 6238 challenge compatible with the PHP SHA-1 implementation. */
function createTotpChallenge(email: string, existing?: { digits: number; periodSeconds: number; secret: string }): AuthChallenge {
  const secret = existing?.secret.toUpperCase() ?? new OTPAuth.Secret({ size: 20 }).base32;
  const digits = existing?.digits ?? OTP_DIGITS;
  const periodSeconds = existing?.periodSeconds ?? OTP_PERIOD_SECONDS;
  const totp = new OTPAuth.TOTP({
    algorithm: "SHA1",
    digits,
    issuer: OTP_ISSUER,
    label: email,
    period: periodSeconds,
    secret: OTPAuth.Secret.fromBase32(secret),
  });

  return {
    code: totp.generate(),
    digits,
    expiresAt: new Date(Date.now() + 10 * 60_000),
    periodSeconds,
    secret,
    uri: totp.toString(),
  };
}

/** Rebuild the canonical authenticator URI for a pending database challenge. */
export function buildTotpUri(email: string, secret: string, periodSeconds: number, digits: number): string {
  return new OTPAuth.TOTP({
    algorithm: "SHA1",
    digits,
    issuer: OTP_ISSUER,
    label: email,
    period: periodSeconds,
    secret: OTPAuth.Secret.fromBase32(secret),
  }).toString();
}

/** Render an authenticator URI as an embedded QR image without remote assets. */
export async function totpQrDataUrl(uri: string): Promise<string> {
  return QRCode.toDataURL(uri, { errorCorrectionLevel: "M", margin: 1, width: 256 });
}

/** Validate a submitted TOTP against the current, previous, and next time windows. */
function verifyTotp(challenge: { digits: number; periodSeconds: number; secret: string }, code: string): boolean {
  const totp = new OTPAuth.TOTP({
    algorithm: "SHA1",
    digits: challenge.digits,
    period: challenge.periodSeconds,
    secret: OTPAuth.Secret.fromBase32(challenge.secret),
  });
  return totp.validate({ token: code, window: 1 }) !== null;
}

export class AuthenticationService {
  public constructor(
    private readonly authRepository: AuthRepository,
    private readonly auditRepository: AuditRepository,
    private readonly notifier?: AuthChallengeNotifier,
    private readonly requestLimiter = new DatabaseRateLimiter(auditRepository, 5, 10 * 60_000),
    private readonly verifyLimiter = new DatabaseRateLimiter(auditRepository, 10, 10 * 60_000),
  ) {}

  /** Resolve an existing PHP or TypeScript session and optionally extend its expiry. */
  public async authenticate(token: string | undefined, refresh = false): Promise<AuthenticatedUser | null> {
    if (token === undefined || token === "") {
      return null;
    }

    const user = await this.authRepository.findAuthenticatedUser(token);
    if (user !== null && refresh) {
      await this.authRepository.refreshSession(token, new Date(Date.now() + 30 * 24 * 60 * 60_000));
    }
    return user;
  }

  /** Start account registration and store a PHP-compatible Argon2id challenge hash. */
  public async initiateRegistration(emailValue: string, context: { ipAddress: string; userAgent?: string | undefined }): Promise<AuthChallenge> {
    const email = normalizeEmail(emailValue);
    await this.requestLimiter.assertAllowed({ action: "register.request", email, ipAddress: context.ipAddress });
    await this.requestLimiter.hit({ action: "register.request", email, ipAddress: context.ipAddress, ...(context.userAgent === undefined ? {} : { userAgent: context.userAgent }) });
    if (await this.authRepository.findTotpUserByEmail(email) !== null) {
      throw new Error("An account with that email already exists. Please log in.");
    }

    const challenge = createTotpChallenge(email);
    await this.authRepository.replacePendingPasscode({
      action: "register",
      codeHash: await argon2.hash(challenge.code, { type: argon2.argon2id }),
      digits: challenge.digits,
      email,
      expiresAt: challenge.expiresAt,
      periodSeconds: challenge.periodSeconds,
      totpSecret: challenge.secret,
    });
    await this.auditRepository.record({ action: "auth.register.requested", details: { status: "qr_generated" }, email, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null });
    await this.notifier?.sendChallenge({ action: "register", code: challenge.code, email, expiresAt: challenge.expiresAt });
    return challenge;
  }

  /** Complete registration after verifying either live TOTP or the stored legacy Argon2id code. */
  public async verifyRegistration(emailValue: string, codeValue: string, context: { ipAddress: string; userAgent?: string | undefined }): Promise<CreatedSession> {
    const email = normalizeEmail(emailValue);
    await this.verifyLimiter.assertAllowed({ action: "register.verify", email, ipAddress: context.ipAddress });
    await this.verifyLimiter.hit({ action: "register.verify", email, ipAddress: context.ipAddress, ...(context.userAgent === undefined ? {} : { userAgent: context.userAgent }) });
    const pending = await this.verifyPendingPasscode(email, "register", codeValue);
    const existing = await this.authRepository.findTotpUserByEmail(email);
    const userId = existing?.id ?? await this.authRepository.createUser(email);
    if (pending.totpSecret !== null) {
      await this.authRepository.updateTotpConfiguration(userId, pending.totpSecret, pending.periodSeconds, pending.digits);
    }
    const session = await this.authRepository.createSession(userId);
    await this.auditRepository.record({ action: "auth.register.completed", details: { session_expires_at: session.expiresAt.toISOString() }, email, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null, userId });
    return session;
  }

  /** Start login using the account's existing TOTP seed so old authenticators remain valid. */
  public async initiateLogin(emailValue: string, context: { ipAddress: string; userAgent?: string | undefined }): Promise<AuthChallenge> {
    const email = normalizeEmail(emailValue);
    await this.requestLimiter.assertAllowed({ action: "login.request", email, ipAddress: context.ipAddress });
    await this.requestLimiter.hit({ action: "login.request", email, ipAddress: context.ipAddress, ...(context.userAgent === undefined ? {} : { userAgent: context.userAgent }) });
    const user = await this.authRepository.findTotpUserByEmail(email);
    if (user === null) {
      throw new Error("No account was found with that email. Please register first.");
    }

    const existing = user.secret === null || user.secret === ""
      ? undefined
      : { digits: user.digits ?? OTP_DIGITS, periodSeconds: user.periodSeconds ?? OTP_PERIOD_SECONDS, secret: user.secret };
    const challenge = createTotpChallenge(email, existing);
    if (existing === undefined) {
      await this.authRepository.updateTotpConfiguration(user.id, challenge.secret, challenge.periodSeconds, challenge.digits);
    }
    await this.authRepository.replacePendingPasscode({
      action: "login",
      codeHash: await argon2.hash(challenge.code, { type: argon2.argon2id }),
      digits: challenge.digits,
      email,
      expiresAt: challenge.expiresAt,
      periodSeconds: challenge.periodSeconds,
      totpSecret: challenge.secret,
    });
    await this.auditRepository.record({ action: "auth.login.requested", details: { status: "qr_generated" }, email, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null });
    await this.notifier?.sendChallenge({ action: "login", code: challenge.code, email, expiresAt: challenge.expiresAt });
    return challenge;
  }

  /** Complete login while accepting pending hashes created by either PHP or TypeScript. */
  public async verifyLogin(emailValue: string, codeValue: string, context: { ipAddress: string; userAgent?: string | undefined }): Promise<CreatedSession> {
    const email = normalizeEmail(emailValue);
    await this.verifyLimiter.assertAllowed({ action: "login.verify", email, ipAddress: context.ipAddress });
    await this.verifyLimiter.hit({ action: "login.verify", email, ipAddress: context.ipAddress, ...(context.userAgent === undefined ? {} : { userAgent: context.userAgent }) });
    const user = await this.authRepository.findTotpUserByEmail(email);
    if (user === null) {
      throw new Error("No account was found with that email.");
    }

    await this.verifyPendingPasscode(email, "login", codeValue);
    const session = await this.authRepository.createSession(user.id);
    await this.auditRepository.record({ action: "auth.login.success", details: { session_expires_at: session.expiresAt.toISOString() }, email, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null, userId: user.id });
    return session;
  }

  /** Generate ten recovery codes and store only Argon2id hashes. */
  public async generateBackupCodes(userId: bigint, context: { ipAddress: string; userAgent?: string | undefined }): Promise<string[]> {
    const codes = Array.from({ length: 10 }, () => randomBytes(4).toString("hex").toUpperCase());
    const hashes = await Promise.all(codes.map(async (code) => argon2.hash(code, { type: argon2.argon2id })));
    await this.authRepository.replaceBackupCodes(userId, hashes);
    await this.auditRepository.record({ action: "auth.backup_codes.generated", details: { count: codes.length }, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null, userId });
    return codes;
  }

  /** Verify and atomically consume one legacy or TypeScript-created Argon2id recovery code. */
  public async verifyBackupCode(emailValue: string, codeValue: string, context: { ipAddress: string; userAgent?: string | undefined }): Promise<CreatedSession> {
    const email = normalizeEmail(emailValue);
    await this.verifyLimiter.assertAllowed({ action: "backup.verify", email, ipAddress: context.ipAddress });
    await this.verifyLimiter.hit({ action: "backup.verify", email, ipAddress: context.ipAddress, ...(context.userAgent === undefined ? {} : { userAgent: context.userAgent }) });
    const user = await this.authRepository.findTotpUserByEmail(email);
    if (user === null) {
      throw new Error("Invalid backup code.");
    }

    const normalizedCode = codeValue.trim().replace(/[^A-Fa-f0-9]/g, "").toUpperCase();
    for (const candidate of await this.authRepository.listUnusedBackupCodes(user.id)) {
      if (await argon2.verify(candidate.hash, normalizedCode) && await this.authRepository.consumeBackupCode(candidate.id, user.id)) {
        const session = await this.authRepository.createSession(user.id);
        await this.auditRepository.record({ action: "auth.backup_code.used", email, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null, userId: user.id });
        return session;
      }
    }
    throw new Error("Invalid backup code.");
  }

  /** Destroy a session and append a logout audit event. */
  public async logout(token: string, user: AuthenticatedUser | null, context: { ipAddress: string; userAgent?: string | undefined }): Promise<void> {
    await this.authRepository.deleteSession(token);
    await this.auditRepository.record({ action: "auth.logout", email: user?.email ?? null, ipAddress: context.ipAddress, userAgent: context.userAgent ?? null, userId: user?.id ?? null });
  }

  /** Validate and consume one pending challenge using live TOTP first and Argon2id as compatibility fallback. */
  private async verifyPendingPasscode(email: string, action: string, codeValue: string): Promise<{ digits: number; periodSeconds: number; totpSecret: string | null }> {
    const code = normalizePasscode(codeValue);
    const pending = await this.authRepository.findPendingPasscode(email, action);
    if (pending === null) {
      throw new Error("Invalid or expired code.");
    }

    const liveTotpValid = pending.totpSecret !== null && verifyTotp({ digits: pending.digits, periodSeconds: pending.periodSeconds, secret: pending.totpSecret }, code);
    const storedCodeValid = liveTotpValid ? false : await argon2.verify(pending.codeHash, code).catch(() => false);
    if (!liveTotpValid && !storedCodeValid) {
      throw new Error("Invalid or expired code.");
    }

    await this.authRepository.deletePendingPasscode(pending.id);
    return { digits: pending.digits, periodSeconds: pending.periodSeconds, totpSecret: pending.totpSecret };
  }
}
