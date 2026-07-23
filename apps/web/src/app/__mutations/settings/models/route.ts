import { ModelCatalogService } from "@job/core";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { publicError, redirectAfterPost, requiredFormString, withStatus } from "../../../../lib/http.js";
import { appConfig, repositories } from "../../../../lib/services.js";

/** Save validated analysis and drafting model defaults in the shared settings table. */
export async function POST(request: Request): Promise<Response> {
  if (await requestUser(request) === null) { return Response.redirect(new URL("/auth/login", request.url), 302); }
  const form = await request.formData();
  try {
    assertCsrf(request, typeof form.get("_token") === "string" ? String(form.get("_token")) : undefined);
    const config = appConfig();
    const repository = repositories();
    const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
    await catalog.saveDefaults(requiredFormString(form, "plan_model"), requiredFormString(form, "draft_model"));
    return redirectAfterPost(request, withStatus("/settings/models", "Model defaults saved."));
  } catch (error) {
    return redirectAfterPost(request, withStatus("/settings/models", publicError(error), "error"));
  }
}
