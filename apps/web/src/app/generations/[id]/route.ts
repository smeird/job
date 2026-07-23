import { parsePublicId } from "@job/db";
import { requestUser } from "../../../lib/auth.js";
import { repositories } from "../../../lib/services.js";

/** Return one owned generation using string identifiers at the JSON boundary. */
export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.json({ error: "Authentication required." }, { status: 401 }); }
  let id: bigint;
  try { id = parsePublicId((await params).id); } catch { return Response.json({ error: "Invalid generation identifier." }, { status: 400 }); }
  const generation = await repositories().generations.findOwned(id, user.id);
  return generation === null
    ? Response.json({ error: "Generation not found." }, { status: 404 })
    : Response.json({ cost_pence: generation.costPence.toString(), created_at: generation.createdAt.toISOString(), cv_document_id: generation.cvDocumentId.toString(), error_message: generation.errorMessage, id: generation.id.toString(), job_document_id: generation.jobDocumentId.toString(), model: generation.model, progress_percent: generation.progressPercent, status: generation.status, thinking_time: generation.thinkingTime, updated_at: generation.updatedAt.toISOString() });
}
