import { CompanyResearchService, ModelCatalogService } from "@job/core";
import { parsePublicId } from "@job/db";
import { requestUser } from "../../../../lib/auth.js";
import { assertCsrf } from "../../../../lib/csrf.js";
import { appConfig, repositories } from "../../../../lib/services.js";

/** Return cached or newly generated company research in the existing JSON envelope. */
export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const user = await requestUser(request);
  if (user === null) { return Response.json({ message: "Authentication required.", status: "error" }, { status: 401 }); }
  try {
    assertCsrf(request, request.headers.get("x-csrf-token") ?? undefined);
    const applicationId = parsePublicId((await params).id, "application id");
    const repository = repositories();
    const config = appConfig();
    const catalog = new ModelCatalogService(repository.settings, config.openai.apiKey, config.openai.baseUrl);
    const model = await catalog.planningModel(config.openai.planModel);
    const service = new CompanyResearchService(config.openai.apiKey, config.openai.baseUrl, repository.applications, repository.usage, config.app.csrfSecret, config.openai.tariffJson);
    const result = await service.research(user.id, applicationId, model);
    return Response.json({ data: result, status: result.status });
  } catch (error) {
    const message = error instanceof Error && error.message === "Job application not found." ? "Job application not found." : "Unable to complete company research at this time.";
    return Response.json({ message, status: "error" }, { status: message === "Job application not found." ? 404 : 500 });
  }
}
