import type { AuditRepository } from "@job/db";

export class RateLimitError extends Error {
  public constructor(message = "Too many attempts. Please try again later.") {
    super(message);
    this.name = "RateLimitError";
  }
}

export class DatabaseRateLimiter {
  public constructor(
    private readonly auditRepository: AuditRepository,
    private readonly limit: number,
    private readonly windowMilliseconds: number,
  ) {}

  /** Assert that a request remains below the configured database-backed limit. */
  public async assertAllowed(input: { action: string; email?: string; ipAddress: string }): Promise<void> {
    const count = await this.auditRepository.countRecent({
      action: input.action,
      ...(input.email === undefined ? {} : { email: input.email }),
      ipAddress: input.ipAddress,
      since: new Date(Date.now() - this.windowMilliseconds),
    });
    if (count >= this.limit) {
      throw new RateLimitError();
    }
  }

  /** Record one rate-limited action after its allowance check. */
  public async hit(input: { action: string; email?: string; ipAddress: string; userAgent?: string | undefined }): Promise<void> {
    await this.auditRepository.record({
      action: input.action,
      ...(input.email === undefined ? {} : { email: input.email }),
      ipAddress: input.ipAddress,
      ...(input.userAgent === undefined ? {} : { userAgent: input.userAgent }),
    });
  }
}
