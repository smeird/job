import { requestUser } from "../../../lib/auth.js";
import { assertCsrf } from "../../../lib/csrf.js";
import { repositories } from "../../../lib/services.js";

/** Remove one user's abandoned jobs, failed runs, and related failure audit entries. */
export async function POST(request: Request): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.json({ error: "Authentication required." }, { status: 401 }); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const repository = repositories();
    const [cleanup, clearedLogs] = await Promise.all([
      repository.generations.cleanupOwned(user.id),
      repository.audit.clearGenerationFailures(user.id),
    ]);
    const generations = await repository.generations.listForUser(user.id);
    return Response.json({
      cleared_logs: clearedLogs,
      generation_logs: [],
      generations: generations.map((generation) => ({
        created_at: generation.createdAt.toISOString(),
        cv_document: { filename: generation.cvFilename ?? "", id: generation.cvDocumentId.toString() },
        id: generation.id.toString(),
        job_document: { filename: generation.jobFilename ?? "", id: generation.jobDocumentId.toString() },
        model: generation.model,
        status: generation.status,
        thinking_time: generation.thinkingTime,
      })),
      removed_failed_generations: cleanup.removedFailedGenerations,
      removed_jobs: cleanup.removedJobs,
    });
  } catch {
    return Response.json({ error: "Unable to clean up tailoring data." }, { status: 500 });
  }
}
