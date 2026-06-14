const SYSTEM =
  'You are Banwolaw Hub AI, a legal practice assistant for licensed attorneys. '
  + 'Provide professional drafting and research support. Never present output as final legal advice. '
  + 'Citations must be real and verifiable — if uncertain, say so.'

const JSON_ONLY =
  ' Respond with valid JSON only matching the requested schema. No markdown fences or prose outside JSON.'

function limit(text, max = 12000) {
  const value = String(text ?? '')
  return value.length > max ? `${value.slice(0, max)}…` : value
}

/**
 * @param {string} endpoint
 * @param {Record<string, unknown>} payload
 */
export function buildPrompts(endpoint, payload) {
  const user = buildUserPrompt(endpoint, payload)
  const structured = [
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
  ].includes(endpoint)

  return {
    system: SYSTEM + (structured ? JSON_ONLY : ' Use HTML for document drafts.'),
    user,
    json: structured,
  }
}

function buildUserPrompt(endpoint, payload) {
  switch (endpoint) {
    case 'chatbot':
      return `Context: ${payload.context ?? 'staff'}. Matter: ${payload.case_title ?? ''}. Message: ${payload.message ?? ''}`
    case 'document/summarize':
      return `Summarize document "${payload.document_name ?? 'document'}":\n${limit(payload.content_preview, 6000)}`
    case 'document/draft-assist':
      return `Draft HTML for template "${payload.template_name ?? 'template'}" regarding "${payload.case_title ?? 'matter'}".`
    case 'case/qa':
      return `Answer about matter "${payload.case_title ?? 'matter'}": ${payload.question ?? ''}`
    case 'intake/summary':
      return `Summarize intake for ${payload.client_name ?? 'client'} (${payload.field_count ?? 0} fields).`
    case 'timeline/summary':
      return `Summarize timeline for "${payload.case_title ?? 'matter'}" (${payload.event_count ?? 0} events).`
    case 'research/summarize-notes':
      return `Summarize ${payload.note_count ?? 0} research notes for "${payload.case_title ?? 'matter'}":\n${limit(payload.notes_text)}`
    case 'research/suggest-authorities':
      return authorityPrompt(payload)
    case 'brief/outline':
      return `HTML brief outline for "${payload.brief_title ?? 'Brief'}" on issue: ${payload.issue ?? ''}`
    case 'brief/rewrite-section':
      return `Rewrite section (${payload.instruction ?? 'Improve clarity'}):\n${limit(payload.section_html)}\nReturn HTML only.`
    case 'brief/generate-from-facts':
      return briefFromFactsPrompt(payload)
    case 'brief/build-arguments':
      return `Build ranked arguments. Issue: ${payload.issue ?? ''}. Matter: ${payload.case_title ?? ''}\nJSON: {"content":"","arguments":[{"rank":1,"title":"","theory":"","strength":"high","weaknesses":[],"authorities":[]}]}`
    case 'brief/analyze-opposition':
      return `Predict opposition and rebuttals. Issue: ${payload.issue ?? ''}\nBrief:\n${limit(payload.content_html)}\nJSON with opposing_arguments and rebuttals arrays.`
    case 'brief/enhance':
      return `Enhance brief for ${payload.enhancement_goal ?? 'clarity'}:\n${limit(payload.content_html)}\nReturn improved HTML only.`
    case 'brief/format-court':
      return `Format for ${payload.court_type ?? 'federal'} court, ${payload.citation_style ?? 'bluebook'} style:\n${limit(payload.content_html)}\nJSON: {"content_html":"","formatting_notes":[{"note":""}]}`
    case 'research/query':
      return `Research query: ${payload.query ?? ''}. Jurisdiction: ${payload.jurisdiction ?? ''}\nJSON: {"content":"","authorities":[]}`
    case 'research/search-cases':
      return `Case search for issue: ${payload.issue ?? ''}. Jurisdiction: ${payload.jurisdiction ?? ''}\nJSON: {"content":"","cases":[],"authorities":[]}`
    case 'research/generate-memo':
      return `Memo (${payload.memo_type ?? 'research_memo'}) on: ${payload.issue ?? ''}\nJSON: {"content":"","memo_sections":[{"title":"","content":""}]}`
    case 'research/analyze-statute':
      return `Statute analysis. Jurisdiction: ${payload.jurisdiction ?? ''}\nText:\n${limit(payload.statute_text)}\nJSON: {"content":"","statute_analysis":[]}`
    case 'research/strategy':
      return `Strategy for issue: ${payload.issue ?? ''}. Matter: ${payload.case_title ?? ''}\nJSON: {"content":"","strategy":{"claims":[],"defenses":[],"procedural_options":[],"jurisdictional_concerns":[],"evidentiary_support":[]}}`
    case 'research/chat': {
      const history = Array.isArray(payload.history) ? payload.history : []
      const historyText = history.map((m) => `${String(m.role ?? 'user').toUpperCase()}: ${m.content ?? ''}`).join('\n')
      return `Project: ${payload.project_name ?? ''}. Theory: ${payload.case_theory ?? ''}\n${historyText}\nUser: ${payload.message ?? ''}`
    }
    case 'motion/structure-check':
      return `Review motion "${payload.motion_title ?? ''}" structure. Required: ${JSON.stringify(payload.required_sections ?? [])}\n${limit(payload.content_html, 8000)}`
    case 'contract/review':
      return `Review contract "${payload.document_name ?? ''}":\n${limit(payload.content_preview)}\nJSON: {"content":"","issues":[]}`
    case 'letters/generate-pack':
      return `Letter pack for ${payload.client_name ?? 'Client'}, matter "${payload.case_title ?? ''}", types ${JSON.stringify(payload.letter_types ?? [])}\nJSON: {"content":"","letters":[]}`
    default:
      return 'Respond helpfully to the legal practice request.'
  }
}

function authorityPrompt(payload) {
  const parts = [
    `Suggest authorities for "${payload.case_title ?? 'matter'}". Issue: ${payload.issue ?? ''}`,
    `Practice area: ${payload.practice_area ?? ''}`,
  ]
  if (payload.saved_authorities_text) {
    parts.push(`Library:\n${payload.saved_authorities_text}`)
  }
  if (payload.notes_text) {
    parts.push(`Notes:\n${limit(payload.notes_text, 8000)}`)
  }
  parts.push('JSON: {"content":"","authorities":[{"type":"","citation":"","relevance":"","verified":false}]}')
  return parts.join('\n\n')
}

function briefFromFactsPrompt(payload) {
  return [
    `Generate ${String(payload.brief_type ?? 'memorandum_of_law').replace(/_/g, ' ')} brief.`,
    `Title: ${payload.brief_title ?? 'Brief'}`,
    `Matter: ${payload.case_title ?? ''}`,
    `Facts: ${limit(payload.case_facts)}`,
    `Statutes: ${limit(payload.statutes, 4000)}`,
    `Outcome: ${payload.desired_outcome ?? ''}`,
    'JSON: {"content":"","content_html":"","sections":[]}',
  ].join('\n')
}
