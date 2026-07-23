import { parsePublicId } from "@job/db";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { repositories } from "../../../../lib/services.js";

/** Cancel a queued generation and return its existing JSON contract. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.json({ error: "Authentication required." }, { status: 401 }); }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const generation = await repositories().generations.cancelQueuedOwned(parsePublicId((await params).id, "generation id"), user.id);
    if (generation === null) {
      return Response.json({ error: "Only queued jobs can be deleted." }, { status: 422 });
    }
    return Response.json({
      cost_pence: generation.costPence.toString(),
      created_at: generation.createdAt.toISOString(),
      cv_document_id: generation.cvDocumentId.toString(),
      error_message: generation.errorMessage,
      id: generation.id.toString(),
      job_document_id: generation.jobDocumentId.toString(),
      model: generation.model,
      progress_percent: generation.progressPercent,
      status: generation.status,
      thinking_time: generation.thinkingTime,
      updated_at: generation.updatedAt.toISOString(),
    });
  } catch {
    return Response.json({ error: "Unable to delete the queued job." }, { status: 500 });
  }
}
