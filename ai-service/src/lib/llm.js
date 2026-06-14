const OPENAI_API_KEY = process.env.AI_OPENAI_API_KEY || ''
const OPENAI_MODEL = process.env.AI_OPENAI_MODEL || 'gpt-4o-mini'
const OPENAI_BASE_URL = (process.env.AI_OPENAI_BASE_URL || 'https://api.openai.com/v1').replace(/\/$/, '')

export function isConfigured() {
  return OPENAI_API_KEY.trim() !== ''
}

/**
 * @param {string} systemPrompt
 * @param {string} userPrompt
 * @param {{ json?: boolean, maxTokens?: number }} [options]
 */
export async function complete(systemPrompt, userPrompt, options = {}) {
  if (!isConfigured()) {
    const error = new Error('AI service not configured. Set AI_OPENAI_API_KEY in ai-service/.env')
    error.status = 503
    throw error
  }

  const body = {
    model: OPENAI_MODEL,
    messages: [
      { role: 'system', content: systemPrompt },
      { role: 'user', content: userPrompt },
    ],
    temperature: 0.3,
    max_tokens: options.maxTokens ?? 4096,
  }

  if (options.json) {
    body.response_format = { type: 'json_object' }
  }

  const response = await fetch(`${OPENAI_BASE_URL}/chat/completions`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${OPENAI_API_KEY}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  })

  if (!response.ok) {
    const error = new Error(`LLM request failed: ${response.status}`)
    error.status = 502
    throw error
  }

  const data = await response.json()
  const content = data?.choices?.[0]?.message?.content ?? ''

  return {
    content: String(content),
    model: String(data?.model ?? OPENAI_MODEL),
  }
}
