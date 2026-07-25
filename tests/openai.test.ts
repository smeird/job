import assert from 'node:assert/strict';
import test from 'node:test';
import { discoverOpenAiModels, modelSupportsJobTune, openAiResponseText } from '../src/openai';

/** Verifies that discovery keeps stable text aliases and rejects incompatible model types. */
test('model compatibility filtering', () => {
  assert.equal(modelSupportsJobTune('gpt-5.6-sol'), true);
  assert.equal(modelSupportsJobTune('o3-mini'), true);
  assert.equal(modelSupportsJobTune('gpt-4o-audio-preview'), false);
  assert.equal(modelSupportsJobTune('text-embedding-3-small'), false);
  assert.equal(modelSupportsJobTune('gpt-5-2026-07-25'), false);
});

/** Verifies that the account catalogue is authenticated, filtered, deduplicated, and newest-first. */
test('OpenAI account model discovery', async () => {
  const fakeFetch = async (_url: string | URL | Request, options?: RequestInit): Promise<Response> => {
    assert.equal((options?.headers as Record<string, string>).Authorization, 'Bearer synthetic-key');
    return new Response(JSON.stringify({ data: [{ id: 'gpt-5.6-sol', created: 30 }, { id: 'gpt-4o-audio-preview', created: 40 }, { id: 'o3-mini', created: 20 }, { id: 'gpt-5.6-sol', created: 10 }] }), { status: 200, headers: { 'content-type': 'application/json' } });
  };
  const models = await discoverOpenAiModels('synthetic-key', fakeFetch as typeof fetch);
  assert.deepEqual(models.map((model) => model.id), ['gpt-5.6-sol', 'o3-mini']);
});

/** Verifies both supported Responses API text shapes and rejects incomplete output. */
test('Responses API text extraction', () => {
  assert.equal(openAiResponseText({ output_text: 'direct' }), 'direct');
  assert.equal(openAiResponseText({ output: [{ content: [{ type: 'output_text', text: 'nested' }] }] }), 'nested');
  assert.equal(openAiResponseText({ output: [{ content: [{ type: 'refusal', refusal: 'no' }] }] }), null);
});
