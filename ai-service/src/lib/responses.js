export function buildResponse(content, model = 'unknown', extra = {}) {
  return {
    output_id: crypto.randomUUID(),
    content,
    labeled: true,
    label: 'AI-generated',
    disclaimer:
      'AI-generated content is for assistance only and must be reviewed by a qualified legal professional before use.',
    model,
    requires_review: true,
    ...extra,
  }
}
