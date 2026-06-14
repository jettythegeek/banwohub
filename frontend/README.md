# Banwolaw Hub — Frontend

Vue 3 + TypeScript + Vite. Design tokens: `src/styles/tokens.css` (from `docs/03-design-system/tokens.css`).

## Setup

```bash
copy .env.example .env
npm install
```

## Development

```bash
npm run dev
```

Vite listens on **http://127.0.0.1:3000**. With XAMPP vhosts, open **http://banwohub.test** (Apache proxies to :3000).

Or from repo root:

```powershell
.\scripts\start-dev.ps1
```

## Environment

```env
VITE_API_URL=http://127.0.0.1:8000/api/v1
```

Use `http://api.banwohub.test/api/v1` when the API is served via Apache vhost instead of `artisan serve`.

## Build

```bash
npm run build
```

Output: `dist/`
