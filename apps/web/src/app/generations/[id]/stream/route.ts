import { parsePublicId } from "@job/db";
import { requestUser } from "../../../../lib/auth.js";
import { repositories } from "../../../../lib/services.js";

/** Format one named server-sent event with a JSON payload. */
function event(name: string, data: unknown): Uint8Array {
  return new TextEncoder().encode(`event: ${name}\ndata: ${JSON.stringify(data)}\n\n`);
}

/** Stream ownership-checked generation status, progress, tokens, cost, and errors. */
export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return new Response("Authentication required.", { status: 401 }); }
  let id: bigint;
  try { id = parsePublicId((await params).id); } catch { return new Response("Not found", { status: 404 }); }
  const repository = repositories().generations;
  if (await repository.findOwned(id, user.id) === null) { return new Response("Not found", { status: 404 }); }
  const stream = new ReadableStream<Uint8Array>({
    /** Poll MySQL until terminal state, timeout, or browser cancellation. */
    async start(controller): Promise<void> {
      const startedAt = Date.now();
      let previous = "";
      try {
        while (!request.signal.aborted && Date.now() - startedAt < 300_000) {
          const snapshot = await repository.streamSnapshot(id, user.id);
          if (snapshot === null) { controller.enqueue(event("error", { message: "Generation not found." })); break; }
          const signature = `${snapshot.status}:${snapshot.progressPercent}:${snapshot.totalTokens}:${snapshot.costPence.toString()}:${snapshot.errorMessage ?? ""}`;
          if (signature !== previous) {
            controller.enqueue(event("status", { updated_at: snapshot.updatedAt.toISOString(), value: snapshot.status }));
            controller.enqueue(event("progress", { percent: snapshot.progressPercent }));
            controller.enqueue(event("tokens", { total: snapshot.totalTokens, updated_at: snapshot.latestOutputAt?.toISOString() ?? null }));
            controller.enqueue(event("cost", { pence: snapshot.costPence.toString(), updated_at: snapshot.updatedAt.toISOString() }));
            if (snapshot.errorMessage !== null && snapshot.errorMessage !== "") { controller.enqueue(event("error", { message: snapshot.errorMessage })); }
            previous = signature;
          } else {
            controller.enqueue(new TextEncoder().encode(": ping\n\n"));
          }
          if (["completed", "failed", "cancelled", "canceled"].includes(snapshot.status.toLowerCase())) { break; }
          await new Promise((resolve) => setTimeout(resolve, 1_000));
        }
      } finally {
        controller.close();
      }
    },
  });
  return new Response(stream, { headers: { "Cache-Control": "no-cache, no-transform", "Connection": "keep-alive", "Content-Type": "text/event-stream; charset=utf-8", "X-Accel-Buffering": "no" } });
}
