# Tailored CV OpenAI Tasks

This note summarises the background jobs and OpenAI operations that run when a user queues a Tailored CV.

## Job lifecycle

1. When the user submits the tailoring form, `GenerationRepository::queueTailorJob()` stores the job payload (job description text, CV markdown, selected model, thinking time, optional contact details) in the `jobs` table with the type `tailor_cv`.【F:src/Generations/GenerationRepository.php†L531-L595】
2. A queue worker picks the job up via `TailorCvJobHandler::handle()`, which orchestrates the OpenAI calls and persists the outputs once every step succeeds.【F:src/Queue/Handler/TailorCvJobHandler.php†L62-L111】

```mermaid
flowchart TD
    A[User submits tailoring form] --> B[GenerationRepository::queueTailorJob saves payload in jobs table]
    B --> C[Queue worker dequeues tailor_cv job]
    C --> D[TailorCvJobHandler loads job artifacts]
    D --> E[Call OpenAIProvider.generatePlan]
    E --> F[Persist cv_plan artifact for auditing]
    F --> G[Call OpenAIProvider.generateDraft]
    G --> H[Store tailored CV outputs (markdown, HTML, text)]
    H --> I[Call OpenAIProvider.generateCoverLetter]
    I --> J[Save cover letter outputs]
    J --> K[Mark job as completed and notify Tailor workspace]
```

## OpenAI operations logged per run

The handler invokes the OpenAI provider three times, and each call appears in the API usage logs (`/usage` in the web app):

| Operation key | Purpose | Saved output | User-facing surface |
| --- | --- | --- | --- |
| `plan` | Generates a structured tailoring plan (summary, strengths, gaps, recommended next steps) from the job description and CV text.【F:src/Queue/Handler/TailorCvJobHandler.php†L88-L99】【F:src/AI/OpenAIProvider.php†L118-L206】 | Stored as a JSON artifact named `cv_plan` in `generation_outputs` for auditing/debugging; it is not exposed in the UI downloads.【F:src/Queue/Handler/TailorCvJobHandler.php†L97-L110】 | Currently internal only; not rendered to end users. |
| `draft` | Produces the tailored CV markdown by combining the plan with the active prompt constraints.【F:src/Queue/Handler/TailorCvJobHandler.php†L88-L105】【F:src/AI/OpenAIProvider.php†L234-L321】 | The markdown, HTML preview, and plain-text variants are saved as the `cv` artifact in `generation_outputs`.【F:src/Queue/Handler/TailorCvJobHandler.php†L97-L110】【F:src/Queue/Handler/TailorCvJobHandler.php†L219-L233】 | Exposed as Tailored CV downloads (MD, DOCX, PDF) in the Tailor wizard once the run completes.【F:src/Controllers/TailorController.php†L148-L191】【F:src/Generations/GenerationDownloadService.php†L57-L172】 |
| `cover_letter` | Builds a matching cover letter markdown draft using the generated plan, job data, CV excerpts, and saved contact details.【F:src/Queue/Handler/TailorCvJobHandler.php†L93-L105】【F:src/AI/OpenAIProvider.php†L322-L392】 | Saved as markdown, HTML, and plain text under the `cover_letter` artifact in `generation_outputs`.【F:src/Queue/Handler/TailorCvJobHandler.php†L97-L110】【F:src/Queue/Handler/TailorCvJobHandler.php†L219-L233】 | Available alongside the CV downloads in the Tailor wizard UI (MD, DOCX, PDF).【F:src/Controllers/TailorController.php†L148-L191】【F:src/Generations/GenerationDownloadService.php†L57-L172】 |

The OpenAI provider records token usage and metadata for each call in the `api_usage` table, which powers the `/usage` dashboard for the signed-in user.【F:src/AI/OpenAIProvider.php†L1456-L1484】【F:src/Services/UsageService.php†L31-L116】

## Where users find the results

* **Tailor workspace (`/tailor`)** – shows generation rows and provides download links for the Tailored CV and cover letter in Markdown, DOCX, or PDF formats once the job status becomes `completed`.【F:src/Controllers/TailorController.php†L148-L191】【F:public/assets/js/tailor.js†L87-L150】
* **Usage analytics (`/usage`)** – presents per-call logs sourced from `api_usage`, letting users verify each OpenAI task (plan, draft, cover letter) and its token consumption.【F:src/Controllers/UsageController.php†L37-L81】【F:src/Services/UsageService.php†L31-L116】
