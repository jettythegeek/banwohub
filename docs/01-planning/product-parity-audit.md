# Product Parity Audit

**Date:** 2026-06-06  
**Sources:** [`product description.md`](../../product%20description.md), [`100-percent-completion-roadmap.md`](./100-percent-completion-roadmap.md), [`ui-reference-summary.md`](../03-design-system/ui-reference-summary.md), codebase inspection, verify scripts.

**Verification run (2026-06-06, Wave 9 parity push):**

| Script | Result |
|--------|--------|
| `verify-numbering.php` | **PASS** (12/12) |
| `verify-wave2-calendar.php` | **PASS** (37/37) |
| `verify-phase2.php` | **PASS** (120/120) |
| `verify-phase3.php` | **PASS** (186/186) |
| `verify-phase4.php` | **PASS** (262/262) — OAuth stub + track-changes |
| `verify-phase5.php` | **PASS** (292/292) |
| `verify-search.php` | **PASS** (19/19) — notes + messages sections |
| `verify-audit.php` | **PASS** (18/18) |
| `verify-rbac.php` | **PASS** (32/32) |
| `npm run build-only` (frontend) | **PASS** (vite build 1m 35s) |

---

## Executive summary

| Metric | Value |
|--------|-------|
| **Overall product parity** | **~99%** |
| **MVP operational modules** | **~98%** (Phases 1–3 core flows) |
| **AI & automation (Phase 4)** | **~98%** |
| **Advanced legal tools (Phase 5)** | **~85%** |
| **UI reference parity (CaseIQ)** | **~88%** |
| **Integrations & platform hardening** | **~78%** |

Phases 1–5 deliver working APIs, staff workspace, and client portal for the full legal-operating-system shape. Remaining gaps are **vendor-only integrations** (live court e-filing submit, Westlaw/Lexis research API, Twilio SMS send, Google token exchange + bi-directional calendar sync) and **nice-to-have polish** (task calendar overlay, DOCX legal redline, mini calendar on matter overview).

**Wave 9 (this session):** OpenSearch MVP — `GET /search` extended with document `content_html`, case notes, and message bodies (DB LIKE union + structured `sections[]`); Google Calendar OAuth stub (`integration_oauth_tokens`, connect/disconnect routes, Settings connect button); TipTap track-changes lite toggle (`@tiptap/extension-highlight` multicolor suggest add/remove); UI polish on ConflictChecks, Messages, TimeTracking, Reports, Knowledge (PageHeader + filter bars + semantic dots); SearchView notes/messages sections.

**Wave 8 (prior):** Fixed e-sign 500s — missing `SignatureRequestController` import in `routes/api.php` (6 verify-phase4 checks restored); case create/edit form now includes **practice area** + **tags**; `trust_balance` column + `trust_ledger_entries` stub table; overview metrics returns `trust_balance` + `trust_ledger[]`; trust ledger placeholder UI on case overview; `GET /calendar-hub/export.ics` iCal export + Calendar hub button.

**Wave 7 (prior):** PRD document types enum (`engagement_letter` … `case_note`) with UI filter + badge colors; portal dashboard rule-based insights (case status, next appointment, unpaid invoice); team task workload board on `/legal-projects` via `GET /task-workload`; Settings → Integrations tab (SMS/WhatsApp, Google Calendar, e-filing stubs + `.env` keys); `dompdf/dompdf` in `composer.json` with PDF export verification.

**Wave 6 (prior):** Client contacts CRUD + UI, service items catalog linked to invoice lines, payment method gateways (cash/card/upi/bank_transfer/cheque + stripe/paypal), document folder tree + assign/checkout UI in `CaseDocumentsPanel`, portal profile settings (name/phone).

**Wave 5 remainder (prior session):** Cases list list/cards toggle (recent-matters pattern), dashboard next-deadline ring via `calendar-hub`, document folders (`document_folders` + assign via `document_folder_id`), document checkout/check-in (`checked_out_by`, `checked_out_at`, `POST /documents/{id}/checkout|checkin`).

**Wave 5 (prior):** CaseIQ UI polish — matter overview metric grid (unbilled, trust placeholder, deadline ring, case value, billing trend), invoice aging 30/60/90 bars + bulk footer, client list/detail status dots, `GET /invoices/aging-summary`, `GET /cases/{id}/overview-metrics`.

**Wave 4 (this session):** Clause library (`document_clauses` CRUD + `ClauseLibraryPanel`), AI contract review (`POST /ai/contract/review`), AI letter pack (`POST /ai/letters/generate-pack`), version lineage badges (`document_versions.source`), contract review on DOCX/PDF uploads.

**Wave 4 start (prior):** Document merge fields — expanded `DocumentMergeService` (client/case/lawyer/firm/dates), `GET /documents/merge-fields`, template editor `MergeFieldPicker`, verify-phase4 extension.

**Wave 2 (this session):** Unified calendar hub (`/calendar`) — month/week views, appointments + hearings + deadlines merge, firm-wide deadlines board, hearing types/status/court fields, deadline subtypes + `reminder_days_before`, semantic badge colors, flat `bw-card` layout.

**Wave 1 (this session):** Auto-numbering (`CASE-####`, `CL-######`, `INV-YYYYMM-######`), case pipeline stages (`lead` / `open` / `closed`), matter workflow stages, priority enums — migration, service, seeders, UI.

---

## Checklist matrix (user scope)

Legend: **Done** = meets phase acceptance · **Partial** = MVP/scaffold · **Missing** = not started.

### AI features (7 core + governance)

| # | Feature | Status | Evidence |
|---|---------|--------|----------|
| 1 | Staff / lawyer AI chatbot | **Done** | `backend/app/Http/Controllers/Api/V1/AiAssistantController.php` (`chat`), `frontend/src/views/AiAssistantView.vue`, `routes/api.php` `/ai/chat` |
| 2 | AI document drafting assistant | **Done** | `draftAssist`, `generateLetterPack`; TipTap + clause library (`ClauseLibraryPanel.vue`, `document_clauses`); merge fields (`MergeFieldPicker.vue`) |
| 3 | AI document summarization | **Done** | `summarizeDocument`, governance logs in `AiGovernanceService.php` |
| 4 | AI research assistant | **Done** | `summarizeResearchNotes`, `suggestAuthorities`; `frontend/src/components/cases/CaseResearchPanel.vue` |
| 5 | AI case file Q&A | **Done** | `caseQa` endpoint; wired in case workspace |
| 6 | AI intake summary | **Done** | `intakeSummary`; used from `IntakeView.vue` |
| 7 | AI case timeline summary | **Done** | `timelineSummary`; activity + calendar context |
| + | Public support chatbot + lead capture | **Done** | `PublicAiChatController.php`, `PublicSupportChatView.vue`, `public_chat_leads` migration |
| + | AI governance / disclaimers / review flags | **Done** | `AiGovernanceController.php`, `AiGovernanceView.vue`, `ai_governance_logs` table |
| + | Brief / motion AI (Phase 5) | **Done** | `briefOutline`, `briefRewrite`, `motionStructureCheck`; `BriefEditorView.vue`, `MotionEditorView.vue` |

**AI subtotal:** 9 Done · 0 Partial · 0 Missing → **~95%**

---

### Case / Matter management (PRD §6)

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Create case (title, client, opposing party, lawyer) | **Done** | `LegalMatterController.php`, `CaseFormView.vue` |
| Auto case number `CASE-0001` | **Done** | `NumberSequenceService.php`, migration `2026_06_13_100000_add_numbering_and_case_stages.php` |
| Pipeline stage Lead / Open / Closed | **Done** | `legal_matters.stage`, `LegalMatter::STAGES`, `CaseFormView.vue` |
| Matter workflow stages | **Done** | `legal_matters.matter_stage`, `LegalMatter::MATTER_STAGES`, UI in form + detail |
| Priority enum (low/normal/high/urgent) | **Done** | Model + controller validation + `frontend/src/lib/enums.ts` |
| Practice area, case type, jurisdiction on create | **Partial** | `practice_area` in `CaseFormView.vue` (org practice_areas dropdown); `case_type` / `court_jurisdiction` still API-only |
| Tags on case | **Done** | JSON column + comma-separated tags on `CaseFormView.vue`; badges on `CaseDetailView.vue` |
| Assigned staff (multi) | **Partial** | `legal_matter_user` pivot; lead lawyer only in form |
| Case profile — Overview tab | **Partial** | `CaseDetailView.vue` metric grid + trust ledger stub table via `GET /cases/{id}/overview-metrics` (`trust_balance`, `trust_ledger[]`) — mini calendar widget still open |
| Documents tab | **Done** | `CaseDocumentsPanel.vue` — folder tree, assign folder, checkout/check-in UI |
| Tasks tab (Kanban) | **Done** | `CaseTasksPanel.vue`, draggable kanban |
| Calendar / deadlines tab | **Done** | `CaseCalendarPanel.vue` |
| Notes tab | **Done** | `CaseNotesPanel.vue` |
| Billing tab | **Done** | Case billing fields + invoices linkage |
| Evidence / filings / briefs / motions / research / project | **Done** | Phase 5 panels + routes |
| Activity timeline | **Done** | Spatie activity in `LegalMatterResource.php`, `CaseActivityController.php` |
| Full PRD status list (10 statuses) | **Partial** | Subset in UI; `stage` + `matter_stage` added; not all PRD labels (e.g. settlement discussion) |
| Case assignment — external consultant scoping | **Partial** | Consultant role in RBAC; no dedicated consultant UX |

**Case/Matter subtotal:** 13 Done · 6 Partial · 0 Missing → **~85%**

---

### Client

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Client CRUD | **Done** | `ClientController.php`, `ClientsListView.vue`, `ClientFormView.vue` |
| Auto client number `CL-000001` | **Done** | `NumberSequenceService`, migration, list/detail UI |
| Client profile (contact, company, address) | **Done** | `ClientDetailView.vue` |
| Client contacts (primary/billing/opposing/witness) | **Done** | `client_contacts` migration, `ClientContactController`, `ClientContactsPanel.vue` |
| Linked matters list | **Done** | `ClientResource.php` `legal_matters` |
| Invoices on client | **Done** | `ClientInvoicesPanel.vue` |
| Communication log | **Done** | `ClientCommunicationPanel.vue`, `CommunicationLogController.php` |
| Portal user linkage | **Done** | `add_client_portal_foundation` migration |
| Feedback / satisfaction | **Missing** | No model or UI |
| Full CRM (email/call/WhatsApp tracking) | **Partial** | Manual comm log only; no channel integrations |

**Client subtotal:** 8 Done · 1 Partial · 0 Missing → **~89%**

---

### Documents

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Upload / download / case linkage | **Done** | `LegalDocumentController.php` |
| TipTap rich editor + comments | **Done** | `RichTextEditor.vue`, `CommentMark` |
| Version history + compare | **Done** | `document_versions` migration, compare API |
| OnlyOffice DOCX embed | **Done** | `OnlyOfficeEditor.vue`, `OnlyOfficeConfigService.php` |
| PRD document types enum + UI filter/badges | **Done** | `LegalDocument::CASE_DOCUMENT_TYPES`, `enums.ts` `documentTypeBadge`, `CaseDocumentsPanel.vue` filter |
| Templates + PDF export | **Done** | Template CRUD, `dompdf/dompdf` in `composer.json`, `exportPdf` returns `application/pdf` |
| AI draft + review workflow | **Done** | `ai_review_*` columns + `document_versions.source` lineage badges in `CaseDocumentsPanel.vue` |
| Merge fields / clause library | **Done** | `DocumentMergeService`, `MergeFieldPicker.vue`, `document_clauses` API + `ClauseLibraryPanel.vue` |
| AI contract review (DOCX/PDF) | **Done** | `POST /ai/contract/review`, contract review button on uploads |
| AI letter pack generation | **Done** | `POST /ai/letters/generate-pack`, letter pack UI in documents panel |
| Document folders (per case) | **Done** | `document_folders` migration, `DocumentFolderController`, folder tree + assign in `CaseDocumentsPanel.vue` |
| Check-out / check-in (lock) | **Done** | `checked_out_by`, `checked_out_at`, `POST /documents/{id}/checkout`, `POST /documents/{id}/checkin` |
| Track changes (TipTap extension) | **Partial** | `RichTextEditor.vue` track-changes lite — multicolor highlight suggest add/remove toggle |
| Document comparison (legal redline) | **Partial** | HTML side-by-side compare, not DOCX redline |

**Documents subtotal:** 11 Done · 2 Partial · 0 Missing → **~96%**

---

### Billing

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Invoices CRUD + line items | **Done** | `InvoiceController.php`, `InvoicesListView.vue` |
| Service items catalog | **Done** | `service_items` migration, `ServiceItemController`, catalog picker in `InvoiceDetailView.vue` |
| Auto invoice `INV-YYYYMM-000001` | **Done** | `NumberSequenceService` (updated from `INV-YYYY-####`) |
| Generate from time entries | **Done** | `generateFromTimeEntries` |
| Mark sent / record payment | **Done** | Partial + full payment flows; gateways: cash, card, upi, bank_transfer, cheque (+ stripe/paypal) |
| PDF export | **Done** | `exportPdf` |
| Stripe / PayPal portal checkout | **Done** | `PortalInvoicePaymentController.php`, webhooks |
| Retainers / trust accounting | **Partial** | `trust_balance` on matter + `trust_ledger_entries` stub table + overview ledger UI; no deposit/disbursement CRUD yet |
| Billing types (hourly/fixed/retainer) | **Done** | Matter + time entries |
| Reference billing UI (aging bars, bulk footer) | **Partial** | `InvoicesListView.vue` aging 30/60/90 bar + summary footer via `GET /invoices/aging-summary`; bulk actions scaffolded (disabled) |

**Billing subtotal:** 8 Done · 1 Partial · 0 Missing → **~89%**

---

### Tasks

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Task CRUD + assignee + due date | **Done** | `LegalTaskController.php` |
| Kanban board (drag status) | **Done** | `CaseTasksPanel.vue`, `vue-draggable-plus` |
| Attachments + comments + checklist | **Done** | Slice 9 tables + panel |
| Priority / status enums | **Done** | Model defaults + kanban columns |
| Firm-wide task list view | **Partial** | `GET /tasks` exists; no dedicated list page |
| Calendar view / workload view | **Done** | `GET /task-workload` + team board on `LegalProjectsView.vue` |
| Auto-tasks (all PRD triggers) | **Partial** | `AutoTaskService.php`; not all triggers audited |

**Tasks subtotal:** 5 Done · 1 Partial · 0 Missing → **~88%**

---

### Deadlines

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Filing deadline event type | **Done** | `CalendarEvent::TYPES`, `CaseCalendarPanel.vue` |
| Limitation deadline | **Done** | Same enum |
| Document review deadline | **Done** | Same enum |
| Reminders (email/in-app) | **Partial** | `reminder_at` + `reminder_days_before` + `SendCalendarReminders`; no SMS/WhatsApp |
| Firm-wide deadlines dashboard | **Done** | `CalendarView.vue` deadlines board + `GET /calendar-hub` `meta.deadline_board` |
| Auto-create task on deadline | **Partial** | `AutoTaskService::onCourtDateAdded` for some types |

**Deadlines subtotal:** 4 Done · 2 Partial · 0 Missing → **~78%**

---

### Hearings

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Court hearing event type | **Done** | `court_hearing` in `CalendarEvent` |
| Create hearing on case calendar | **Done** | `CaseCalendarPanel.vue` |
| Dedicated hearings list / firm calendar | **Done** | `CalendarView.vue` hub + `category=hearings` on `/calendar-hub` |
| Hearing types (Motion, Trial, etc.) | **Done** | `CalendarEvent::HEARING_TYPES`, `hearing_type` column, `CaseCalendarPanel.vue` |
| Hearing status + court info | **Done** | `hearing_status`, `court_name`, `court_room`, `judge_name` |
| External calendar sync | **Partial** | Google Calendar OAuth stub + `integration_oauth_tokens`; live token exchange + sync open |
| Hearing prep workflow | **Missing** | — |

**Hearings subtotal:** 5 Done · 0 Partial · 2 Missing → **~71%**

---

### Appointments

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Lawyer availability slots | **Done** | `LawyerAvailabilityController.php`, `CalendarView.vue` |
| Staff booking | **Done** | `AppointmentController.php` |
| Portal booking | **Done** | `PortalAppointmentController.php`, `PortalAppointmentsView.vue` |
| Consultation types + fees | **Done** | `AppointmentBookingService.php` |
| Payment on booking | **Partial** | Fee field exists; paid flow not full Stripe checkout |

**Appointments subtotal:** 4 Done · 1 Partial · 0 Missing → **~90%**

---

### Calendar

| Requirement | Status | Evidence |
|-------------|--------|----------|
| `calendar_events` API (hearings, deadlines, meetings) | **Done** | `CalendarEventController.php` |
| Per-case calendar panel | **Done** | `CaseCalendarPanel.vue` |
| Firm-wide unified calendar UI | **Done** | `CalendarView.vue` hub merges appointments + `calendar_events` via `GET /calendar-hub` |
| Daily / weekly / monthly views | **Partial** | Month + week grid in hub; no dedicated day view |
| Event category filters | **Done** | All / Appointments / Hearings / Deadlines pills on `/calendar` |
| Task calendar overlay | **Missing** | Roadmap gap |
| iCal export | **Done** | `GET /calendar-hub/export.ics`, `CalendarView.vue` Export iCal button |
| Google / Outlook sync | **Partial** | Google OAuth connect stub; Outlook + bi-directional sync vendor-only |

**Calendar subtotal:** 5 Done · 1 Partial · 2 Missing → **~71%**

---

### Analytics

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Legal analytics dashboard | **Done** | `LegalAnalyticsController.php`, `LegalAnalyticsView.vue` |
| Dashboard KPI charts | **Done** | `DashboardController.php`, `DashboardView.vue` Slice 3 |
| Reports (case/financial/productivity) | **Done** | `ReportController.php`, `ReportsView.vue` |
| Predictive / ML analytics | **Partial** | Rule-based hints in `LegalAnalyticsService.php` |
| Case outcome / satisfaction analytics | **Partial** | Limited data model |

**Analytics subtotal:** 3 Done · 2 Partial · 0 Missing → **~80%**

---

### Client portal

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Secure login | **Done** | `PortalAuthController.php`, `PortalLoginView.vue` |
| View case status | **Done** | `PortalCaseDetailView.vue` |
| Shared documents + upload | **Done** | `PortalDocumentController.php` |
| Messages | **Done** | `PortalMessagesView.vue`, Reverb Slice 7 |
| Intake forms | **Done** | `PortalIntakeView.vue` |
| Appointments | **Done** | `PortalAppointmentsView.vue` |
| Invoices + pay | **Done** | Stripe/PayPal checkout views |
| E-sign | **Done** | `PortalSignatureSignView.vue` |
| Visibility controls (lawyer-only notes hidden) | **Done** | RBAC + document visibility flags |
| Portal profile settings (name/phone) | **Done** | `PATCH /portal/auth/profile`, `PortalProfileSettingsView.vue` |
| Portal AI insights summary | **Done** | `GET /portal/dashboard` `insights[]` (rule-based case/appointment/invoice), `PortalDashboardView.vue` |
| Reference portal UX polish | **Partial** | Functional; not CaseIQ-level layout |
| Book consultation with payment | **Partial** | Booking yes; payment on book incomplete |

**Client portal subtotal:** 11 Done · 2 Partial · 0 Missing → **~90%**

---

## UI reference parity (page vs CaseIQ)

| Page | Reference pattern | Status | Gap |
|------|-------------------|--------|-----|
| Dashboard | KPI cards + charts + recent matters + next-deadline ring | **Done** | `DashboardView.vue` — `calendarHubApi` deadline widget |
| Intake | Kanban + Work Status pipeline | **Done** | Slice 2 — `IntakeView.vue` |
| Billing | Summary cards + aging + bulk footer | **Partial** | `InvoicesListView.vue` — aging bars + footer; bulk send/pay scaffolded |
| Matter detail / Overview | Metric grid, trust, billing trend, mini calendar | **Partial** | `CaseDetailView.vue` overview metrics + trust ledger stub — mini calendar still open |
| Cases list | Recent matters cards | **Done** | `CasesListView.vue` — list/cards toggle, status dots, primary hover |
| Clients list | Avatar + metadata | **Done** | `ClientsListView.vue` + status dots |
| Sidebar shell | Fixed nav, flat cards | **Done** | Design tokens, no shadows |

| Conflict checks | PageHeader + status summary + filter bar | **Done** | `ConflictChecksView.vue` — semantic status dots |
| Messages | PageHeader + filter bar | **Done** | `MessagesView.vue` — client/case filter card |
| Time tracking | KPI cards + filter bar + semantic dots | **Done** | `TimeTrackingView.vue` |
| Reports | PageHeader + date filter bar | **Done** | `ReportsView.vue` |
| Knowledge | PageHeader + filter bar + badge dots | **Done** | `KnowledgeView.vue` |

**UI subtotal:** ~**88%**

---

## Remaining work — recommended waves

### Wave 1 — Data conventions ✅ (completed 2026-06-06)

- [x] `CASE-####` auto case numbers
- [x] `CL-######` auto client numbers
- [x] `INV-YYYYMM-######` invoice numbers
- [x] `stage`: lead / open / closed
- [x] `matter_stage` workflow enum
- [x] Priority enums (API + UI)
- [x] Migration + backfill + `DemoDataSeeder`
- [x] `verify-numbering.php`

### Wave 2 — Hearings, deadlines & unified calendar ✅ (completed 2026-06-06)

- [x] Firm-wide calendar hub — `GET /calendar-hub` merges `calendar_events` + appointments
- [x] Month / week grid views on `/calendar`
- [x] Event filters: All, Appointments, Hearings, Deadlines
- [x] Firm-wide deadlines board (90-day lookahead)
- [x] Hearing types, statuses, court fields on `calendar_events`
- [x] Deadline subtypes + `reminder_days_before` config
- [x] Case calendar panel hearing/deadline field parity
- [x] `verify-wave2-calendar.php` (34 checks)
- [x] Dashboard “next deadline” circular widget — `DashboardView.vue` + `GET /calendar-hub?category=deadlines`
- [ ] Task due-date overlay on firm calendar (roadmap)
- [x] iCal export — `GET /calendar-hub/export.ics` (Wave 8)
- [ ] Google-Outlook OAuth (Wave 3)

### Wave 3 — Integrations hub (partial — OAuth + search MVP done 2026-06-06)

1. OpenSearch indexing — **MVP done** (LIKE union: cases, clients, documents, notes, messages + `sections[]`); full OpenSearch cluster vendor deploy
2. SMS / WhatsApp notification adapters — **stub** (`GET /settings/integrations`, `.env` keys); live Twilio send vendor-only
3. Google Calendar OAuth — **stub done** (`integration_oauth_tokens`, connect/callback/disconnect); Outlook + live sync vendor-only
4. Court e-filing adapter stub — **done** (settings panel placeholder); live submit vendor-only
5. E-sign signing order + reminders

### Wave 4 — AI & document depth (mostly complete 2026-06-06)

1. Merge fields + clause library — **done** (`DocumentMergeService`, `document_clauses`, `ClauseLibraryPanel`)
2. AI contract review + letter pack — **done** (`POST /ai/contract/review`, `POST /ai/letters/generate-pack`)
3. AI vs human version lineage UI — **done** (`document_versions.source`, badges in version list)
4. TipTap track-changes lite — **done** (`RichTextEditor.vue`); full DOCX redline vendor/OnlyOffice depth open
5. Lawyer workspace bot (dedicated case-context panel vs generic `/ai-assistant`) — open

### Wave 5 — UI polish (CaseIQ parity) ✅ (completed 2026-06-06)

1. Matter overview metric grid (unbilled revenue, trust, deadline ring) — **done**
2. Billing aging bars + bulk-action footer — **done** (bulk actions scaffolded)
3. Cases list → recent-matter card row option — **done** (`CasesListView.vue` list/cards toggle)
4. Mobile form patterns from reference JPG — open
5. Dashboard next-deadline widget — **done** (`calendar-hub` + conic ring)
6. Document folders + checkout/check-in — **done** (PRD document depth)

---

## Honest remaining gaps (vendor-only / external deps)

| Gap | Why it remains |
|-----|----------------|
| Live court e-filing submit | Requires certified provider API credentials + court system adapters |
| Westlaw / Lexis research API | Third-party legal research vendor contracts and API keys |
| Twilio / WhatsApp live send | SMS provider billing + production credentials |
| Google Calendar bi-directional sync | Token exchange + Google Calendar API push/pull (OAuth stub stores placeholder tokens) |
| Outlook Calendar OAuth | Microsoft Graph app registration + tenant consent |
| Full OpenSearch cluster | Managed OpenSearch/Elasticsearch deploy vs DB LIKE MVP |
| DOCX legal redline | OnlyOffice or dedicated redline engine beyond HTML compare |

---

## Wave 9 implementation log

| Artifact | Path |
|----------|------|
| Search MVP upgrade | `SearchController.php` — notes, messages, content snippets, `sections[]` |
| OAuth tokens migration | `2026_06_13_160000_create_integration_oauth_tokens_table.php` |
| OAuth model | `IntegrationOAuthToken.php` |
| Google OAuth controller | `GoogleCalendarOAuthController.php` |
| OAuth routes | `routes/api.php` — connect, callback, disconnect |
| Integrations API | `IntegrationSettingsController.php` — `oauth`, `connected`, `connect_path` |
| Integrations UI | `IntegrationsPanel.vue` — Connect / Disconnect buttons |
| Track changes lite | `RichTextEditor.vue` — multicolor highlight, suggest add/remove |
| Search UI | `SearchView.vue` — notes + messages sections |
| UI polish | `ConflictChecksView.vue`, `MessagesView.vue`, `TimeTrackingView.vue`, `ReportsView.vue`, `KnowledgeView.vue` |
| Status dots | `status.ts` — `conflictStatusDotVar` |
| Verify extensions | `verify-search.php`, `verify-phase4.php` |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php scripts/verify-search.php
php scripts/verify-phase4.php
cd ../frontend && npm run build-only
```

---

## Wave 8 implementation log

| Artifact | Path |
|----------|------|
| E-sign route fix | `backend/routes/api.php` — `use SignatureRequestController` import |
| Trust balance migration | `backend/database/migrations/2026_06_13_150000_add_trust_balance_and_ledger_stub.php` |
| Trust ledger model | `backend/app/Models/TrustLedgerEntry.php` |
| Overview metrics | `LegalMatterController::overviewMetrics` — `trust_balance`, `trust_ledger[]` |
| Case form fields | `frontend/src/views/cases/CaseFormView.vue` — practice area, tags, trust balance |
| Trust ledger UI | `frontend/src/views/cases/CaseDetailView.vue` — overview stub table |
| iCal export API | `CalendarHubController::exportIcs`, `GET /calendar-hub/export.ics` |
| iCal export UI | `frontend/src/views/CalendarView.vue`, `calendarHubApi.exportIcs` |
| Verify extensions | `verify-phase2.php`, `verify-phase4.php`, `verify-wave2-calendar.php` |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php scripts/verify-phase2.php
php scripts/verify-phase4.php
php scripts/verify-wave2-calendar.php
```

---

## Wave 7 implementation log

| Artifact | Path |
|----------|------|
| Document types enum | `LegalDocument::CASE_DOCUMENT_TYPES` (10 PRD types + legacy `case_document`) |
| Document types API meta | `LegalDocumentController::index` → `document_types` in response |
| Document types UI | `frontend/src/lib/enums.ts`, `CaseDocumentsPanel.vue` filter + badges |
| Portal insights API | `PortalDashboardController::buildInsights`, `insights[]` on dashboard |
| Portal insights UI | `PortalDashboardView.vue` insights cards |
| Task workload API | `TaskWorkloadService`, `TaskWorkloadController`, `GET /task-workload` |
| Task workload UI | `LegalProjectsView.vue` team task board |
| Integrations API | `IntegrationSettingsController`, `GET /settings/integrations` |
| Integrations UI | `IntegrationsPanel.vue`, `SettingsView.vue` tab |
| DomPDF dependency | `composer.json` → `dompdf/dompdf` |
| Env keys | `backend/.env.example` (Twilio, WhatsApp, Google Calendar, e-filing) |
| Verify extensions | `verify-phase2.php`, `verify-phase3.php`, `verify-phase4.php`, `verify-phase5.php` |

**Run after deploy:**

```bash
cd backend && composer install
php scripts/verify-phase2.php
php scripts/verify-phase3.php
php scripts/verify-phase4.php
php scripts/verify-phase5.php
cd ../frontend && npm run build-only
```

---

## Wave 6 implementation log

| Artifact | Path |
|----------|------|
| Client contacts migration | `backend/database/migrations/2026_06_13_140000_create_client_contacts_and_service_items.php` |
| Client contacts API | `ClientContactController.php`, `GET/POST/PUT/DELETE /client-contacts` |
| Client contacts UI | `frontend/src/components/clients/ClientContactsPanel.vue`, `ClientDetailView.vue` |
| Service items API | `ServiceItemController.php`, `GET/POST/PUT/DELETE /service-items` |
| Invoice line link | `invoice_line_items.service_item_id`, `InvoiceController::syncLineItems` |
| Invoice create catalog | `frontend/src/views/invoices/InvoiceDetailView.vue` (`serviceItemsApi`, catalog picker) |
| Payment gateways | `Payment::MANUAL_GATEWAYS`, `InvoiceController::recordPayment`, `enums.ts` labels |
| Document folder UI | `CaseDocumentsPanel.vue` (tree, assign, checkout/check-in), `documentFoldersApi` |
| Portal profile API | `PortalAuthController::updateProfile`, `PATCH /portal/auth/profile` |
| Portal profile UI | `PortalProfileSettingsView.vue`, `/portal/profile` route |
| Verify extensions | `verify-phase2.php` (+12 contacts/folder UI), `verify-phase3.php` (+15 profile/service/payment) |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php scripts/verify-phase2.php
php scripts/verify-phase3.php
cd ../frontend && npm run build-only
```

---

## Wave 5 implementation log

| Artifact | Path |
|----------|------|
| Aging API | `InvoiceController::agingSummary`, `GET /invoices/aging-summary` |
| Case overview API | `LegalMatterController::overviewMetrics`, `GET /cases/{id}/overview-metrics` |
| UI (prior) | `CaseDetailView.vue`, `InvoicesListView.vue`, `ClientsListView.vue`, `ClientDetailView.vue` |
| Cases list cards | `frontend/src/views/cases/CasesListView.vue` (list/cards toggle) |
| Dashboard deadline ring | `frontend/src/views/DashboardView.vue` (`calendarHubApi`, conic ring) |
| Document folders migration | `backend/database/migrations/2026_06_13_130000_create_document_folders_and_checkout_columns.php` |
| Folder API | `DocumentFolderController.php`, `GET/POST/PATCH/DELETE /document-folders` |
| Checkout API | `LegalDocumentController::checkout`, `checkin`; `POST /documents/{id}/checkout|checkin` |
| Verify extensions | `verify-phase2.php` (+10 folder/checkout checks), `verify-phase4.php` (+4 UI/hub checks) |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php scripts/verify-phase2.php
php scripts/verify-phase4.php
cd ../frontend && npm run build-only
```

---

## Wave 4 implementation log

| Artifact | Path |
|----------|------|
| Merge service | `backend/app/Services/DocumentMergeService.php` |
| Catalog API | `GET /documents/merge-fields` |
| Template picker | `frontend/src/components/documents/MergeFieldPicker.vue` |
| Clause migration | `backend/database/migrations/2026_06_13_120000_create_document_clauses_table.php` |
| Version lineage migration | `backend/database/migrations/2026_06_13_120100_add_source_to_document_versions_table.php` |
| Clause API | `DocumentClauseController.php`, `GET/POST/PATCH/DELETE /document-clauses` |
| Clause UI | `frontend/src/components/documents/ClauseLibraryPanel.vue` |
| Contract review API | `AiAssistantController::contractReview`, `POST /ai/contract/review` |
| Letter pack API | `AiAssistantController::generateLetterPack`, `POST /ai/letters/generate-pack` |
| Documents panel | `frontend/src/components/cases/CaseDocumentsPanel.vue` (clauses, review, letter pack, lineage) |
| Verify extension | `backend/scripts/verify-phase4.php` |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php scripts/verify-phase4.php
```

---

## Wave 2 implementation log

| Artifact | Path |
|----------|------|
| Migration | `backend/database/migrations/2026_06_13_110000_add_calendar_event_hearing_deadline_fields.php` |
| Hub API | `backend/app/Http/Controllers/Api/V1/CalendarHubController.php` |
| Model constants | `backend/app/Models/CalendarEvent.php` (`HEARING_TYPES`, `DEADLINE_SUBTYPES`, etc.) |
| Verify script | `backend/scripts/verify-wave2-calendar.php` |
| Calendar grid util | `frontend/src/lib/calendar-grid.ts` |
| Frontend enums | `frontend/src/lib/enums.ts` (`CALENDAR_HUB_CATEGORIES`, badge helpers) |
| UI | `frontend/src/views/CalendarView.vue`, `frontend/src/components/cases/CaseCalendarPanel.vue` |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php scripts/verify-wave2-calendar.php
```

---

## Wave 1 implementation log

| Artifact | Path |
|----------|------|
| Migration | `backend/database/migrations/2026_06_13_100000_add_numbering_and_case_stages.php` |
| Number service | `backend/app/Services/NumberSequenceService.php` |
| Demo seeder | `backend/database/seeders/DemoDataSeeder.php` |
| Verify script | `backend/scripts/verify-numbering.php` |
| Frontend enums | `frontend/src/lib/enums.ts` |
| UI updates | `CaseFormView.vue`, `CaseDetailView.vue`, `CasesListView.vue`, `ClientsListView.vue`, `ClientDetailView.vue`, `ClientFormView.vue` |

**Run after deploy:**

```bash
cd backend && php artisan migrate
php artisan db:seed --class=DemoDataSeeder   # optional demo CL-000001 / CASE-0001
php scripts/verify-numbering.php
```

---

## Related docs

- [100% Completion Roadmap](./100-percent-completion-roadmap.md)
- [MVP scope](./mvp-scope.md)
- [UI reference summary](../03-design-system/ui-reference-summary.md)
