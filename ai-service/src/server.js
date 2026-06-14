import cors from 'cors'
import express from 'express'
import { requireServiceKey } from './middleware/auth.js'
import routes from './routes/index.js'
import { isConfigured } from './lib/llm.js'

const app = express()
const port = Number(process.env.AI_SERVICE_PORT || 3100)

app.use(cors())
app.use(express.json({ limit: '1mb' }))

app.get('/health', (_req, res) => {
  res.json({
    status: isConfigured() ? 'ok' : 'misconfigured',
    service: 'banwohub-ai',
    version: '1.0.0',
    llm_configured: isConfigured(),
  })
})

app.use('/v1', requireServiceKey, routes)

app.use((_req, res) => {
  res.status(404).json({ message: 'Not found.' })
})

app.listen(port, '127.0.0.1', () => {
  console.log(`Banwolaw AI service listening on http://127.0.0.1:${port}`)
})
