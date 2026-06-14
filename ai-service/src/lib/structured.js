const STRUCTURED = new Set([
  'research/suggest-authorities',
  'brief/generate-from-facts',
  'brief/build-arguments',
  'brief/analyze-opposition',
  'brief/format-court',
  'research/query',
  'research/search-cases',
  'research/generate-memo',
  'research/analyze-statute',
  'research/strategy',
  'contract/review',
  'letters/generate-pack',
])

export function expectsJson(endpoint) {
  return STRUCTURED.has(endpoint)
}

export function extractJson(rawContent) {
  const trimmed = String(rawContent ?? '').trim()
  if (!trimmed) return null

  try {
    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
      return JSON.parse(trimmed)
    }
  } catch {
    // continue
  }

  const fence = trimmed.match(/```(?:json)?\s*([\s\S]*?)```/i)
  if (fence) {
    try {
      return JSON.parse(fence[1].trim())
    } catch {
      return null
    }
  }

  const start = trimmed.indexOf('{')
  const end = trimmed.lastIndexOf('}')
  if (start >= 0 && end > start) {
    try {
      return JSON.parse(trimmed.slice(start, end + 1))
    } catch {
      return null
    }
  }

  return null
}

export function mergeStructured(endpoint, rawContent, parsed) {
  if (!parsed || typeof parsed !== 'object') {
    return { content: rawContent }
  }

  const result = {
    content: parsed.content ?? parsed.content_html ?? parsed.summary ?? rawContent,
  }

  const keys = [
    'content_html',
    'sections',
    'arguments',
    'opposing_arguments',
    'rebuttals',
    'formatting_notes',
    'authorities',
    'cases',
    'memo_sections',
    'statute_analysis',
    'strategy',
    'issues',
    'letters',
  ]

  for (const key of keys) {
    if (parsed[key] !== undefined) {
      result[key] = parsed[key]
    }
  }

  if (['research/query', 'research/search-cases', 'research/suggest-authorities'].includes(endpoint)) {
    result.verification_warning =
      'AI-suggested authorities may be incorrect or hallucinated. Verify every citation against primary sources before use in filings or client advice.'
    result.ranked_authorities = result.authorities ?? []
    result.validation = {
      source_authority: 'ai_generated',
      confidence_rating: 0.65,
      verification_status: 'requires_manual_verification',
    }
  }

  return result
}
