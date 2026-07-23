import { ModelCatalogService } from "@job/core";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { redirectAfterPost, withStatus } from "../../../../lib/http.js";
import { appConfig, repositories } from "../../../../lib/services.js";

/** Force the official OpenAI SDK model listing and retain cached options on failure. */
export async function POST(request: Request): Promise<Response> {
  if (await requestUser(request) === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const form = await request.formData();
  assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
  const config = appConfig();
  const repository = repositories();
  const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
  await catalog.models(true);
  const message = catalog.lastRefreshSucceeded() ? "Model catalogue refreshed from OpenAI." : "OpenAI was unavailable; cached or built-in models remain active.";
  return redirectAfterPost(request, withStatus("/settings/models", message));
}
