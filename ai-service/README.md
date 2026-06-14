# Banwolaw AI Service

Separate deployable AI layer for Banwolaw Hub. Laravel orchestrates requests and stores governance logs; this service handles LLM inference via OpenAI-compatible APIs.

## Setup

```bash
cd ai-service
cp .env.example .env
# Set AI_OPENAI_API_KEY in .env
npm install
npm start
```

## Environment

| Variable | Description |
|----------|-------------|
| `AI_SERVICE_PORT` | HTTP port (default 3100) |
| `AI_SERVICE_KEY` | Bearer token Laravel sends (`AI_SERVICE_KEY` in backend `.env`) |
| `AI_OPENAI_API_KEY` | **Required** — OpenAI or compatible API key |
| `AI_OPENAI_MODEL` | Model name (default `gpt-4o-mini`) |
| `AI_OPENAI_BASE_URL` | API base URL (default OpenAI) |

## Health

`GET /health` returns `llm_configured: true` when `AI_OPENAI_API_KEY` is set.

All `/v1/*` endpoints return **503** with a clear message if the API key is missing — never stub responses.

## Alternative: Org-level providers

Organizations can configure OpenAI, Anthropic, Google AI, or Deepseek directly in **Settings → AI Providers**. When active, Laravel calls the provider directly and does not use this service.
