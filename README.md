# Banwolaw Hub

Single-organization legal practice management platform for **Banwolaw** (not multi-tenant SaaS).

## Stack

| Layer    | Technology                                      |
|----------|-------------------------------------------------|
| Frontend | Vue 3, TypeScript, Vite, Tailwind CSS v4, Pinia |
| Backend  | Laravel 13 API, Sanctum, Spatie Permission      |
| Database | MySQL (`banwohub`)                              |

Documentation lives in [`docs/`](docs/). Phase 1 scope: [`docs/phases/phase-1-foundation.md`](docs/phases/phase-1-foundation.md).


## Local development URL (XAMPP)

Use these URLs (not `localhost:3000` / `localhost:8000`):

| Service | URL |
|---------|-----|
| **App UI** | http://banwohub.test |
| **API** | http://api.banwohub.test/api/v1 |

The UI is proxied by Apache to the Vue/Vite dev server on port **3000**. The API is served by Apache from `backend/public` (no `php artisan serve` required for normal XAMPP dev).

### 1. Windows hosts file

Add (requires **Administrator** to edit `C:\Windows\System32\drivers\etc\hosts`):

```
127.0.0.1 banwohub.test
127.0.0.1 api.banwohub.test
```

Or run as admin from the repo root:

```powershell
.\scripts\add-hosts-banwohub.ps1
```

### 2. XAMPP Apache

1. In **XAMPP Control Panel**, start **Apache** and **MySQL**.
2. In `C:\xampp\apache\conf\httpd.conf`, ensure these are **uncommented**:
   - `LoadModule proxy_module modules/mod_proxy.so`
   - `LoadModule proxy_http_module modules/mod_proxy_http.so`
   - `Include conf/extra/httpd-vhosts.conf`
3. Append the contents of [`docs/setup/xampp-vhosts.conf`](docs/setup/xampp-vhosts.conf) (same as [`scripts/xampp-banwohub.test.conf`](scripts/xampp-banwohub.test.conf)) to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.
4. **Restart Apache** in the control panel.

### 3. Application environment

Copy examples and align URLs:

```bash
cd backend
copy .env.example .env
```

Key values in `backend/.env`:

```env
APP_URL=http://api.banwohub.test
SESSION_DOMAIN=.banwohub.test
SANCTUM_STATEFUL_DOMAINS=banwohub.test,api.banwohub.test
FRONTEND_URL=http://banwohub.test
CORS_ALLOWED_ORIGINS=http://banwohub.test
APP_CURRENCY=USD
APP_CURRENCY_SYMBOL=$
APP_CURRENCY_LOCALE=en-US
```

All monetary amounts in the UI and API default to **USD ($)**. Frontend formatting uses `frontend/src/lib/currency.ts`; backend defaults use `config/currency.php` and `App\Support\Currency`.

```bash
cd frontend
copy .env.example .env
```

```env
VITE_API_URL=http://api.banwohub.test/api/v1
```

### 4. Start dev

```powershell
.\scripts\start-dev.ps1
```

Then open **http://banwohub.test**.

Opening `http://localhost/banwohub/` redirects to http://banwohub.test via the repo root `index.html`.


## Prerequisites

- [XAMPP](https://www.apachefriends.org/) (or MySQL 8+) with database `banwohub` created
- PHP 8.3+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- [Composer](https://getcomposer.org/)
- Node.js 20+ and npm

## Project structure

```
banwohub/
├── backend/          # Laravel 13 API
├── frontend/         # Vue 3 + Vite app
├── docs/             # Product & technical docs
└── README.md
```

## 1. Database (XAMPP MySQL)

Create the database in phpMyAdmin or CLI:

```sql
CREATE DATABASE banwohub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2. Backend setup

```bash
cd backend
copy .env.example .env
```

Edit `backend/.env` — set `DB_USERNAME`, `DB_PASSWORD` (do not commit `.env`):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=banwohub
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

> **Note:** `.env` is gitignored. If you use a non-root XAMPP user, set `DB_USERNAME` / `DB_PASSWORD` accordingly before migrating.

Generate key (if needed), migrate, seed:

```bash
php artisan key:generate
php artisan migrate --seed
```

API (XAMPP vhosts): `http://api.banwohub.test/api/v1` — Apache serves `backend/public`; do not run `php artisan serve` unless you intentionally use the localhost fallback below.

**Localhost fallback only:** `php artisan serve` + set `APP_URL=http://localhost:8000`, `VITE_API_URL=http://127.0.0.1:8000/api/v1`, open `http://127.0.0.1:3000`.

### Demo users (after seed)

| Email                 | Role         | Default password (override in `.env`) |
|-----------------------|--------------|----------------------------------------|
| admin@banwolaw.com    | Firm Admin   | `SEED_ADMIN_PASSWORD` or `ChangeMe123!` |
| sysadmin@banwolaw.com | System Admin | `SEED_SYSADMIN_PASSWORD` or `ChangeMe123!` |

Public registration is **not** exposed.

## 3. Frontend setup

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

App (with vhosts): **http://banwohub.test** — run `.\scripts\start-dev.ps1` (Vite on :3000 behind Apache)

## 4. Verify

```bash
cd backend && php artisan route:list --path=api
cd frontend && npm run build
```

## E2E smoke tests (Playwright)

Minimal browser smoke suite for staff login, dashboard navigation, case tasks kanban, and portal login page load (Slice 13).

### Prerequisites

- Backend API reachable (XAMPP vhosts or `php artisan serve` on port 8000)
- Frontend dev server on port 3000 (`npm run dev` or `.\scripts\start-dev.ps1`)
- Database migrated and seeded, plus E2E fixtures:

```bash
cd backend
php artisan migrate --seed
php artisan db:seed --class=E2eSmokeSeeder
```

### Run locally

```bash
cd frontend
npm install
npx playwright install chromium
npm run test:e2e
```

Useful variants:

```bash
npm run test:e2e:headed   # visible browser
npm run test:e2e:ui       # Playwright UI mode
```

Environment overrides (optional):

| Variable | Default |
|----------|---------|
| `PLAYWRIGHT_BASE_URL` | `http://127.0.0.1:3000` |
| `E2E_STAFF_EMAIL` | `admin@banwolaw.com` |
| `E2E_STAFF_PASSWORD` | `ChangeMe123!` (or `SEED_ADMIN_PASSWORD`) |
| `PLAYWRIGHT_SKIP_WEBSERVER` | unset — Playwright starts `npm run dev` if port 3000 is free |

For XAMPP (`http://banwohub.test`), set `PLAYWRIGHT_BASE_URL=http://banwohub.test` and `PLAYWRIGHT_SKIP_WEBSERVER=1` while Vite/Apache are already running.

CI runs via [`.github/workflows/e2e-smoke.yml`](.github/workflows/e2e-smoke.yml) on push/PR (`continue-on-error` until the full stack is stable in Actions).

## Password reset (local)

With `MAIL_MAILER=log` in `backend/.env`, reset emails are written to `backend/storage/logs/laravel.log`. The API also logs each reset URL and, when `APP_DEBUG=true`, returns a `reset_link` in the JSON response.

Set the frontend reset page URL (see `backend/.env.example`):

```env
PASSWORD_RESET_FRONTEND_URL=http://banwohub.test/reset-password
```

**Test flow**

1. Open http://banwohub.test/forgot-password
2. Submit a seeded email (e.g. `admin@banwolaw.com`)
3. Copy the reset link from the success screen (debug), from `laravel.log`, or from the logged mail entry
4. Open the link → set a new password → sign in at http://banwohub.test/login

## API overview (Phase 1)

| Method | Path                    | Description              |
|--------|-------------------------|--------------------------|
| POST   | `/auth/login`           | Login (token)            |
| POST   | `/auth/forgot-password` | Request password reset   |
| POST   | `/auth/reset-password`  | Set new password (token) |
| POST   | `/auth/logout`          | Logout                   |
| GET    | `/auth/me`              | Current user             |
| GET    | `/dashboard`      | KPI counts         |
| GET/PUT| `/organization`   | Firm settings      |
| *      | `/clients`        | Client CRUD        |
| *      | `/cases`          | Case/matter CRUD   |

All protected routes require `Authorization: Bearer {token}`.

## AI providers (Phase 4)

Firm Admins configure org-level AI in **Settings → AI Providers** (`ai.providers.manage`):

1. Enter an API key for OpenAI, Anthropic, Google AI (Gemini), or Deepseek
2. Enable the provider and save
3. **Test connection**, then **Set active** — one active provider drives all AI features

API keys are encrypted at rest and masked in API responses. With no active provider, `AI_STUB_MODE=true` returns labeled stub responses (default for local dev).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/settings/ai-providers` | List provider configs (masked keys) |
| PUT | `/settings/ai-providers` | Update provider key, model, enabled |
| PUT | `/settings/ai-providers/active` | Set the single active provider |
| POST | `/settings/ai-providers/{provider}/test-connection` | Validate API key |

Verify: `cd backend && php artisan migrate && php scripts/verify-phase4.php`

## Design system

Flat UI — no box shadows. Tokens: `docs/03-design-system/tokens.css` (copied to `frontend/src/styles/tokens.css`).

## Troubleshooting

- **Site not opening / 404 at `http://localhost/banwohub/`**: Wrong URL. Use **http://banwohub.test** after: (1) `.\scripts\add-hosts-banwohub.ps1` as Administrator, (2) Apache vhosts from `docs/setup/xampp-vhosts.conf`, (3) `.\scripts\start-dev.ps1` for Vite. Restart Apache after vhost changes.
- **Composer not found**: use full path `C:\ProgramData\ComposerSetup\bin\composer.bat` or add Composer to PATH.
- **npm not found**: add `C:\Program Files\nodejs` to PATH.
- **MySQL connection refused**: start MySQL in XAMPP; confirm `banwohub` exists and credentials in `.env`.
- **CORS errors**: set `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS` in `backend/.env` to `http://banwohub.test` / `banwohub.test` (see Local development URL above).
- **Login fails with correct password**: Usually the API is unreachable (misleading message). Keep `php artisan serve` running (PHP 8.4+), restart Vite after `.env` changes, restart Apache for `/api` proxy. Test: `cd backend` then `php scripts\verify-login.php` (expect `password_ok`). Re-seed only if needed: `php artisan migrate:fresh --seed`.
- **Login page prefilled**: should be empty; hard-refresh the browser if you still see old values.
- **503 Service Unavailable at banwohub.test**: Vite is not running on port 3000. Run `.\scripts\start-dev.ps1` (or `cd frontend && npm run dev`).
- **Laragon shows static placeholder instead of the app**: Laragon’s default vhost serves the repo root. Run `.\scripts\setup-laragon.ps1` as Administrator (enables Apache proxy + custom vhosts), then `.\scripts\start-dev.ps1`.
- **banwohub.test shows static page or API 500**: run `scripts\enable-xampp-proxy.ps1` (Admin), sync vhosts, restart Apache, then `scripts\start-dev.ps1`.

## Phase 1 status

See parent agent handoff or `docs/phases/phase-1-foundation.md` checklist for done vs remaining items.
