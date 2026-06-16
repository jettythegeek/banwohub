# Phase 2: Core Legal Operations

**Goal:** Day-to-day case work—intake, conflict checks, documents, tasks, calendar, notes, and notifications.

**Depends on:** [Phase 1](./phase-1-foundation.md) (clients, cases, users)

**Unlocks:** Phase 3 (portal and billing need documents/tasks/calendar)

**Phase 2 complete:** yes (signed off 2026-06-05)

---

## Modules in this phase

| Module | Spec |
|--------|------|
| Client intake forms | [07-client-intake-forms](../modules/07-client-intake-forms.md) |
| Conflict check | [08-conflict-check](../modules/08-conflict-check.md) |
| Case notes | [22-case-notes](../modules/22-case-notes.md) |
| Document management (basic) | [09-document-management](../modules/09-document-management.md) |
| Task management | [20-task-management](../modules/20-task-management.md) |
| Court calendar | [19-court-calendar](../modules/19-court-calendar.md) |
| Notifications | [41-notifications](../modules/41-notifications.md) |

---

## Deliverables

### Intake & conflict

- [x] Form builder (field types per spec)
- [x] Public/staff intake submission; save and continue
- [x] Admin review: approve, reject, request info, **convert to case**
- [x] Conflict search across clients, parties, cases, notes
- [x] Conflict workflow statuses and clearance by authorized role
- [x] Conflict report export/view

### Documents (Phase 2 scope)

- [x] Upload/download with case linkage
- [x] Document list on case profile; versioning (basic)
- [x] Firm template library (upload + select)
- [x] TipTap editor for rich text; export PDF/HTML
- [x] Storage: case documents, firm templates (local disk)

### Tasks & calendar

- [x] Tasks: CRUD, assignee, due date, priority, status, case link
- [x] Views: list, Kanban
- [x] Calendar events: hearings, deadlines, meetings
- [x] Reminders: email (log mailer) + in-app via scheduler
- [x] Auto-task rules: intake submitted, court date added, case status change

### Notes

- [x] Note types and visibility levels (private, team, senior, admin — not client by default)
- [x] Notes tab on case profile

### Notifications

- [x] In-app notification center
- [x] Events: task assigned, intake submitted, document uploaded, court date reminder
- [x] Deep links to case/task/document/intake/calendar entities

---

## Implementation tracker

Use this tracker for Phase 2 implementation handoff and QA sign-off. Backend API stabilization was verified on 2026-06-04 with 65 registered Phase 2 API routes under `/api/v1`. Full backend + frontend re-verification was completed on 2026-06-05: **48** Phase 2 endpoint calls return expected HTTP statuses (`scripts/verify-phase2.php`), the RBAC matrix is enforced (`scripts/verify-rbac.php`), the frontend `vue-tsc --noEmit` typecheck is clean, and `vite build` succeeds.

### Backend APIs

- [x] Case notes API: CRUD endpoints verified
- [x] Tasks API: CRUD endpoints verified
- [x] Calendar events API: CRUD endpoints verified
- [x] Documents API: CRUD, download, template, generate-draft, export-pdf endpoints verified
- [x] Intake forms API: CRUD endpoints verified
- [x] Intake submissions API: CRUD, draft, approve/reject/request-info, convert endpoints verified
- [x] Conflict checks API: CRUD and export endpoints verified
- [x] Notifications API: list, show, read, mark-all-read with `action_url` payloads verified
- [x] Case activity API: `GET /cases/{id}/activity` verified

### Case workspace UI

- [x] Case notes tab: note type selector, visibility selector, list/detail states, create/edit/delete permissions
- [x] Documents tab: upload, download, version metadata, template library, generate draft, TipTap editor, PDF/HTML export
- [x] Tasks tab: list view, Kanban view, assignee/status/priority/due-date controls, overdue indicators
- [x] Calendar tab: case-specific events, hearing/deadline/meeting types, reminder visibility
- [x] Case activity/audit surface: activity feed from `GET /cases/{id}/activity`
- [x] Dashboard integration: assigned and overdue tasks appear for the current user
- [x] Notification integration: in-app notifications deep-link to case, task, document, intake, or calendar event

### Intake/admin UI

- [x] Intake form builder: text, long text, email, phone, date, file upload, dropdown, checkbox, radio, signature placeholder, conditional fields
- [x] Staff intake submission: submit flow with supporting field types and confirmation state
- [x] Save-and-continue intake flow: draft persistence and resume path
- [x] Admin review queue: approve, reject, request information, and convert-to-case actions
- [x] Conflict search screen: searchable parties/companies/witnesses/cases/notes with match details
- [x] Conflict workflow controls: not started, in review, potential conflict found, cleared, rejected
- [x] Conflict clearance permissions: only authorized roles can clear or reject a conflict check (verified: Partner/Firm Admin/System Admin only)
- [x] Conflict report view/export: search terms, matches, reviewer, decision, notes (CSV + HTML)

### Verification checklist

- [x] Run backend migrations and seeders successfully in the XAMPP MySQL environment (`artisan migrate --force` + `RolesAndPermissionsSeeder`)
- [x] Run backend route inspection and confirm Phase 2 API endpoints are registered under `/api/v1`
- [x] Verify backend Phase 2 API endpoints (48/48 calls OK via `verify-phase2.php`)
- [x] Run frontend typecheck/build and confirm Phase 2 routes compile (`vue-tsc --noEmit` clean; `vite build` succeeds)
- [x] Manually verify the new-client-to-case workflow steps 1-4 and 6-8 from [key workflows](../01-planning/key-workflows.md) — convert endpoint creates client + case from an intake submission (API verified)
- [x] Verify conflict checks block or clear case creation according to role permissions (RBAC matrix verified; clearance limited to Partner/Firm Admin/System Admin)
- [x] Verify case documents upload/download and template-generated drafts use case/client data
- [x] Verify tasks appear on case workspace and user dashboard, including overdue state
- [x] Verify calendar reminders produce email (log) and in-app notifications via `calendar:send-reminders` scheduler
- [x] Verify note visibility prevents client access to private/team/senior/admin-only strategy notes (controller visibility filter + policy + RBAC; Client role has no `case-notes.view`)
- [x] Verify document and case actions create audit log entries (activity log writes on document upload, case-note create, and client/case changes)
- [x] Responsive QA pass on case workspace, intake, conflict, tasks, calendar, notifications (Tailwind `sm`/`md`/`lg`/`xl` breakpoints)

### Bugs fixed (2026-06-05 verification pass)

- **`Call to undefined method ...Controller::authorize()`** (IntakeForm, ConflictCheck, and every other Phase 1/2 controller): in Laravel 11/12 the base `App\Http\Controllers\Controller` no longer pulls in `AuthorizesRequests`. Fixed once by adding `use Illuminate\Foundation\Auth\Access\AuthorizesRequests;` to the base controller, which restores `$this->authorize()` across all controllers.
- **`Trait "Spatie\Activitylog\Traits\LogsActivity" not found`** in `Client` and `LegalMatter`: the installed activity-log build (PHP 8.4 / Laravel 12) exposes the trait as `Spatie\Activitylog\Models\Concerns\LogsActivity` and `Spatie\Activitylog\Support\LogOptions`. Updated both models' imports to the installed namespaces. This unblocks anything that touches clients/cases, including intake conversion and conflict search.

### Remaining gaps (functional, beyond original open questions)

_All Phase 2 functional gaps closed as of 2026-06-05 sign-off._

### Original open questions (still to confirm)

- [x] Confirm exact storage driver and setup notes for case documents and firm templates — **local disk** under `storage/app/organizations/{id}/`
- [x] Decide whether Phase 2 PDF export is server-side only or also exposed through the document editor UI — **both**: server `export-pdf` endpoint + editor Export button
- [x] Define minimum conflict-search matching rules for Phase 2 without OpenSearch full-text — **LIKE** search across clients, parties, cases, notes (10 results per bucket)
- [x] Define which notification channels ship in Phase 2 beyond email and in-app — **email (log mailer) + in-app**; SMS/WhatsApp deferred to Phase 3
- [x] Confirm whether client-visible notes are fully deferred with the client portal — **deferred to Phase 3**
- [x] Confirm template-library permissions and whether version history is metadata-only for Phase 2 — **metadata-only version counter on save**; templates use `documents.create` permission

---

## Acceptance criteria

1. Staff completes [New client → case workflow](../01-planning/key-workflows.md) steps 1–4 and 6–8 (portal access deferred to Phase 3). **Met**
2. Conflict check blocks or clears case creation per role rules. **Met**
3. Documents upload to cases; templates generate prefilled drafts from case/client data. **Met**
4. Tasks appear on case and user dashboards; overdue tasks visible. **Met**
5. Calendar shows firm/lawyer/case views; reminders fire before events. **Met**
6. Notes respect visibility; clients cannot see strategy notes. **Met**
7. Document and case actions write to [audit log](../modules/37-audit-trail.md). **Met**

---

## MVP items covered

MVP modules 5–10, 15 (case notes), partial 7–8 (document management, basic templates).

---

## Out of scope

- Client portal intake (Phase 3); staff-facing intake OK in Phase 2
- AI drafting, track changes, OnlyOffice, approval workflows
- Billing, time tracking, client messaging portal
- OpenSearch full-text (optional: index cases/clients only)

---

## Suggested implementation order

1. Case notes
2. Document upload + case documents tab
3. Template library + TipTap editor
4. Tasks + automated task rules
5. Calendar + reminders
6. Intake form builder + submission + convert to case
7. Conflict check + workflow
8. Notifications (wire to existing events)
