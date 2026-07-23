import { parsePublicId } from "@job/db";
import { requestUser } from "../../../../lib/auth.js";
import { repositories } from "../../../../lib/services.js";

/** Download an original document after checking both its identifier and owner. */
export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  let id: bigint;
  try { id = parsePublicId((await params).id); } catch { return new Response("Not found", { status: 404 }); }
  const document = await repositories().documents.findOwned(id, user.id);
  if (document === null) { return new Response("Not found", { status: 404 }); }
  const filename = document.filename.replace(/["\\\r\n]/g, "");
  return new Response(new Uint8Array(document.content), { headers: { "Cache-Control": "no-store", "Content-Disposition": `attachment; filename="${filename}"`, "Content-Length": document.content.length.toString(), "Content-Type": document.mimeType } });
}
