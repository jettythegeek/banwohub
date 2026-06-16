# Phase 4: AI and Automation

**Goal:** AI-assisted drafting and support, document automation, approval workflows, and e-signature—with governance and human review.

**Depends on:** [Phase 3](./phase-3-client-business-operations.md), separate [AI service](../02-tech-stack/tech-stack.md)

**Unlocks:** MVP AI items; [Phase 5](./phase-5-advanced-legal-tools.md) advanced drafting tools

---

## Modules in this phase

| Module | Spec |
|--------|------|
| AI chatbot | [34-ai-chatbot](../modules/34-ai-chatbot.md) |
| AI governance | [35-ai-governance](../modules/35-ai-governance.md) |
| Document automation (advanced) | [09-document-management](../modules/09-document-management.md) |
| Approval workflows | [27-approval-workflows](../modules/27-approval-workflows.md) |
| E-signature | [28-e-signature](../modules/28-e-signature.md) |
| Legal research (assistant) | [12-legal-research](../modules/12-legal-research.md) — AI assistant portion |

---

## AI service (separate deployable)

Implement per [tech stack](../02-tech-stack/tech-stack.md):

- [x] Internal API authenticated from Laravel (`ai-service/`, Bearer `AI_SERVICE_KEY`)
- [x] Endpoints: chatbot, document summarize, draft assist, case Q&A, intake summary, timeline summary, research summarize/suggest-authorities
- [x] AI governance logs: user, prompt context, output id, timestamp (`ai_governance_logs` + Spatie activity `ai`)
- [x] Rate limits (system setting `AI_RATE_LIMIT_PER_MINUTE`)

Laravel remains orchestration layer; no heavy ML inside PHP. Stub mode (`AI_STUB_MODE=true`) returns labeled responses without running the Node service.

### Slice 1 complete (2026-06-05)

| Area | Status |
|------|--------|
| `ai-service/` Node scaffold | Done — `/health`, `/v1/*` stub endpoints |
| Laravel `AiServiceClient` | Done — HTTP client + stub fallback |
| `AiGovernanceService` + `ai_governance_logs` | Done |
| Staff API `/api/v1/ai/*` | Done — chat, summarize, draft-assist, case-qa, intake/timeline summary |
| Portal client block (`EnsureStaffAiAccess`) | Done — clients cannot call internal AI |
| Permissions `ai.use`, `ai.governance.view` | Done — seeded for Firm Admin, Partner, Lawyer, Paralegal |
| Verification | `scripts/verify-phase4.php` — **22/22** |

### Slice 2 complete (2026-06-05)

| Area | Status |
|------|--------|
| `/ai-governance` view | Done — disclaimer banner, settings summary, paginated log table |
| `/ai-assistant` staff panel | Done — chat UI wired to `POST /ai/chat`, label + requires-review badges |
| Settings tab link | Done — AI governance tab when `ai.governance.view` |
| Sidebar nav | Done — AI assistant entry when `ai.use` |
| Frontend API client | Done — `aiApi` in `frontend/src/lib/api.ts` |
| Verification | `verify-phase4.php` — **31/31** (permission gate, UI file checks) |

**Run:** `php artisan migrate && php scripts/verify-phase4.php`

### Slice 3 complete (2026-06-05)

| Area | Status |
|------|--------|
| `ai_review_status` on `legal_documents` | Done — `ai_generated`, review status, approver, governance log FK |
| `POST /documents/ai-draft` | Done — saves AI output with status `generated` |
| `PATCH /documents/{id}/ai-review` | Done — workflow transitions; blocks finalize without approval |
| Case document editor AI wiring | Done — `CaseDocumentsPanel` draft-assist + summarize + review UI |
| Reused governance UI components | Done — `AiDisclaimerBanner`, `AiOutputBadges` |
| Verification | `verify-phase4.php` — extended for AI draft workflow |

**Run:** `php artisan migrate && php scripts/verify-phase4.php && php scripts/verify-phase3.php && php scripts/verify-phase2.php`

### Slice 4 complete (2026-06-05)

| Area | Status |
|------|--------|
| `approval_requests` table + polymorphic subjects | Done — `legal_document`, `invoice` |
| `ApprovalWorkflowService` | Done — submit, review, notifications, send/share guards |
| API `/api/v1/approval-requests` | Done — list, submit, review with RBAC |
| Permissions `approvals.view`, `approvals.submit`, `approvals.review` | Done — seeded by role |
| Block send/share until approved | Done — invoice `mark-sent`, document `client_visible` |
| In-app notifications | Done — request, completed, changes requested, rejected |
| Frontend panels | Done — `ApprovalWorkflowPanel` on documents + invoices |
| Verification | `verify-phase4.php` — **66/66** (regression: phase3 159/159, phase2 48/48) |

**Run:** `php artisan migrate && php scripts/verify-phase4.php && php scripts/verify-phase3.php && php scripts/verify-phase2.php`

### Slice 5 complete (2026-06-05)

| Area | Status |
|------|--------|
| `ai_provider_configs` table | Done — per-org provider, encrypted API key, model, settings |
| Org `active_ai_provider` in `organizations.settings` | Done |
| `AiProviderManager` + adapters | Done — OpenAI, Anthropic, Google AI (Gemini), Deepseek |
| `AiServiceClient` routing | Done — active provider first; stub only when none configured |
| API `/settings/ai-providers` | Done — list, update, set active, test-connection |
| Permission `ai.providers.manage` | Done — Firm Admin + System Admin only |
| Settings → AI Providers tab | Done — provider cards with key, enable, active, test |
| Verification | `verify-phase4.php` — extended (66+ checks) |

**Admin setup:** Settings → AI Providers → enter API key for a provider → enable → Save → Test connection → Set active.

**Run:** `php artisan migrate && php scripts/verify-phase4.php && php scripts/verify-phase3.php && php scripts/verify-phase2.php`

### Slice 6 complete (2026-06-05)

| Area | Status |
|------|--------|
| `signature_requests` table | Done — document, case, client, status, fields, audit, signed copy FK |
| `SignatureRequestService` | Done — send, sign, decline, signed copy storage, staff notifications |
| Staff API `/api/v1/signature-requests` | Done — list, send, show with RBAC |
| Portal API `/api/v1/portal/signature-requests` | Done — list, show, sign, decline |
| Permissions `signatures.view`, `signatures.send`, `portal.signatures.*` | Done — seeded by role |
| Staff UI `SignatureSendPanel` on `CaseDocumentsPanel` | Done — send for signature from case documents |
| Portal sign flow | Done — `/portal/sign/:id` with canvas/typed signature + audit trail |
| Signed copy on case | Done — new `legal_document` (category `signed`, PDF when Dompdf available) |
| Verification | `verify-phase4.php` — **113/113** (regression: phase3 159/159, phase2 48/48) |

**Run:** `php artisan migrate && php scripts/verify-phase4.php && php scripts/verify-phase3.php && php scripts/verify-phase2.php`

### Slice 7 complete (2026-06-05)

| Area | Status |
|------|--------|
| `POST /ai/research/summarize-notes` | Done — case research notes → AI summary with governance logging |
| `POST /ai/research/suggest-authorities` | Done — issue-based authority suggestions + `verification_warning` |
| `ai-service` research routes | Done — stub endpoints mirror Laravel orchestration |
| Case workspace Research tab | Done — `CaseResearchPanel` on `CaseDetailView` |
| Governance + disclaimers | Done — `requires_review`, labeled outputs, `ai_governance_logs` |
| Verification | `verify-phase4.php` — **132/132** (regression: phase3 159/159, phase2 48/48) |

**Run:** `php artisan migrate && php scripts/verify-phase4.php && php scripts/verify-phase3.php && php scripts/verify-phase2.php`

**Phase 4 complete (MVP):** yes (signed off 2026-06-05)

MVP acceptance criteria 1–6 are met. Full Phase 4 deliverables still open: public support chatbot, staff/lawyer workspace bots (beyond existing staff `/ai-assistant`), document merge fields, clause library, version history for AI vs human edits, e-sign signing order/reminders, and saving research artifacts to case folders (Phase 5 covers full legal research DB).

---

## Deliverables

### AI chatbot

- [ ] Public support bot (FAQ, lead capture, book consultation)
- [ ] Staff assistance bot (policies, KB — link [knowledge](../modules/29-knowledge-management.md) lite)
- [ ] Lawyer workspace bot (case file Q&A, draft help)
- [ ] Disclaimers on every legal output ([AI governance](../modules/35-ai-governance.md))

### AI document drafting

- [x] Generate draft from template + case data via AI service
- [x] Statuses: Generated → Under review → Edited → Approved → Finalized
- [x] Lawyer must approve before client/file use (finalize blocked until `approved`)
- [ ] Version history for AI vs human edits

### Document automation

- [ ] Merge fields from case/client/intake
- [ ] Clause library (basic)
- [ ] Track changes / comments (TipTap or OnlyOffice evaluation)
- [ ] Compare versions

### Approval workflows

- [x] Submit document/motion/invoice for review
- [x] Reviewer comments, approve/reject/request changes
- [x] Notifications on approval events
- [x] Block send/filing until approved where configured
- [ ] Supports [document drafting workflow](../01-planning/key-workflows.md) — motion/filing pipeline (Phase 5)

### E-signature

- [x] Send document for signature; field placement (default signature, date, name)
- [ ] Signing order; reminders
- [x] Audit trail (signer, IP, timestamp, version)
- [x] Store signed PDF/HTML copy on case

### Research assistant (lite)

- [x] AI summarizes saved research notes
- [x] Suggests authorities with **source verification warning**
- [ ] Save research to case (full case law DB in Phase 5)

---

## Acceptance criteria

1. All AI outputs labeled; disclaimers shown before use. **Met (slices 1–3, 7)**
2. No AI document reaches “final” without lawyer approval status. **Met (slice 3)**
3. AI actions logged in audit trail and AI governance store. **Met (slice 1)**
4. Clients cannot access internal AI tools. **Met (slice 1)**
5. E-sign completes portal workflow sign step. **Met (slice 6)**
6. Approval workflow blocks filing/send until approved (where configured). **Met (slice 4)**

---

## MVP items covered

MVP modules 19–20 (AI chatbot basic, AI drafting with review warnings).

---

## Out of scope (Phase 5)

- Dedicated brief/motion editors
- Full legal research database integration
- Court e-filing
- Predictive analytics

---

## Suggested implementation order

1. ~~AI service scaffold + Laravel client + governance logging~~ **Done (slice 1)**
2. ~~AI governance UI (labels, statuses, disclaimers)~~ **Done (slice 2)**
3. ~~AI drafting on existing document editor~~ **Done (slice 3)**
4. ~~Approval workflow engine~~ **Done (slice 4)**
5. ~~E-signature integration~~ **Done (slice 6)**
6. ~~Research assistant (lite)~~ **Done (slice 7)**
7. Public + staff chatbots
8. Lawyer workspace bot + case Q&A (case Q&A API exists; dedicated workspace UI deferred)
9. Document automation enhancements (merge, clauses)

---

## Progress log

| Date | Slice | Notes |
|------|-------|-------|
| 2026-06-05 | 1 — AI scaffold + governance | `ai-service/`, Laravel orchestration, governance logs, staff-only API. Regression: phase3 159/159, phase2 48/48. |
| 2026-06-05 | 2 — AI governance UI | `/ai-governance`, `/ai-assistant`, disclaimer banners, governance log table, nav + settings links. |
| 2026-06-05 | 3 — AI document drafting | Case editor wired to draft-assist/summarize; `ai_review_status` workflow; finalize requires lawyer approval. |
| 2026-06-05 | 4 — Approval workflow engine | `approval_requests` API + RBAC; invoice send and document share guards; notifications; document/invoice panels. |
| 2026-06-05 | 5 — Multi-provider AI | Per-org provider configs (OpenAI, Anthropic, Google, Deepseek); Settings → AI Providers; `ai.providers.manage` RBAC. |
| 2026-06-05 | 6 — E-signature MVP | `signature_requests` API + portal sign flow; signed copy on case; staff send from CaseDocumentsPanel; acceptance criterion #5 met. |
| 2026-06-05 | 7 — Research assistant (lite) | `/ai/research/*` APIs, CaseResearchPanel, governance logging; Phase 4 MVP sign-off. Regression: phase3 159/159, phase2 48/48. |
