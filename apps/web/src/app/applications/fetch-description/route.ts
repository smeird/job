import { fetchJobPosting } from "@job/core";
import { requestUser } from "../../../lib/auth.js";
import { assertCsrf } from "../../../lib/csrf.js";
import { publicError } from "../../../lib/http.js";

/** Import a public advert into the legacy JSON response contract. */
export async function POST(request: Request): Promise<Response> {
  if (await requestUser(request) === null) {
    return Response.json({ message: "Authentication required.", status: "error" }, { status: 401 });
  }
  try {
    const form = await request.formData();
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const url = typeof form.get("source_url") === "string" ? String(form.get("source_url")) : "";
    return Response.json({ data: await fetchJobPosting(url), status: "ok" });
  } catch (error) {
    return Response.json({ message: publicError(error, "Unable to fetch that job advert. Paste the description manually if the job board blocks imports."), status: "error" }, { status: 422 });
  }
}
