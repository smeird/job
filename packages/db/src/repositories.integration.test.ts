import { createHash } from "node:crypto";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { ApplicationsRepository } from "./repositories/applications.js";
import { AuditRepository } from "./repositories/audit.js";
import { AuthRepository } from "./repositories/auth.js";
import { ContactDetailsRepository } from "./repositories/contact-details.js";
import { DocumentsRepository } from "./repositories/documents.js";
import { GenerationsRepository } from "./repositories/generations.js";
import { JobsRepository } from "./repositories/jobs.js";
import { SettingsRepository } from "./repositories/settings.js";
import { UsageRepository } from "./repositories/usage.js";
import { loadDatabaseConfig } from "./config.js";
import { createDatabase } from "./database.js";
import { verifyDatabaseSchema } from "./schema-verifier.js";

const enabled = process.env.RUN_MYSQL_INTEGRATION === "true";
const config = loadDatabaseConfig();
const safeDatabase = /(?:^|_)test(?:_|$)/i.test(config.database);
const suite = enabled && safeDatabase ? describe : describe.skip;

suite("MySQL repositories", () => {
  const database = createDatabase(config);
  const auth = new AuthRepository(database);
  const audit = new AuditRepository(database);
  const contacts = new ContactDetailsRepository(database);
  const documents = new DocumentsRepository(database);
  const generations = new GenerationsRepository(database);
  const jobs = new JobsRepository(database);
  const applications = new ApplicationsRepository(database);
  const settings = new SettingsRepository(database);
  const usage = new UsageRepository(database);
  const testEmail = `typescript-integration-${Date.now()}@example.com`;
  let userId = 0n;
  const jobIds: bigint[] = [];

  beforeAll(async () => {
    if (!safeDatabase) { throw new Error("Integration tests require a database name containing 'test'."); }
    userId = await auth.createUser(testEmail);
  });

  afterAll(async () => {
    if (jobIds.length > 0) { await database.deleteFrom("jobs").where("id", "in", jobIds).execute(); }
    await database.deleteFrom("audit_logs").where("email", "=", testEmail).execute();
    await database.deleteFrom("site_settings").where("name", "=", "typescript_integration_test").execute();
    if (userId > 0n) { await database.deleteFrom("users").where("id", "=", userId).execute(); }
    await database.destroy();
  });

  it("verifies the migrated schema and exercises tenant-owned repositories", async () => {
    expect(await verifyDatabaseSchema(database)).toMatchObject({ ok: true });
    const session = await auth.createSession(userId, 60);
    await expect(auth.findAuthenticatedUser(session.token)).resolves.toMatchObject({ id: userId });

    await contacts.saveForUser(userId, { address: "1 Test Street", email: "test@example.com", phone: null });
    await expect(contacts.findForUser(userId)).resolves.toMatchObject({ address: "1 Test Street" });

    const content = Buffer.from("# Master CV\n\nAcme Systems", "utf8");
    const cvId = await documents.create({ content, documentType: "cv", filename: "master.md", mimeType: "text/markdown", sha256: createHash("sha256").update(content).digest("hex"), sizeBytes: BigInt(content.length), userId });
    const jobContent = Buffer.from("Senior engineer role", "utf8");
    const jobDocumentId = await documents.create({ content: jobContent, documentType: "job_description", filename: "role.txt", mimeType: "text/plain", sha256: createHash("sha256").update(jobContent).digest("hex"), sizeBytes: BigInt(jobContent.length), userId });
    await expect(documents.findOwned(cvId, userId)).resolves.toMatchObject({ content, id: cvId });
    await expect(documents.findOwned(cvId, userId + 1n)).resolves.toBeNull();

    const applicationId = await applications.create(userId, { description: "Senior engineer role", sourceUrl: "https://example.com/job", title: "Senior Engineer" });
    await expect(applications.findOwned(applicationId, userId)).resolves.toMatchObject({ status: "outstanding" });
    expect(await applications.updateStatusOwned(applicationId, userId, "applied", null)).toBe(true);

    const generationId = await generations.createAndEnqueue({ cvDocumentId: cvId, jobDocumentId, model: "gpt-test", payload: { user_id: userId.toString(), version: 1 }, thinkingTime: 30, userId });
    const queuedRows = await database.selectFrom("jobs").select("id").where("runtime_queue", "=", "typescript").orderBy("id", "desc").limit(1).execute();
    jobIds.push(...queuedRows.map((row) => BigInt(String(row.id))));
    const reservations = await Promise.all([jobs.reserveNext(new Date(Date.now() + 1_000)), jobs.reserveNext(new Date(Date.now() + 1_000))]);
    expect(reservations.filter((reservation) => reservation !== null)).toHaveLength(1);
    const reserved = reservations.find((reservation) => reservation !== null) ?? null;
    expect(reserved?.payload).toMatchObject({ generation_id: generationId.toString(), user_id: userId.toString() });
    if (reserved !== null) { await jobs.markCompleted(reserved.id); }

    await usage.recordForGeneration({ costPenceRounded: 1n, endpoint: "responses.test", generationId, metadata: { completion_tokens: 4, cost_available: true, cost_pence_precise: 0.75, model_requested: "gpt-test", prompt_tokens: 6, total_tokens: 10 }, provider: "openai", tokensUsed: 10, userId });
    await expect(usage.listForUser(userId)).resolves.toEqual(expect.arrayContaining([expect.objectContaining({ totalTokens: 10 })]));
    await expect(generations.findOwned(generationId, userId)).resolves.toMatchObject({ costPence: 1n });

    await audit.record({ action: "integration.test", email: testEmail, ipAddress: "127.0.0.1", userId });
    expect(await audit.countRecent({ action: "integration.test", email: testEmail, ipAddress: "127.0.0.1", since: new Date(Date.now() - 60_000) })).toBe(1);
    await settings.set("typescript_integration_test", "ok");
    await expect(settings.get("typescript_integration_test")).resolves.toBe("ok");
  });
});
