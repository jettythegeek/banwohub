import { Router } from 'express'
import { handleAiRequest } from '../lib/handler.js'
import { isConfigured } from '../lib/llm.js'

const router = Router()

const routes = [
  ['post', '/chatbot', 'chatbot'],
  ['post', '/document/summarize', 'document/summarize'],
  ['post', '/document/draft-assist', 'document/draft-assist'],
  ['post', '/case/qa', 'case/qa'],
  ['post', '/intake/summary', 'intake/summary'],
  ['post', '/timeline/summary', 'timeline/summary'],
  ['post', '/research/summarize-notes', 'research/summarize-notes'],
  ['post', '/research/suggest-authorities', 'research/suggest-authorities'],
  ['post', '/brief/outline', 'brief/outline'],
  ['post', '/brief/rewrite-section', 'brief/rewrite-section'],
  ['post', '/brief/generate-from-facts', 'brief/generate-from-facts'],
  ['post', '/brief/build-arguments', 'brief/build-arguments'],
  ['post', '/brief/analyze-opposition', 'brief/analyze-opposition'],
  ['post', '/brief/enhance', 'brief/enhance'],
  ['post', '/brief/format-court', 'brief/format-court'],
  ['post', '/research/query', 'research/query'],
  ['post', '/research/search-cases', 'research/search-cases'],
  ['post', '/research/generate-memo', 'research/generate-memo'],
  ['post', '/research/analyze-statute', 'research/analyze-statute'],
  ['post', '/research/strategy', 'research/strategy'],
  ['post', '/research/chat', 'research/chat'],
  ['post', '/motion/structure-check', 'motion/structure-check'],
  ['post', '/contract/review', 'contract/review'],
  ['post', '/letters/generate-pack', 'letters/generate-pack'],
]

for (const [method, path, endpoint] of routes) {
  router[method](path, async (req, res) => {
    if (!isConfigured()) {
      return res.status(503).json({
        message: 'AI service not configured. Set AI_OPENAI_API_KEY in ai-service/.env',
      })
    }

    try {
      const body = await handleAiRequest(endpoint, req.body ?? {})
      return res.json(body)
    } catch (error) {
      const status = error?.status ?? 500
      return res.status(status).json({ message: error?.message ?? 'AI request failed.' })
    }
  })
}

export default router
