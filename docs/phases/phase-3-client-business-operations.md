# Phase 3: Client and Business Operations

**Goal:** Client-facing portal, firm communications, billing, time tracking, and operational reports.

**Depends on:** [Phase 2](./phase-2-core-legal-operations.md)

**Phase 3 complete:** yes (signed off 2026-06-05)

**Unlocks:** MVP launch (with Phase 4 lite AI optional)

---

## Modules in this phase

| Module | Spec |
|--------|------|
| Client portal | [17-client-portal](../modules/17-client-portal.md) |
| Communication center | [16-communication-center](../modules/16-communication-center.md) |
| CRM (full) | [15-crm](../modules/15-crm.md) |
| Appointments | [18-appointments](../modules/18-appointments.md) |
| Billing & payments | [25-billing-payments](../modules/25-billing-payments.md) |
| Time tracking | [26-time-tracking](../modules/26-time-tracking.md) |
| Reporting | [32-reporting](../modules/32-reporting.md) |

---

## Deliverables

### Client portal

- [x] Client auth (separate guard or role-scoped login)
- [x] Case status, shared documents (read/download)
- [x] Upload documents
- [x] Messages (in-app, poll-based)
- [x] Intake forms (client-facing)
- [x] Invoices (read-only list + detail)
- [x] Make payments (Stripe/PayPal portal checkout)
- [x] Visibility controls per document (`client_visible`)
- [ ] E-sign placeholder or link to Phase 4

### Communication

- [x] In-app messaging linked to client + case
- [x] Message threads on case profile
- [x] Email notifications for new messages
- [x] Communication log on client profile
- [ ] Optional: SMS/WhatsApp provider integration ([integrations](../modules/42-integrations.md))

### Appointments

- [x] Lawyer availability settings
- [x] Booking flow (consultation types, paid/free)
- [x] Calendar integration + reminders

### Billing & time

- [x] Time entries: manual + timer; billable flag; case/task link
- [x] Expenses on case
- [x] Invoices from time + expenses; PDF generation
- [x] Stripe + PayPal payment links / portal checkout
- [x] Receipts; payment status on invoice and client balance (manual payment record)
- [x] Billing types: hourly, fixed, retainer (minimum)

### Reporting

- [x] Basic dashboards: cases by status, revenue, unpaid invoices, time by lawyer
- [x] Export CSV for key reports

### Realtime (recommended)

- [ ] Laravel Reverb/Pusher: client-lawyer chat, notification toasts

---

## Acceptance criteria

1. [Client portal workflow](../01-planning/key-workflows.md) steps 1–6 work (sign/pay may need Phase 4 e-sign).
2. [Billing workflow](../01-planning/key-workflows.md) end-to-end: time → invoice → portal payment → receipt.
3. Clients only see permitted case data; internal notes hidden.
4. Lawyers track time on cases; billable hours attach to invoices.
5. Firm Admin sees revenue and outstanding invoice reports.
6. Portal and payment flows use HTTPS; PCI via gateway (no card storage on app).

---

## MVP items covered

MVP modules 11–14, 16 (basic reporting), plus portal/comms/billing/time.

---

## Out of scope

- Advanced approval on invoices
- Contingency/subscription billing complexity
- Full CRM feedback surveys (basic satisfaction note on log entry — done)
- AI features (Phase 4)

---

## Suggested implementation order

1. Time tracking — **done** (see Progress below)
2. Invoices + PDF + manual payment record — **done** (see Progress below)
3. Client portal foundation (read) — **done** (see Progress below)
4. Stripe/PayPal integration + portal pay — **done** (see Progress below)
5. In-app messaging + realtime — **messaging done** (poll-based; Reverb deferred)
6. Client portal upload — **done** (see Progress below)
7. Appointments — **done** (see Progress below)
8. CRM communication history + client feedback (basic) — **done** (see Progress below)
9. Reporting dashboards — **done** (see Progress below)

---

## Progress

### Time tracking — completed

- **Backend**: `time_entries` table (org/user/case/task, started/ended, `duration_minutes`,
  `billable`, `rate`, `status` draft/submitted/approved, running-timer flag), `TimeEntry`
  model with org scoping, `TimeEntryResource`, `TimeEntryPolicy` (own vs all), and
  `TimeEntryController` with CRUD + `timer/start`, `{id}/stop`, `running`, `{id}/approve`
  and a totals summary on the list endpoint.
- **API**: routes under `/api/v1/time-entries`.
- **RBAC**: `time-entries.{view, view-all, create, update, update-all, delete, delete-all,
  approve}` seeded across roles in `RolesAndPermissionsSeeder`.
- **Frontend**: global Time tracking view (nav + `/time-tracking`) and a Time tab on the case
  workspace (`CaseTimePanel`) with live timer, manual entry, billable summary, approve/delete.
- **Verification**: `backend/scripts/verify-phase3.php` — 20/20 checks pass; Phase 2 suite
  still 48/48 (no regressions).

### Invoicing — completed

- **Backend**: `invoices` and `invoice_line_items` tables; `invoice_id` on `time_entries`
  to prevent double-billing; `Invoice` and `InvoiceLineItem` models with org scoping,
  `InvoiceResource`, `InvoicePolicy`, and `InvoiceController` with CRUD,
  `generate-from-time-entries`, `mark-sent`, `record-payment`, and `export-pdf`
  (DomPDF with HTML fallback). Invoice totals, tax, discount, balance, and status
  (`draft` / `sent` / `partial` / `paid` / `overdue` / `cancelled`) are computed
  server-side.
- **API**: routes under `/api/v1/invoices` plus action endpoints.
- **RBAC**: `invoices.{view, create, update, delete, send, record-payment}` seeded
  across roles in `RolesAndPermissionsSeeder`.
- **Frontend**: global Invoices list (`/invoices`), create/detail views, generate-from-time
  flow, case Invoices tab (`CaseInvoicesPanel`), and client invoice history
  (`ClientInvoicesPanel`). Sidebar nav entry when `invoices.view` is granted.
- **Verification**: `backend/scripts/verify-phase3.php` — time tracking + invoicing
  checks pass; Phase 2 suite still green.

### Client portal foundation — completed

- **Backend**: `client_id` on `users` links portal accounts to `Client` records;
  `client_visible` on `legal_documents` controls portal sharing. Portal auth at
  `/api/v1/portal/auth/*` (Client role + linked client only; staff login rejects
  portal users). Scoped read APIs: dashboard, cases, shared documents (download),
  and non-draft invoices via `Portal*Controller` + `EnsurePortalClient` middleware.
- **RBAC**: Client role permissions tightened to `portal.*` endpoints only
  (`portal.dashboard.view`, `portal.cases.view`, `portal.documents.view`,
  `portal.documents.download`, `portal.invoices.view`).
- **Frontend**: `/portal/*` routes with separate `PortalLayout`, portal login,
  dashboard (cases, recent invoices, messages placeholder), case detail,
  invoices list/detail. Staff document panel includes **Client visible** toggle.
- **Verification**: `backend/scripts/verify-phase3.php` — portal login, scoped
  access, document visibility, and cross-client isolation checks added.

### Stripe/PayPal portal checkout — completed

- **Backend**: `payments` table (`invoice_id`, `gateway` stripe|paypal|manual,
  `external_id`, `amount`, `status`, `metadata` JSON). `Payment` model;
  `InvoicePaymentService` centralizes invoice balance updates and idempotency;
  `StripeCheckoutService` and `PayPalCheckoutService` create checkout sessions
  (Stripe REST / PayPal v2). Portal endpoints:
  `GET /portal/invoices/payment/gateways`,
  `POST /portal/invoices/{id}/checkout/stripe|paypal`,
  `POST /portal/invoices/payment/paypal/capture`. Webhooks at
  `POST /api/v1/webhooks/stripe` and `/webhooks/paypal` mark invoices paid/partial.
  Staff `record-payment` creates `manual` gateway payments via the same service.
- **Config**: `.env.example` — `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`,
  `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MODE`, `PAYPAL_WEBHOOK_ID`.
  Graceful 503 when keys absent.
- **RBAC**: `portal.invoices.pay` on Client role.
- **Frontend**: `PortalInvoiceDetailView` — Pay with Stripe / PayPal buttons;
  success/cancel return routes; PayPal capture on return. Flat UI with clear
  message when gateways disabled.
- **Verification**: `verify-phase3.php` extended — manual payment rows, gateway
  config, checkout structure (503 without keys / session URL with keys), webhook
  signature check, idempotency.

### Client portal document upload — completed

- **Backend**: `uploaded_by_client` and `portal_reviewed_at` on `legal_documents`.
  Portal `POST /portal/documents` (file upload to client's matters only);
  `GET /portal/documents?scope=shared|pending` lists shared vs pending review.
  Staff `PATCH /documents/{id}` with `client_visible` marks portal uploads reviewed;
  `portal_document_uploaded` in-app notification to staff with `documents.view`.
- **RBAC**: `portal.documents.upload` on Client role.
- **Frontend**: portal case detail upload form + pending/shared sections;
  staff case documents panel inbox with Approve & share / Keep internal.
- **Verification**: `verify-phase3.php` extended — upload, pending/shared lists,
  staff notification, approve workflow, cross-case isolation.

### Appointments — completed

- **Backend**: `lawyer_availability_slots` (weekly windows per lawyer) and `appointments`
  (client/lawyer/matter, consultation type, fee/payment status, status lifecycle).
  `appointment` calendar event type; `calendar_events.legal_matter_id` nullable for
  bookings without a matter. `AppointmentBookingService` computes open slots, creates
  linked `CalendarEvent` with `reminder_at` (reuses `calendar:send-reminders`),
  and fires in-app notifications. Staff `AppointmentController` + `LawyerAvailabilityController`;
  portal `PortalAppointmentController` (lawyers list, slots, book, cancel).
- **API**: `/api/v1/appointments`, `/appointments/available-slots`, `/lawyer-availability`;
  portal `/portal/lawyers`, `/portal/appointments/*`.
- **RBAC**: `appointments.{view, create, update, delete, manage-availability}` for staff;
  `portal.appointments.{view, book}` on Client role.
- **Frontend**: staff `/calendar` (appointments list, availability editor, lawyer filter);
  portal `/portal/appointments` booking UI. Flat UI, no shadows.
- **Verification**: `verify-phase3.php` extended — availability, staff CRUD, calendar
  link + reminder, portal book/cancel, cross-client isolation, notifications.

### Reporting — completed

- **Backend**: `ReportController` with org-scoped aggregates — cases by status, revenue
  (total billed, collected, paid invoice count), unpaid invoice totals, billable time
  grouped by lawyer. Optional `from_date` / `to_date` filters on cases (`created_at`),
  invoices (`issue_date`), and time entries (`started_at`).
- **API**: `GET /api/v1/reports/summary`, `GET /api/v1/reports/export.csv` (datasets:
  `all`, `cases`, `invoices`, `time_by_lawyer`).
- **RBAC**: `reports.view` on Firm Admin and Partner roles (System Admin has all).
- **Frontend**: `/reports` with summary cards, cases-by-status bars, lawyer time table,
  date filters, and CSV export. Flat `bw-card` UI, no shadows.
- **Verification**: `verify-phase3.php` extended — summary shape, date filters, CSV export,
  unauthenticated denial.

### CRM communication history — completed

- **Backend**: `communication_logs` table (org/client/case, channel
  `in_app|email|phone|meeting|note`, subject, body, `logged_by_user_id`,
  `occurred_at`, `client_feedback`, `satisfaction_score`, optional
  `message_thread_id`). `CommunicationLog` model; `CommunicationLogResource`;
  `CommunicationLogPolicy`; `CommunicationLogController` CRUD.
  `CommunicationLogService` auto-logs in-app messages from
  `MessageThreadController` on thread create and reply.
- **API**: routes under `/api/v1/communication-logs`.
- **RBAC**: `communication-logs.{view, create, update, delete}` seeded across
  staff roles in `RolesAndPermissionsSeeder`.
- **Frontend**: `ClientCommunicationPanel` on client profile — timeline,
  manual log form, client feedback and satisfaction score (1–5). Flat UI.
- **Verification**: `verify-phase3.php` extended — CRUD, feedback update,
  auto-log from messaging, client-scoped list; appointment slot times use
  per-run dynamic offsets to avoid stale DB collisions.

### Case expenses — completed

- **Backend**: `case_expenses` table (org/matter/user, description, amount,
  `expense_date`, `billable`, `status`, `invoice_id`). `CaseExpense` model;
  `CaseExpenseResource`; `CaseExpensePolicy`; `CaseExpenseController` CRUD with
  list summary. `invoice_line_items.case_expense_id`; `POST /invoices/generate-from-expenses`.
- **API**: routes under `/api/v1/case-expenses`.
- **RBAC**: `expenses.{view, view-all, create, update, update-all, delete, delete-all}`
  seeded across staff roles.
- **Frontend**: case Expenses tab (`CaseExpensesPanel`) — add/list/delete, billable
  summary. Flat `bw-card` UI.
- **Verification**: `verify-phase3.php` — CRUD, generate-from-expenses, invoice totals.

### Billing types — completed

- **Backend**: `billing_type` (`hourly|fixed|retainer`), `billing_rate`,
  `fixed_fee_amount`, `retainer_minimum_amount` on `legal_matters`. Exposed via
  `LegalMatterController` / `LegalMatterResource`.
- **Frontend**: billing section on case create/edit form.
- **Verification**: `verify-phase3.php` — PATCH case with retainer + minimum amount.

### Portal intake forms — completed

- **Backend**: `PortalIntakeController` — list/show published forms, client submit
  (`POST /portal/intake-forms/{id}/submit`). `client_id` on `intake_submissions`
  links portal submissions. Staff notification + auto-tasks on submit.
- **API**: `/api/v1/portal/intake-forms`, `/submit`.
- **RBAC**: `portal.intake.{view, submit}` on Client role.
- **Frontend**: `/portal/intake` list + detail/submit (`PortalIntakeView`); nav link
  in `PortalLayout`.
- **Verification**: `verify-phase3.php` — list, show, submit, client link, draft blocked.

### Email notifications (messages) — completed

- **Backend**: `NewMessageNotification` (queued mail). `MessageNotificationService`
  sends email to staff on portal messages and to portal users on staff messages.
  Portal message paths also auto-log to `communication_logs`.
- **Verification**: `verify-phase3.php` — `Notification::fake()` asserts mail queued
  to portal user on staff reply.

### Deferred (out of MVP scope)

- Laravel Reverb/Pusher realtime (poll-based messaging remains)
- E-sign (Phase 4)
- SMS/WhatsApp provider integration

### In-app messaging — completed

- **Backend**: `message_threads` (org, client, optional case, subject, `last_message_at`)
  and `messages` (thread, sender, body, `read_at`, attachments JSON). `MessageThread`
  and `Message` models; `MessageThreadResource` / `MessageResource`; `MessageThreadPolicy`;
  staff `MessageThreadController` (list/create/show, send, mark-read) and portal
  `PortalMessageController` with client scoping. `MessageNotificationService` fires
  `InAppNotifier` events (`message_received` / `portal_message_received`) with
  `NotificationDeepLink` action URLs.
- **API**: `/api/v1/message-threads` (staff); `/api/v1/portal/message-threads` (portal).
- **RBAC**: `messages.{view, create}` for staff roles; `portal.messages.{view, create}`
  on Client role.
- **Frontend**: staff `/messages` view + case Messages tab (`CaseMessagesPanel`);
  portal dashboard recent threads, `/portal/messages`, case detail messages panel.
  Poll-based refresh (15s); flat UI, no shadows.
- **Verification**: `verify-phase3.php` extended — thread CRUD, send/reply, mark-read,
  cross-client isolation, notifications, dashboard messages.
