# 100% Completion Roadmap

**Goal:** Close every documented gap between Banwolaw Hub and the full product spec (`product description.md`, `docs/modules/`, phase guides, and UI reference mockups).

**Current baseline (2026-06-05):** Phases 1–5 MVP slices are signed off with automated verification (`verify-phase2.php` through `verify-phase5.php`). Functional APIs and staff/portal flows exist for core legal operations, client business ops, AI/automation, and advanced litigation tools. Remaining work is **example parity** (TipTap/OnlyOffice/Trello-quality UX), **UI polish** (CaseIQ reference layouts), **module depth** (attachments, workload views, merge fields), and **platform hardening** (realtime, search, security extras).

---

## Gap inventory

Legend: **Done** = meets phase acceptance; **Partial** = scaffold or MVP only; **Missing** = not started.

### Cross-cutting

| Area | Spec source | Status | Gap |
|------|-------------|--------|-----|
| Global search (OpenSearch) | modules/33, tech-stack | Partial | LIKE search MVP across cases/clients/documents (Slice 8); no OpenSearch index yet |
| Audit trail UI | modules/37 | Partial | Activity log writes exist; no firm-wide audit explorer |
| Security (2FA, encryption at rest docs) | modules/36 | Partial | TOTP 2FA done (Slice 12); session policies, key rotation not implemented |
| Onboarding wizard | modules/39 | Missing | No multi-step firm setup wizard |
| Mobile responsive polish | modules/40 | Partial | Breakpoints used; reference mobile form patterns not fully applied |
| Integrations hub | modules/42 | Partial | Stripe/PayPal/AI providers; no SMS/WhatsApp, court e-filing, calendar sync |
| Realtime (Reverb/Pusher) | phase-3, tech-stack | Partial | Reverb wired for message threads (Slice 7); notification toasts still poll-based |
| CI / automated E2E | non-functional | Partial | Playwright smoke suite + GitHub Actions (Slice 13); expand coverage post-MVP |

### Phase 1 — Foundation

| Module | Status | Gap |
|--------|--------|-----|
| Auth, org, users, RBAC | Done | Password reset + TOTP 2FA (Slice 12) |
| Clients, cases, dashboard | Done | Practice-area widgets deferred; KPI charts + donut done (Slice 3) |
| Case detail shell | Partial | Tabs exist; overview tab missing reference metric grid, billing trend, mini calendar |
| Design system adoption | Partial | Tokens + flat cards; intake pipeline dots (Slice 2); dashboard charts (Slice 3) |

### Phase 2 — Core legal operations

| Module | Status | Gap |
|--------|--------|-----|
| Intake forms + convert | Done | Draggable kanban + Work Status pipeline (Slice 2) |
| Conflict check | Done | — |
| Documents (basic) | Partial | Upload, TipTap, templates, PDF export, version diff (Slice 4/6); **OnlyOffice DOCX embed (Slice 5)**; no merge fields |
| Tasks | Partial | Draggable Kanban (Slice 1); attachments, comments, checklist (Slice 9); **calendar/workload views** still missing |
| Calendar + reminders | Done | Firm/case views; no task calendar overlay |
| Notes | Done | — |
| Notifications | Done | Email log + in-app; no push/realtime toasts |

### Phase 3 — Client & business

| Module | Status | Gap |
|--------|--------|-----|
| Client portal | Done | E-sign now in Phase 4; portal UX polish vs reference |
| Messaging | Partial | Reverb live thread updates (Slice 7); poll fallback when Reverb unset |
| Billing, time, expenses, reports | Done | Invoice table lacks reference bulk-action footer polish |
| Appointments | Done | — |
| CRM comm log | Done | — |

### Phase 4 — AI & automation

| Module | Status | Gap |
|--------|--------|-----|
| AI service + governance | Done | Multi-provider routing |
| AI drafting + review workflow | Partial | No **version history** (AI vs human edits) |
| Document automation | Missing | Merge fields, clause library, compare versions |
| Track changes / OnlyOffice | Partial | TipTap comments + version history (Slice 4); **OnlyOffice DOCX JWT embed (Slice 5)** |
| Approval workflows | Done | Motion/filing pipeline partial (Phase 5) |
| E-signature | Partial | Basic sign flow; no signing order/reminders |
| AI chatbots | Partial | Staff `/ai-assistant` only; **no dedicated lawyer workspace bot UI** |
| Research assistant (lite) | Done | Save-to-case folder deferred |

### Phase 5 — Advanced legal tools

| Module | Status | Gap |
|--------|--------|-----|
| Filings + court forms | Partial | Manual filing + statuses; **no e-filing API, no AI form validation** |
| Evidence + e-discovery | Partial | Core flows; **no OCR/full-text OpenSearch at scale** |
| Briefs, motions, research KB | Done | Citation automation polish deferred |
| Knowledge, projects, analytics, CLE | Done | Predictive ML = rule-based hints; external research DB missing |
| Consultant portal | Partial | RBAC scoping; no dedicated consultant UX |

### UI / design system (reference parity)

| Reference pattern | Source | Status | Gap |
|-------------------|--------|--------|-----|
| Dashboard KPI + charts | ui-reference-summary | Done | Stat cards + cases-by-status bars, invoice donut, revenue trend, task workload (Slice 3) |
| Intake Kanban pipeline | ui-reference-summary | Done | Draggable kanban + Work Status bar with semantic dots (Slice 2) |
| Billing table + aging bars | ui-reference-summary | Partial | Functional tables; summary aging visuals missing |
| Matter overview widgets | ui-reference-summary | Missing | Trust balance, billing trend, case value breakdown |
| Semantic status colors | 02-colors.md, tokens | Partial | Badge variants exist; **task/case kanban dot tokens** (Slice 1b) |
| Flat UI (no shadows) | design system | Done | — |

### Product description anchors

| Example in PRD | Intended experience | Current |
|----------------|---------------------|---------|
| **Trello / Kanban board** | Drag cards across columns | Draggable case tasks + intake board (Slices 1–2) |
| **OnlyOffice** | Advanced DOCX editing | JWT embed + Docker compose POC (Slice 5) |
| **TipTap** | Rich editing + track changes | TipTap without track-change extension |
| **PDF / template generation** | Court forms, invoices | DomPDF + templates (done) |

---

## Workstreams

Workstreams run in parallel where possible; **slice order** below defines merge sequence.

### WS-A — Example parity: documents

1. TipTap extensions: comments, suggestion mode / track changes evaluation
2. Document version diff UI (metadata + HTML side-by-side)
3. OnlyOffice Document Server spike (Docker, JWT, case document embed)
4. Merge fields + clause library (template engine)
5. AI vs human version lineage on `legal_documents`

### WS-B — Example parity: tasks & intake

1. **Slice 1:** Draggable case-task Kanban (`vue-draggable-plus`), PATCH on drop
2. Intake review queue Kanban + Work Status pipeline bar
3. Task attachments, comments, checklists
4. Team workload view + task calendar view
5. Auto-task rule coverage audit (filing rejection, invoice overdue)

### WS-C — UI beauty (CaseIQ → Banwolaw)

1. **Slice 1b:** Semantic status dot tokens (task, case, intake pipeline)
2. ~~Dashboard chart row + invoice status donut~~ (Slice 3)
3. Case overview tab widgets (deadline ring, billing trend)
4. Billing view aging bars + bulk footer
5. Intake/toolbar patterns (search, filters, view toggle polish)

### WS-D — Platform robustness

1. Laravel Reverb for messages + notification toasts
2. OpenSearch indexing pipeline (documents, notes, messages)
3. Audit log explorer UI
4. 2FA (TOTP) for staff roles
5. Browser E2E smoke (login, case task drag, portal pay)
6. Backup/virus-scan job hooks (tech-stack queue list)

### WS-E — Phase 4/5 depth

1. Public AI support chatbot + lead capture
2. E-sign signing order + reminders
3. Court e-filing integration adapter (when API available)
4. E-discovery OCR + OpenSearch
5. External legal research DB connector
6. Consultant portal shell

---

## Slice order (recommended)

| Order | Slice | Workstream | Outcome | Verify |
|-------|-------|------------|---------|--------|
| **1** | Draggable task Kanban | WS-B | Trello-style case tasks; PATCH status on drop | `verify-phase2.php` + frontend build |
| **1b** | Semantic status tokens | WS-C | Kanban column dots + badge consistency | tokens + `vue-tsc` |
| 2 | Intake Kanban + pipeline bar | WS-B, WS-C | Reference intake board | extend verify-phase2 |
| 3 | Dashboard charts row | WS-C | KPI + invoice donut | `verify-phase2.php` + frontend build |
| 4 | TipTap track-changes spike | WS-A | Comments + version history foundation | verify-phase4 |
| 5 | OnlyOffice evaluation | WS-A | Docker compose + embed POC | manual |
| 6 | Document version diff | WS-A | Compare two versions in UI | verify-phase4 |
| 7 | Reverb messaging | WS-D | Live thread updates | verify-phase3 |
| 8 | OpenSearch MVP | WS-D | Global search API | new verify script |
| **9** | Task attachments + comments | WS-B | Full task module spec | verify-phase2 |
| 10 | Public AI chatbot | WS-E | MVP module 19 complete | verify-phase4 |
| 11 | Audit explorer | WS-D | Module 37 UI | verify-audit.php |
| **12** | 2FA (TOTP) | WS-D | Module 36 | verify-rbac.php |
| **13** | E2E smoke suite | WS-D | CI gate | GitHub Actions |
| 14+ | E-filing, OCR, research DB | WS-E | Post-MVP integrations | per vendor |

---

## Slice 1 — acceptance criteria

- [x] `vue-draggable-plus` added to frontend dependencies
- [x] `CaseTasksPanel.vue` Kanban columns use drag-and-drop between statuses
- [x] Dropping a card calls `PATCH /tasks/{id}` with new `status`
- [x] Optimistic UI with rollback on API error
- [x] Column headers show semantic status dot per design tokens
- [x] `verify-phase2.php` includes PATCH status transition + frontend Kanban checks
- [x] Regressions: phase2/3/4/5 verify scripts pass

---

## Slice 1b — acceptance criteria

- [x] Task and case status dot CSS variables in `tokens.css` (docs + frontend)
- [x] Kanban column headers consume dot tokens
- [x] `status.ts` documents mapping for reuse in intake/case badges

---

## Slice 2 — acceptance criteria

- [x] `IntakeView.vue` submissions board uses drag-and-drop across pipeline columns (New → Reviewing → Rejected → Qualified)
- [x] Dropping a card calls `PATCH /intake-submissions/{id}` with mapped `status`
- [x] Optimistic UI with rollback on API error
- [x] Work Status bar and kanban column headers use `--status-intake-*` semantic dot tokens
- [x] `verify-phase2.php` includes PATCH intake status transitions + frontend IntakeView kanban checks
- [x] Regressions: phase2/3 verify scripts pass

---

## Slice 3 — acceptance criteria

- [x] `DashboardView.vue` chart row: cases-by-status bars, invoice status donut, revenue trend SVG, task workload mini bars
- [x] Semantic colors from `--status-case-*`, `--status-task-*`, `--status-invoice-*` tokens via `statusDotVar()`
- [x] `GET /dashboard` returns `charts.cases_by_status`, `charts.invoices_by_status`, `charts.revenue_trend`, `charts.task_workload`
- [x] CSS/SVG only — no chart library dependency
- [x] `verify-phase2.php` includes dashboard API chart keys + DashboardView frontend checks
- [x] Regressions: phase2/3 verify scripts pass

---

## Slice 4 — acceptance criteria (100% roadmap: document editor parity)

- [x] `document_versions` table — `document_id`, `content_html`, `version_number`, `created_by`, `change_summary`
- [x] Version snapshot on each document content save (create, draft, AI draft, PATCH)
- [x] API `GET /documents/{id}/versions` and `GET /documents/{id}/versions/compare`
- [x] `RichTextEditor.vue` — TipTap Highlight + inline comment marks (`CommentMark`) with comments sidebar
- [x] `CaseDocumentsPanel` — version list, change summary on save, side-by-side compare view
- [x] `.env.example` — `ONLYOFFICE_URL` placeholder for Slice 5 evaluation (no integration yet)
- [x] `verify-phase4.php` extended for version history + editor parity checks
- [x] Regressions: phase2/3 verify scripts pass

**TipTap vs OnlyOffice (Slice 5 recommendation):** Keep TipTap for HTML drafts, merge fields, and inline comments in the staff editor. Use OnlyOffice Document Server in Slice 5 for DOCX track-changes parity on uploaded Word files — embed via JWT-signed iframe when `ONLYOFFICE_URL` is set; TipTap remains the lightweight path for templates and AI drafts.

---

## Slice 5 — acceptance criteria (OnlyOffice evaluation)

- [x] `OnlyOfficeConfigService` — JWT sign/decode, signed file URL, editor config builder
- [x] API `GET /documents/{id}/onlyoffice-config`, signed file route, `POST onlyoffice-callback` save
- [x] `OnlyOfficeEditor.vue` — DocsAPI embed with save/close events
- [x] `CaseDocumentsPanel` — "Edit in Word" for uploaded DOCX; OnlyOffice panel wired
- [x] `docker-compose.onlyoffice.yml` — Document Server with JWT enabled
- [x] `.env.example` — `ONLYOFFICE_URL`, `ONLYOFFICE_JWT_SECRET`, `ONLYOFFICE_FILE_URL_TTL`
- [x] `verify-phase4.php` extended for OnlyOffice config + frontend wiring checks
- [x] Regressions: phase2/3 verify scripts pass

---

## Slice 6 — acceptance criteria (document version diff)

- [x] Delivered in Slice 4 — `GET /documents/{id}/versions/compare` + side-by-side UI in `CaseDocumentsPanel`
- [x] Roadmap Slice 6 marked complete (no duplicate work)

---

## Slice 7 — acceptance criteria (Reverb messaging)

- [x] Laravel Reverb config in `.env.example` (`BROADCAST_CONNECTION=reverb`, `REVERB_*`)
- [x] `MessageSent` event broadcasts on private `message-thread.{id}` channel
- [x] `routes/channels.php` authorizes staff + portal client participants
- [x] Staff + portal message controllers dispatch `MessageSent` after create
- [x] `MessageThreadPanel.vue` — Laravel Echo listener; 15s poll only when Reverb unset
- [x] `frontend/src/lib/echo.ts` — Reverb client with Sanctum bearer auth
- [x] `verify-phase3.php` extended for broadcast event + frontend Echo checks
- [x] Regressions: phase2/4/5 verify scripts pass

---

## Slice 9 — acceptance criteria (task attachments + comments)

- [x] `task_attachments` table — `legal_task_id`, `path`, `name`, `size`, uploader metadata
- [x] `task_comments` table — `legal_task_id`, `user_id`, `body`
- [x] API `POST/GET/DELETE /tasks/{id}/attachments`, download route; `POST/GET /tasks/{id}/comments`
- [x] Checklist via existing `legal_tasks.checklist` JSON — PATCH on task update
- [x] `CaseTasksPanel.vue` — task detail panel with attachments, comment thread, checklist
- [x] RBAC reuses `tasks.view` / `tasks.update` on parent task
- [x] `verify-phase2.php` extended for attachment/comment API + frontend wiring
- [x] Regressions: phase2/3/4/5 verify scripts pass

---

## Slice 8 — acceptance criteria (global search MVP)

- [x] `GET /search?q=` — unified LIKE results for cases, clients, documents (org-scoped)
- [x] `SearchView.vue` — grouped results with links to case/client/document workspace
- [x] `Topbar.vue` search submits to `/search?q=`
- [x] `verify-search.php` — API + frontend wiring checks
- [x] Regressions: phase2/3 verify scripts pass

---

## Slice 11 — acceptance criteria (audit explorer)

- [x] `GET /audit-logs` — Spatie activity log, org-scoped, paginated
- [x] Filters: `user_id`, `subject_type`, `from_date`, `to_date`, `action`
- [x] RBAC `audit.view` for Firm Admin / System Admin
- [x] Auth login + failed login recorded in activity log
- [x] `AuditView.vue` — filterable table at `/audit`; Settings → Audit tab link
- [x] `verify-audit.php` — API filters, RBAC deny, frontend wiring
- [x] Regressions: phase2/3/4/5/search verify scripts pass

---

## Slice 12 — acceptance criteria (2FA TOTP)

- [x] `users` migration — `two_factor_secret` (encrypted), `two_factor_enabled`, `two_factor_confirmed_at`
- [x] `TotpService` — RFC 6238 TOTP (no external composer dependency)
- [x] API `POST /auth/two-factor/enable`, `confirm`, `disable`; `GET /auth/two-factor/status`
- [x] Login returns `two_factor_required` + `challenge_token` when 2FA enabled; `POST /auth/two-factor/verify` completes login
- [x] `TwoFactorSecurityPanel.vue` — Settings → Security tab; QR via `qrcode` + manual secret
- [x] `LoginView.vue` — authenticator code step after password
- [x] RBAC: authenticated user manages own 2FA only (no admin override route)
- [x] `verify-rbac.php` extended for 2FA API + frontend wiring + migration checks
- [x] Regressions: phase2/3/4/5/search/audit verify scripts pass

---

## Slice 13 — acceptance criteria (E2E smoke suite)

- [x] `@playwright/test` added to frontend devDependencies
- [x] `playwright.config.ts` — base URL from `PLAYWRIGHT_BASE_URL`, optional `npm run dev` webServer
- [x] Smoke specs: staff login + dashboard, case tasks kanban (optional drag), portal login page load
- [x] `E2eSmokeSeeder` — idempotent case + task fixtures for task workspace tests
- [x] `.github/workflows/e2e-smoke.yml` — MySQL service, migrate/seed, API + Vite, Playwright on push/PR (`continue-on-error`)
- [x] README documents local E2E run (`npm run test:e2e`, seeder, env overrides)
- [x] Regressions: phase2/3/4/5/search/audit/rbac verify scripts pass

---

## Slice 10 — acceptance criteria (public AI support chat)

- [x] `public_chat_leads` table — session, optional name/email/phone, message, AI preview, IP metadata
- [x] API `POST /api/v1/public/chat` — unauthenticated, rate limited (IP + throttle), FAQ via `AiServiceClient` with `context: public`
- [x] Rejects internal case data fields (`legal_matter_id`, etc.); no access to case files
- [x] Lead capture when name/email/phone provided; governance log with `bot_context: public`
- [x] `PublicSupportChatView.vue` — flat Banwolaw branding, disclaimer banner, FAQ prompts, lead form
- [x] Public routes `/support` and `/chat` (redirect) — no staff login required
- [x] `verify-phase4.php` extended for public chat API + frontend wiring
- [x] Regressions: phase2/3/5/search verify scripts pass

---

## Verification commands

After each slice:

```bash
cd backend && php scripts/verify-phase2.php
cd backend && php scripts/verify-phase3.php
cd backend && php scripts/verify-phase4.php
cd backend && php scripts/verify-phase5.php
cd backend && php scripts/verify-search.php
cd backend && php scripts/verify-audit.php
cd backend && php scripts/verify-rbac.php
cd frontend && npm run type-check && npm run build-only
cd frontend && npm run test:e2e   # after E2eSmokeSeeder + dev servers (Slice 13)
```

---

## Related docs

- [MVP scope](./mvp-scope.md) — first production checklist
- [Implementation roadmap](./implementation-roadmap.md) — phase sequence
- [UI reference summary](../03-design-system/ui-reference-summary.md) — CaseIQ patterns
- [Phase 2 guide](../phases/phase-2-core-legal-operations.md) — tasks Kanban originally deferred drag
