# Banwolaw Hub — Product Documentation

AI-powered legal practice management system **for Banwolaw only** (single organization — not multi-tenant SaaS). This folder splits the monolithic PRD into **foundation docs**, **planning docs**, **module specs**, **phase implementation guides**, and **tech stack** references.

> **Source:** Content is derived from [`product description.md`](../product%20description.md) at the repo root. Use this `docs/` tree for phase-by-phase implementation.

---

## How to use these docs

1. Read [00-foundation](./00-foundation/) for product vision, goals, and roles.
2. Review [01-planning/mvp-scope.md](./01-planning/mvp-scope.md) for the first shippable cut.
3. Apply the [design system](./03-design-system/) (Banwolaw brand, flat UI, reference layouts) before building screens.
4. Implement in order: [Phase 1](./phases/phase-1-foundation.md) → [Phase 5](./phases/phase-5-advanced-legal-tools.md).
5. Open the linked **module spec** for each feature before building.
6. Apply [cross-cutting requirements](./01-planning/non-functional-requirements.md) and [security](./modules/36-security-compliance.md) in every phase.

---

## Foundation

| Document | Description |
|----------|-------------|
| [01-product-overview.md](./00-foundation/01-product-overview.md) | Product name, description, vision, target users |
| [02-goals-and-principles.md](./00-foundation/02-goals-and-principles.md) | Product goals and core principles |
| [03-roles-and-permissions.md](./00-foundation/03-roles-and-permissions.md) | System Admin through External Consultant (Banwolaw-only) |
| [04-final-summary.md](./00-foundation/04-final-summary.md) | Case-centric operating system summary |

---

## Planning

| Document | Description |
|----------|-------------|
| [mvp-scope.md](./01-planning/mvp-scope.md) | MVP goal, 20 in-scope modules, post-MVP list |
| [implementation-roadmap.md](./01-planning/implementation-roadmap.md) | Phase overview, dependencies, MVP alignment |
| [100-percent-completion-roadmap.md](./01-planning/100-percent-completion-roadmap.md) | Gap inventory, workstreams, slice order to 100% |
| [key-workflows.md](./01-planning/key-workflows.md) | End-to-end user workflows |
| [non-functional-requirements.md](./01-planning/non-functional-requirements.md) | Performance, scalability, accessibility |
| [data-model.md](./01-planning/data-model.md) | Core data objects |
| [success-metrics.md](./01-planning/success-metrics.md) | KPIs |
| [risks-and-considerations.md](./01-planning/risks-and-considerations.md) | Legal, privacy, adoption, AI risks |

---

## Implementation phases

| Phase | Focus | Guide |
|-------|--------|-------|
| **1** | Foundation | [phase-1-foundation.md](./phases/phase-1-foundation.md) |
| **2** | Core legal operations | [phase-2-core-legal-operations.md](./phases/phase-2-core-legal-operations.md) |
| **3** | Client & business ops | [phase-3-client-business-operations.md](./phases/phase-3-client-business-operations.md) |
| **4** | AI & automation | [phase-4-ai-automation.md](./phases/phase-4-ai-automation.md) |
| **5** | Advanced legal tools | [phase-5-advanced-legal-tools.md](./phases/phase-5-advanced-legal-tools.md) |

---

## Module specifications

Each file under [`modules/`](./modules/) maps to a PRD section (§5–§42).

| # | Module | Phase |
|---|--------|-------|
| 05 | [Dashboard](./modules/05-dashboard.md) | 1 |
| 06 | [Case/Matter Management](./modules/06-case-matter-management.md) | 1 |
| 07 | [Client Intake Forms](./modules/07-client-intake-forms.md) | 2 |
| 08 | [Conflict Check](./modules/08-conflict-check.md) | 2 |
| 09 | [Document Management](./modules/09-document-management.md) | 2 / 4 |
| 10 | [Brief Writing](./modules/10-brief-writing.md) | 5 |
| 11 | [Motion Writing](./modules/11-motion-writing.md) | 5 |
| 12 | [Legal Research](./modules/12-legal-research.md) | 4 / 5 |
| 13 | [Court Forms](./modules/13-court-forms.md) | 5 |
| 14 | [Filing Tracker](./modules/14-filing-tracker.md) | 5 |
| 15 | [CRM](./modules/15-crm.md) | 1 / 3 |
| 16 | [Communication Center](./modules/16-communication-center.md) | 3 |
| 17 | [Client Portal](./modules/17-client-portal.md) | 3 |
| 18 | [Appointments](./modules/18-appointments.md) | 3 |
| 19 | [Court Calendar](./modules/19-court-calendar.md) | 2 |
| 20 | [Task Management](./modules/20-task-management.md) | 2 |
| 21 | [Legal Project Management](./modules/21-legal-project-management.md) | 5 |
| 22 | [Case Notes](./modules/22-case-notes.md) | 2 |
| 23 | [Evidence Management](./modules/23-evidence-management.md) | 5 |
| 24 | [E-Discovery](./modules/24-e-discovery.md) | 5 |
| 25 | [Billing & Payments](./modules/25-billing-payments.md) | 3 |
| 26 | [Time Tracking](./modules/26-time-tracking.md) | 3 |
| 27 | [Approval Workflows](./modules/27-approval-workflows.md) | 4 |
| 28 | [E-Signature](./modules/28-e-signature.md) | 4 |
| 29 | [Knowledge Management](./modules/29-knowledge-management.md) | 5 |
| 30 | [Training & CLE](./modules/30-training-cle.md) | 5 |
| 31 | [AI Analytics](./modules/31-ai-analytics.md) | 5 |
| 32 | [Reporting](./modules/32-reporting.md) | 3 |
| 33 | [Global Search](./modules/33-global-search.md) | Cross-cutting (MVP) |
| 34 | [AI Chatbot](./modules/34-ai-chatbot.md) | 4 |
| 35 | [AI Governance](./modules/35-ai-governance.md) | 4 |
| 36 | [Security & Compliance](./modules/36-security-compliance.md) | Cross-cutting |
| 37 | [Audit Trail](./modules/37-audit-trail.md) | Cross-cutting (MVP) |
| 38 | [Admin Settings](./modules/38-admin-settings.md) | 1 |
| 39 | [Onboarding](./modules/39-onboarding.md) | Cross-cutting |
| 40 | [Mobile & Responsive](./modules/40-mobile-responsive.md) | Cross-cutting |
| 41 | [Notifications](./modules/41-notifications.md) | 2 |
| 42 | [Integrations](./modules/42-integrations.md) | Cross-cutting |

---

## Design system

Visual direction, tokens, and component guidelines for Vue 3 + Tailwind CSS v4. UI patterns are derived from [`UI reference/`](../UI%20reference/) at the repo root.

| Document | Description |
|----------|-------------|
| [03-design-system/README.md](./03-design-system/README.md) | Overview, quick start, doc index |
| [01-principles.md](./03-design-system/01-principles.md) | Flat aesthetic, no shadows, accessibility |
| [02-colors.md](./03-design-system/02-colors.md) | Brand palette and semantic color tokens |
| [03-typography.md](./03-design-system/03-typography.md) | Type scale and hierarchy |
| [04-spacing-and-layout.md](./03-design-system/04-spacing-and-layout.md) | Grid, spacing, radius, borders |
| [05-components.md](./03-design-system/05-components.md) | Buttons, cards, sidebar, tables, forms, badges |
| [06-tailwind-vue.md](./03-design-system/06-tailwind-vue.md) | Tailwind v4 and Vue integration |
| [ui-reference-summary.md](./03-design-system/ui-reference-summary.md) | Patterns from reference mockups |
| [tokens.css](./03-design-system/tokens.css) | CSS custom properties (copy to frontend when ready) |

---

## Tech stack

| Document | Description |
|----------|-------------|
| [tech-stack.md](./02-tech-stack/tech-stack.md) | Vue, Laravel, MySQL, OpenSearch, AI service, queues, documents, payments |

---

## MVP vs phases note

The [MVP scope](./01-planning/mvp-scope.md) bundles items from Phases 1–4 (including basic AI). Use **phases** for build order and **MVP** for the first production release checklist. See [implementation-roadmap.md](./01-planning/implementation-roadmap.md) for reconciliation.
