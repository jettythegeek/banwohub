import { complete } from './llm.js'
import { buildPrompts } from './prompts.js'
import { buildResponse } from './responses.js'
import { extractJson, mergeStructured } from './structured.js'

/**
 * @param {string} endpoint
 * @param {Record<string, unknown>} payload
 */
export async function handleAiRequest(endpoint, payload) {
  const prompts = buildPrompts(endpoint, payload)
  const result = await complete(prompts.system, prompts.user, {
    json: prompts.json,
    maxTokens: prompts.json ? 4096 : 2048,
  })

  const parsed = extractJson(result.content)
  const merged = mergeStructured(endpoint, result.content, parsed)

  const { content, ...extra } = merged

  return buildResponse(content, result.model, extra)
}
