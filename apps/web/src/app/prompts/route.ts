import { SYSTEM_PROMPT, TAILOR_PROMPT } from "@job/core";

/** Preserve the prompt-inspection JSON contract for fixture and operational verification. */
export function GET(): Response {
  return Response.json({ system: SYSTEM_PROMPT, tailor: TAILOR_PROMPT });
}
