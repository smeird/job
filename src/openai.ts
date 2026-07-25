/** Describes one text model returned by the OpenAI model catalogue. */
export type OpenAiModel = { id: string; created: number };

/** Keeps only stable text-generation model aliases that Job Tune can use with the Responses API. */
export function modelSupportsJobTune(modelId: string): boolean {
  const id = modelId.trim().toLowerCase();
  const textFamily = id.startsWith('gpt-') || /^o(?:1|3|4)(?:-|$)/.test(id);
  const incompatibleCapability = /(audio|realtime|transcribe|tts|speech|image|embedding|moderation|search|instruct|codex|deep-research|computer-use)/.test(id);
  const datedSnapshot = /-\d{4}-\d{2}-\d{2}$/.test(id);
  return Boolean(id && textFamily && !id.startsWith('ft:') && !incompatibleCapability && !datedSnapshot);
}

/** Fetches and filters the models currently available to the configured OpenAI API project. */
export async function discoverOpenAiModels(apiKey: string, fetcher: typeof fetch = fetch): Promise<OpenAiModel[]> {
  const response = await fetcher('https://api.openai.com/v1/models', { headers: { Authorization: `Bearer ${apiKey}` }, signal: AbortSignal.timeout(15000) });
  if (!response.ok) throw new Error('OpenAI did not accept the model catalogue request.');
  const payload = await response.json() as { data?: Array<{ id?: unknown; created?: unknown }> };
  const models = (payload.data || [])
    .filter((model): model is { id: string; created?: unknown } => typeof model.id === 'string' && modelSupportsJobTune(model.id))
    .map((model) => ({ id: model.id, created: typeof model.created === 'number' ? model.created : 0 }))
    .sort((left, right) => right.created - left.created || left.id.localeCompare(right.id));
  return [...new Map(models.map((model) => [model.id, model])).values()];
}

/** Extracts the assistant text emitted by a successful OpenAI Responses API call. */
export function openAiResponseText(payload: unknown): string | null {
  if (!payload || typeof payload !== 'object') return null;
  const response = payload as { output_text?: unknown; output?: unknown };
  if (typeof response.output_text === 'string' && response.output_text.trim()) return response.output_text;
  if (!Array.isArray(response.output)) return null;
  for (const item of response.output) {
    if (!item || typeof item !== 'object' || !Array.isArray((item as { content?: unknown }).content)) continue;
    for (const content of (item as { content: unknown[] }).content) {
      if (content && typeof content === 'object' && (content as { type?: unknown }).type === 'output_text' && typeof (content as { text?: unknown }).text === 'string') return (content as { text: string }).text;
    }
  }
  return null;
}
