# BanwoHub UI Inventory

**Design system:** [`DESIGN_SYSTEM.md`](./DESIGN_SYSTEM.md)  
**Last updated:** 2026-06-10  
**Progress:** 52 of 52 screens **done** | 0 **in_progress** | 0 **pending**  
**Gap audit (2026-06-10):** 0 forbidden hex hits in `frontend/src` after pass below.

Legend: **done** = tiimi layout + BanwoHub teal/gold + white cards; **in_progress** = partial; **pending** = not yet redesigned.

---

## Global foundation

| Item | File(s) | Issues (before) | Status |
|------|---------|-----------------|--------|
| Design tokens | `frontend/src/styles/tokens.css` | tiimi yellow replaced gold; gray cards | **done** |
| Global CSS / components | `frontend/src/styles/main.css` | accent-gold + action-teal buttons | **done** |
| App layout | `frontend/src/components/layout/AppLayout.vue` | — | **done** |
| Sidebar | `frontend/src/components/layout/Sidebar.vue` | Gold pill active state | **done** |
| Topbar | `frontend/src/components/layout/Topbar.vue` | Gold + button | **done** |
| Modal | `frontend/src/components/common/BwModal.vue` | — | **done** |
| Page header | `frontend/src/components/common/PageHeader.vue` | OK | **done** |
| Portal layout | `frontend/src/components/layout/PortalLayout.vue` | Bright header, gold nav pills | **done** |

---

## Auth (guest)

| Route | File | Issues | Status |
|-------|------|--------|--------|
| `/login` | `views/LoginView.vue` | tiimi split layout | **done** |
| `/forgot-password` | `views/ForgotPasswordView.vue` | Same | **done** |
| `/reset-password` | `views/ResetPasswordView.vue` | Same | **done** |

---

## Main app (authenticated)

| Route | File | Issues | Status |
|-------|------|--------|--------|
| `/dashboard` | `views/DashboardView.vue` | Colorful ApexCharts dashboard: metric cards w/ sparklines/gauges, case activity area chart, status donut, filing/motion/task charts, recent matter cards | **done** |
| `/clients` | `views/clients/ClientsListView.vue` | Employee-grid cards: count title, filter, grid/list toggle, bulk select, `ClientCard` white cards, gold pagination | **done** |
| `/clients/new` | `views/clients/ClientFormView.vue` | Teal save, white form card | **done** |
| `/clients/:id` | `views/clients/ClientDetailView.vue` | Student-style detail: breadcrumbs, header + Edit/More, gold tabs, 2-col overview + `ClientProfileSidebar` KPIs | **done** |
| `/clients/:id/edit` | `views/clients/ClientFormView.vue` | Student-style edit: Save/Cancel header, form cards, sidebar w/ Change photo | **done** |
| `/cases` | `views/cases/CasesListView.vue` | — | **done** |
| `/cases/new` | `views/cases/CaseFormView.vue` | Teal save, white form card | **done** |
| `/cases/:id` | `views/cases/CaseDetailView.vue` | Gold tab underline, white panels | **done** |
| `/cases/:id/edit` | `views/cases/CaseFormView.vue` | Form styling | **done** |
| `/intake` | `views/IntakeView.vue` | — | **done** |
| `/conflict-checks` | `views/ConflictChecksView.vue` | — | **done** |
| `/time-tracking` | `views/TimeTrackingView.vue` | — | **done** |
| `/invoices` | `views/invoices/InvoicesListView.vue` | — | **done** |
| `/invoices/new`, `/:id` | `views/invoices/InvoiceDetailView.vue` | — | **done** |
| `/messages` | `views/MessagesView.vue` | — | **done** |
| `/calendar` | `views/CalendarView.vue` | — | **done** |
| `/reports` | `views/ReportsView.vue` | — | **done** |
| `/ai-assistant` | `views/AiAssistantView.vue` | White chat card | **done** |
| `/ai-governance` | `views/AiGovernanceView.vue` | White table cards | **done** |
| `/filings` | `views/FilingsView.vue` | — | **done** |
| `/evidence` | `views/EvidenceView.vue` | — | **done** |
| `/briefs` | `views/BriefsView.vue` | — | **done** |
| `/briefs/:id` | `views/briefs/BriefEditorView.vue` | Teal save bar | **done** |
| `/motions` | `views/MotionsView.vue` | — | **done** |
| `/motions/:id` | `views/motions/MotionEditorView.vue` | — | **done** |
| `/research` | `views/ResearchView.vue` | — | **done** |
| `/e-discovery` | `views/EdiscoveryView.vue` | White list card | **done** |
| `/knowledge` | `views/KnowledgeView.vue` | — | **done** |
| `/legal-projects` | `views/LegalProjectsView.vue` | White stat/kanban cards | **done** |
| `/legal-analytics` | `views/LegalAnalyticsView.vue` | White chart cards | **done** |
| `/training` | `views/TrainingView.vue` | White list cards | **done** |
| `/notifications` | `views/NotificationsView.vue` | — | **done** |
| `/search` | `views/SearchView.vue` | White results cards | **done** |
| `/audit` | `views/AuditView.vue` | White table card | **done** |
| `/settings` | `views/SettingsView.vue` | Accordion on white card | **done** |
| `/settings/users` | `views/settings/UsersView.vue` | — | **done** |

---

## Case workspace panels (embedded in CaseDetailView)

| Panel | File | Inline add form | Status |
|-------|------|-----------------|--------|
| Notes | `components/cases/CaseNotesPanel.vue` | → `BwModal` | **done** |
| Tasks | `components/cases/CaseTasksPanel.vue` | → `BwModal`; full-width kanban (5-col grid), icon list/kanban toggle | **done** |
| Time | `components/cases/CaseTimePanel.vue` | → `BwModal` | **done** |
| Expenses | `components/cases/CaseExpensesPanel.vue` | → `BwModal` | **done** |
| Invoices | `components/cases/CaseInvoicesPanel.vue` | Pharmly order-list: hero teal summary cards, toolbar (search, gold Add, filter, export, date range), data table w/ icon actions, numbered pagination; generate → `BwModal` | **done** |
| Messages | `components/cases/CaseMessagesPanel.vue` | Uses `MessageThreadPanel` | **done** |
| Calendar | `components/cases/CaseCalendarPanel.vue` | → `BwModal` | **done** |
| Documents | `components/cases/CaseDocumentsPanel.vue` | → `BwModal` | **done** |
| Research | `components/cases/CaseResearchPanel.vue` | → `BwModal` | **done** |
| Knowledge | `components/cases/CaseKnowledgePanel.vue` | → `BwModal` | **done** |
| Conflicts | `components/cases/CaseConflictChecksPanel.vue` | → `BwModal` | **done** |
| Filings | `components/cases/CaseFilingsPanel.vue` | → `BwModal` | **done** |
| Evidence | `components/cases/CaseEvidencePanel.vue` | → `BwModal` | **done** |
| Briefs | `components/cases/CaseBriefsPanel.vue` | → `BwModal` | **done** |
| Motions | `components/cases/CaseMotionsPanel.vue` | → `BwModal` | **done** |
| E-discovery | `components/cases/CaseEdiscoveryPanel.vue` | → `BwModal` | **done** |
| Project | `components/cases/CaseProjectPanel.vue` | → `BwModal` | **done** |

---

## Client panels

| Panel | File | Status |
|-------|------|--------|
| Profile sidebar | `components/clients/ClientProfileSidebar.vue` | **done** — avatar, quick actions, KPI grid |
| Cases | `components/clients/ClientCasesPanel.vue` | **done** — embedded tab panel |
| Contacts | `components/clients/ClientContactsPanel.vue` | **done** — gold Add, modal, `embedded` tab mode |
| Communication | `components/clients/ClientCommunicationPanel.vue` | **done** — gold Add, modal, `embedded` tab mode |
| Invoices | `components/clients/ClientInvoicesPanel.vue` | **done** — `embedded` tab mode |

---

## Portal (client-facing)

| Route | File | Status |
|-------|------|--------|
| `/portal/login` | `views/portal/PortalLoginView.vue` | **done** |
| `/portal` | `views/portal/PortalDashboardView.vue` | **done** |
| `/portal/cases` | `views/portal/PortalCasesListView.vue` | **done** |
| `/portal/cases/:id` | `views/portal/PortalCaseDetailView.vue` | **done** |
| `/portal/invoices` | `views/portal/PortalInvoicesListView.vue` | **done** |
| `/portal/invoices/:id` | `views/portal/PortalInvoiceDetailView.vue` | **done** |
| `/portal/invoices/.../payment/*` | Payment success/cancel views | **done** |
| `/portal/messages` | `views/portal/PortalMessagesView.vue` | **done** |
| `/portal/appointments` | `views/portal/PortalAppointmentsView.vue` | **done** |
| `/portal/intake` | `views/portal/PortalIntakeView.vue` | **done** |
| `/portal/profile` | `views/portal/PortalProfileSettingsView.vue` | **done** |
| `/portal/sign/:id` | `views/portal/PortalSignatureSignView.vue` | **done** |

---

## Public / misc

| Route | File | Status |
|-------|------|--------|
| `/support` | `views/PublicSupportChatView.vue` | **done** |

---

## Shared components

| Component | File | Status |
|-----------|------|--------|
| StatusBadge | `components/common/StatusBadge.vue` | **done** |
| EmptyState | `components/common/EmptyState.vue` | **done** |
| PaginationBar | `components/common/PaginationBar.vue` | **done** — gold active page |
| ClientCard | `components/clients/ClientCard.vue` | **done** — tiimi employee-grid profile card |
| ClientProfileSidebar | `components/clients/ClientProfileSidebar.vue` | **done** — detail/edit right column |
| MessageThreadPanel | `components/messages/MessageThreadPanel.vue` | **done** |
| ResearchCommandCenterPanel | `components/research/ResearchCommandCenterPanel.vue` | **done** |
| Settings panels | `components/settings/*` | **done** |
| AI panels | `components/ai/*` | **done** |

---

## Color restoration (2026-06-10)

| Token | Hex | Usage |
|-------|-----|-------|
| `--action-teal` | `#0A4F5E` | Save, Publish, Send |
| `--action-teal-hover` | `#083944` | Teal hover |
| `--accent-gold` | `#B1915A` | Add, active nav pill, tab underline |
| `--accent-gold-hover` | `#9A8252` | Gold hover |
| `--surface` | `#FFFFFF` | All card bodies |
| `--background` | `#F8F9FB` | Workspace/page background only |

**Not used:** tiimi `#FFD640` for brand accents.

### Gap audit fixes (2026-06-10)

| Pattern | Files fixed | Change |
|---------|-------------|--------|
| `#3D7A70` (old teal) | `DashboardView.vue`, `InvoicesListView.vue`, `TimeTrackingView.vue` | → `#0A4F5E` stat accent bars |
| Gray panel body | `PublicSupportChatView.vue` | chat scroll area → `bg-surface` |
| Gray panel selection | `CaseDocumentsPanel.vue`, `AiProvidersPanel.vue` | → `bg-primary-50` |

**Verified clean (batch 4 + named views):** `CaseDetailView`, `BriefEditorView`, `MotionEditorView`, `InvoiceDetailView`, `AuthShell`, auth views, all case panels, `PaginationBar`, `ResearchCommandCenterPanel`, client/portal screens — no `#FFD640`, `#3D7A70`, or `accent-yellow`.

**Acceptable remaining `bg-surface-muted` / `bg-muted`:** list-row hovers, progress-bar tracks, search input fill in `Topbar` — not card/panel bodies.
