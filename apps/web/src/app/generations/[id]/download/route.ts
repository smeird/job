import { parsePublicId } from "@job/db";
import { parseRestrictedMarkdown, renderDocx, renderPdf } from "@job/documents";
import { requestUser } from "../../../../lib/auth.js";
import { repositories } from "../../../../lib/services.js";

const formatMimes = {
  docx: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  md: "text/markdown",
  pdf: "application/pdf",
  txt: "text/plain",
} as const;

/** Download a stored binary output or render a legacy Markdown output on demand. */
export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  let generationId: bigint;
  try { generationId = parsePublicId((await params).id); } catch { return new Response("Not found", { status: 404 }); }
  const url = new URL(request.url);
  const artifactValue = (url.searchParams.get("artifact") ?? "cv").replace(/-/g, "_");
  const artifact = artifactValue === "cv" || artifactValue === "cover_letter" ? artifactValue : null;
  const formatValue = url.searchParams.get("format") ?? "pdf";
  const format = formatValue in formatMimes ? formatValue as keyof typeof formatMimes : null;
  if (artifact === null || format === null) { return new Response("Unsupported download format", { status: 400 }); }
  const repo = repositories().generations;
  if (await repo.findOwned(generationId, user.id) === null) { return new Response("Not found", { status: 404 }); }
  const mime = formatMimes[format];
  const direct = await repo.findOutputOwned(generationId, user.id, artifact, mime);
  let content: Buffer;
  if (direct?.content !== null && direct?.content !== undefined) {
    content = direct.content;
  } else if (direct?.outputText !== null && direct?.outputText !== undefined) {
    content = Buffer.from(direct.outputText, "utf8");
  } else {
    const markdown = await repo.findOutputOwned(generationId, user.id, artifact, "text/markdown");
    if (markdown?.outputText === null || markdown === null) { return new Response("Output unavailable", { status: 404 }); }
    const tree = parseRestrictedMarkdown(markdown.outputText);
    content = format === "docx" ? await renderDocx(tree) : format === "pdf" ? await renderPdf(tree, { title: artifact === "cv" ? "Tailored CV" : "Cover letter" }) : Buffer.from(markdown.outputText, "utf8");
  }
  const basename = artifact === "cv" ? "tailored-cv" : "cover-letter";
  return new Response(new Uint8Array(content), { headers: { "Cache-Control": "no-store", "Content-Disposition": `attachment; filename="${basename}-${generationId.toString()}.${format}"`, "Content-Length": content.length.toString(), "Content-Type": mime } });
}
