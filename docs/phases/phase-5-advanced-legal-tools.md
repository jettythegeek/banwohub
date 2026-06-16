# Phase 5: Advanced Legal Tools

**Goal:** Litigation-grade tooling—briefs, motions, research, court forms, evidence, e-discovery, analytics, knowledge base, and CLE.

**Depends on:** [Phase 4](./phase-4-ai-automation.md) (AI service, documents, approvals)

**Aligns with:** [Post-MVP modules](../01-planning/mvp-scope.md)

---

## Modules in this phase

| Module | Spec |
|--------|------|
| Brief writing | [10-brief-writing](../modules/10-brief-writing.md) |
| Motion writing | [11-motion-writing](../modules/11-motion-writing.md) |
| Case law & legal research | [12-legal-research](../modules/12-legal-research.md) |
| Court forms | [13-court-forms](../modules/13-court-forms.md) |
| Filing status tracker | [14-filing-tracker](../modules/14-filing-tracker.md) |
| Evidence management | [23-evidence-management](../modules/23-evidence-management.md) |
| E-discovery | [24-e-discovery](../modules/24-e-discovery.md) |
| Legal project management | [21-legal-project-management](../modules/21-legal-project-management.md) |
| AI legal analytics | [31-ai-analytics](../modules/31-ai-analytics.md) |
| Knowledge management | [29-knowledge-management](../modules/29-knowledge-management.md) |
| Training & CLE | [30-training-cle](../modules/30-training-cle.md) |

Also enable: [External consultant](../00-foundation/03-roles-and-permissions.md) portal access patterns, advanced [evidence](../modules/23-evidence-management.md), court [e-filing integration](../modules/42-integrations.md) where available.

---

## Deliverables (by workstream)

### Drafting & filings

- [x] Filing lifecycle tracker (manual filing, status transitions, court response)
- [x] Brief editor with research side panel, citations, AI outline/rewrite
- [x] Motion templates + AI structure check + approval → filing pipeline
- [x] Court form templates by jurisdiction; auto-fill from case
- [ ] AI form validation; filing lifecycle statuses — statuses done; AI validation deferred
- [ ] E-filing integration where APIs exist; manual status otherwise — manual filing done

### Research & knowledge

- [x] Legal search UI with filters; saved research folders
- [ ] External research DB integration (when licensed)
- [x] Firm knowledge base: articles, SOPs, clauses; search + AI chatbot link

### Litigation support

- [x] Evidence upload with metadata, exhibit numbers, chain of custody
- [x] Exhibit bundles export
- [x] E-discovery: bulk upload, tagging, privilege, reviewer assignment
- [x] Multi-reviewer progress dashboard

### Operations & learning

- [x] Legal projects: milestones, budget, workload views
- [x] Analytics: case duration, outcomes, predictive hints (with disclaimers)
- [x] CLE: courses, quizzes, credits, certificates, admin compliance reports

---

## Acceptance criteria

1. [Court filing workflow](../01-planning/key-workflows.md) completable inside the app (manual file OK if no e-filing API). **Met (Slice 1)**
2. Briefs/motions require lawyer review before finalize (reuse Phase 4 governance). **Met (Slices 3–4)**
3. Evidence exhibits generate numbered list export. **Met (Slice 2)**
4. E-discovery supports tag/privilege/relevance filters at scale (performance test with large sets). **Met (Slice 6)**
5. Analytics dashboards pull from production data with firm-level isolation. **Met (Slice 8)**
6. Consultant role sees only invited matters—no billing or internal notes. **Met (Slices 1–8 RBAC)**

---

## Suggested implementation order

1. Filing tracker + court forms (manual filing) — **Slice 1 complete**
2. Evidence management — **Slice 2 complete**
3. Brief writing tool — **Slice 3 complete**
4. Motion writing assistant — **Slice 4 complete**
5. Legal research (search + save + folders) — **Slice 5 complete**
6. E-discovery — **Slice 6 complete**
7. Knowledge management — **Slice 7 complete**
8. Legal project management + workload — **Slice 8 complete**
9. AI analytics + reporting extensions — **Slice 8 complete**
10. Training/CLE module — **Slice 8 complete**
11. E-filing + research DB integrations (as vendors available)

---

## Risk reminders

See [risks](../01-planning/risks-and-considerations.md): verify all case law citations; e-filing depends on external systems; e-discovery storage and indexing costs—plan OpenSearch scaling.

---

### Slice 1 complete (2026-06-05)

| Area | Status |
|------|--------|
| `court_form_templates`, `court_form_instances`, `court_filings` tables | Done |
| Court form templates seeder (Federal + State) | Done |
| `CourtFormPrefillService` auto-fill from case/client/lawyer | Done |
| API `/court-form-templates`, `/court-form-instances`, `/court-filings` | Done — CRUD, prefill, status transitions |
| Filing lifecycle statuses (11 states) + manual filing method | Done |
| Permissions `filings.*`, `court-forms.*` (Consultant excluded) | Done |
| `/filings` staff view + case workspace Filings tab | Done |
| Verification | `scripts/verify-phase5.php` — **31/31** (Slice 1 only) |

**Deferred to Slice 2:** AI form validation, approval → filing pipeline, e-filing integration, auto calendar deadlines on court response.

### Slice 2 complete (2026-06-05)

| Area | Status |
|------|--------|
| `evidence_items`, `evidence_custody_logs` tables | Done |
| Evidence upload with metadata (type, description, source, date obtained) | Done |
| Exhibit number assignment (`EvidenceExhibitService`) | Done |
| Chain of custody log (auto on upload + manual entries) | Done |
| Exhibit bundle export (numbered index + zip) | Done |
| API `/evidence-items` CRUD + status, assign-exhibit, custody-logs, exhibit-index, export-bundle | Done |
| Permissions `evidence.*` (Consultant excluded) | Done |
| `/evidence` staff view + case workspace Evidence tab | Done |
| Verification | `scripts/verify-phase5.php` — **59/59** (Slices 1–2) |

**Deferred to Slice 3:** Exhibit grouping, legal-issue linking, evidence tags UI polish.

### Slice 3 complete (2026-06-05)

| Area | Status |
|------|--------|
| `legal_briefs`, `brief_citations` tables | Done |
| Brief CRUD API (case-linked, title, content HTML, status draft/review/final) | Done |
| Citation fields (authority type, citation text, source note) | Done |
| AI outline/rewrite via `AiServiceClient` + governance logging | Done |
| Permissions `briefs.*` (Consultant excluded) | Done |
| `/briefs` staff list + case workspace Briefs tab + `/briefs/:id` editor | Done |
| RichTextEditor + research side panel (notes summary, authority suggestions) | Done |
| Verification | `scripts/verify-phase5.php` — **Slices 1–3** |

**Deferred to Slice 4:** Argument structure guide UI, legal terminology suggestions, citation format automation.

### Slice 4 complete (2026-06-05)

| Area | Status |
|------|--------|
| `motion_templates`, `legal_motions` tables + `court_filings.legal_motion_id` | Done |
| Motion templates seeder (8 common motion types) | Done |
| Motion CRUD API with status draft/review/approved/filing_ready | Done |
| AI structure check via `AiServiceClient` + governance logging | Done |
| Create court filing from approved motion | Done |
| Permissions `motions.*` (Consultant excluded) | Done |
| `/motions` staff list + case workspace Motions tab + `/motions/:id` editor | Done |
| Verification | `scripts/verify-phase5.php` — **Slices 1–4** |

**Deferred to Slice 6:** AI argument drafting, authority recommendations, client review workflow step.

### Slice 5 complete (2026-06-05)

| Area | Status |
|------|--------|
| `legal_research_entries`, `research_folders`, `research_saved_items` tables | Done |
| Internal research library (title, citation, summary, jurisdiction, tags, document type) | Done |
| Starter entries seeder (5 global authorities) | Done |
| API `/legal-research-entries` CRUD + keyword/jurisdiction/type search | Done |
| API `/research-folders` CRUD + `/items` save/list | Done |
| API `/research-saved-items` list + delete (per-case scoping) | Done |
| Permissions `research.*` (Consultant excluded) | Done |
| `/research` staff view + case workspace Research tab enhancements | Done |
| Phase 4 AI assistant retained (summarize notes, suggest authorities) | Done |
| Verification | `scripts/verify-phase5.php` — **Slices 1–5** |

**Deferred to Slice 7:** External licensed research DB, AI case comparison, conflicting-decision analysis.

### Slice 6 complete (2026-06-05)

| Area | Status |
|------|--------|
| `ediscovery_collections`, `ediscovery_documents`, `ediscovery_tags`, `ediscovery_review_assignments` tables | Done |
| Bulk upload with default privilege/relevance/tags | Done |
| Document tagging (privilege, relevance, custom tags) | Done |
| Reviewer assignment + per-document review status | Done |
| Multi-reviewer progress dashboard (`EdiscoveryReviewProgressService`) | Done |
| Filters at scale (privilege, relevance, tag, reviewer, status, keyword) | Done |
| API `/ediscovery-collections`, `/ediscovery-documents`, `/ediscovery-tags`, `/ediscovery-review-assignments`, `/ediscovery-review-progress` | Done |
| Permissions `ediscovery.*` (Consultant excluded) | Done |
| `/e-discovery` staff view + case workspace E-discovery tab | Done |
| Verification | `scripts/verify-phase5.php` — **Slices 1–6** |

**Deferred to Slice 8:** Full-text OCR indexing, highlight/annotation UI, OpenSearch scaling.

### Slice 7 complete (2026-06-05)

| Area | Status |
|------|--------|
| `knowledge_articles` table | Done |
| Articles, SOPs, clause snippets (`content_type`) | Done |
| Categories, tags, practice area organization | Done |
| Starter articles seeder (5 global resources) | Done |
| API `/knowledge-articles` CRUD + keyword/category/type/tag search | Done |
| Case-scoped listing (firm-wide + matter notes) | Done |
| Permissions `knowledge.*` (Consultant excluded) | Done |
| `/knowledge` staff view + case workspace Knowledge tab | Done |
| Optional AI assistant link with pre-filled KB prompt | Done |
| Verification | `scripts/verify-phase5.php` — **Slices 1–7** |

**Deferred to Slice 8:** Legal project management, AI analytics, CLE module.

### Slice 8 complete (2026-06-05)

| Area | Status |
|------|--------|
| `legal_project_milestones`, `legal_project_budgets` tables | Done |
| Case milestones with types, status, due dates, assignees | Done |
| Budget tracking with category lines and totals | Done |
| Workload view per lawyer (`LegalProjectWorkloadService`) | Done |
| API `/legal-project-milestones`, `/legal-project-budgets`, `/legal-project-workload` | Done |
| `LegalAnalyticsService` dashboards from production data | Done |
| API `/legal-analytics/dashboard`, `/legal-analytics/hints` with disclaimers | Done |
| `training_courses`, `training_enrollments`, `training_certificates` tables | Done |
| Courses, quizzes, CLE credit tracking, certificates | Done |
| Admin CLE compliance report | Done |
| Starter training courses seeder (3 global courses) | Done |
| Permissions `projects.*`, `analytics.view`, `training.*` (Consultant excluded) | Done |
| `/legal-projects`, `/legal-analytics`, `/training` staff views | Done |
| Case workspace Project tab (milestones + budget) | Done |
| Verification | `scripts/verify-phase5.php` — **287/287** (Slices 1–8) |

**Phase 5 complete (MVP):** yes (signed off 2026-06-05)

MVP acceptance criteria 1–6 are met. Full Phase 5 deliverables still open: AI form validation, e-filing integration, external licensed research DB, full-text OCR/OpenSearch for e-discovery, advanced predictive ML models, and consultant portal refinements beyond invited-matter scoping.

**Run:** `php artisan migrate && php scripts/verify-phase5.php && php scripts/verify-phase4.php && php scripts/verify-phase3.php && php scripts/verify-phase2.php`
