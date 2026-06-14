# Phase 1: Foundation

**Goal:** Single-organization platform shell with authenticated users, Banwolaw configuration, client and case records, and role-aware dashboards.

**Depends on:** Tech stack setup ([tech stack](../02-tech-stack/tech-stack.md))

**Unlocks:** Phase 2 (all case-linked operations)

### Implementation status

| Area | Status |
|------|--------|
| Backend API (auth, org, clients, cases, dashboard, RBAC) | Done |
| Users API (`/api/v1/users`, roles, deactivate) | Done |
| Vue UI (login, dashboard, shell, permission-aware nav) | Done |
| Vue UI (clients, cases, organization settings, team invite) | Done |

**Phase 1 complete?** Yes — ready for Phase 2 (intake, documents, tasks, calendar, etc.).

**Delivered:** Client/case CRUD pages with timeline on case detail, `PUT /organization` in Settings, Team admin at `/settings/users`, routes `/clients/:id` and `/cases/:id`, dashboard role-specific subtitle.

---

## Modules in this phase

| Module | Spec | Build focus |
|--------|------|-------------|
| Authentication & user roles | [Roles](../00-foundation/03-roles-and-permissions.md) | Login, Sanctum/Passport, Spatie Permission |
| Firm setup | [Admin settings](../modules/38-admin-settings.md) | Banwolaw profile, practice areas, case types |
| User management | [Admin settings](../modules/38-admin-settings.md) | Invite staff, assign roles |
| Dashboard | [05-dashboard](../modules/05-dashboard.md) | Role-specific widgets (static → live data) |
| Client management | [15-crm](../modules/15-crm.md) | Client CRUD, profile, link to cases |
| Case/matter management | [06-case-matter-management](../modules/06-case-matter-management.md) | Case CRUD, status, assignment, timeline shell |

---

## Deliverables

### Backend (Laravel)

- [ ] Single-organization model (`Firm` or `Organization` seeded for Banwolaw only)
- [ ] Users, roles, permissions (System Admin, Firm Admin, Partner, Lawyer, Paralegal, Secretary, Client, Consultant)
- [ ] Auth API: login, logout, password reset, session management (no public firm signup)
- [ ] Organization settings API: profile, practice areas, case types, jurisdictions (minimal)
- [ ] Client API: CRUD, search, pagination
- [ ] Case API: CRUD, assignment, status transitions, case timeline events
- [ ] Activity log hooks for case/client create/update (Spatie Activity Log)

### Frontend (Vue 3 + Vite)

Apply [design system](../03-design-system/README.md): Banwolaw tokens, flat UI (no shadows), sidebar + canvas shell per [UI reference summary](../03-design-system/ui-reference-summary.md).

- [ ] Auth flows (login, forgot password)
- [ ] App shell: sidebar, header
- [ ] Firm admin: settings, user list, invite user
- [ ] Client list and client detail pages
- [ ] Case list, case detail (tabs shell: overview, parties, activity)
- [ ] Dashboards per role (Firm Admin, Lawyer — Client dashboard stub OK)

### Database (initial entities)

From [data model](../01-planning/data-model.md): `User`, `Firm` (Banwolaw only), `Role`, `Permission`, `Client`, `Case/Matter`, `Party`

---

## Acceptance criteria (Definition of Done)

1. Banwolaw organization is seeded; System Admin can manage system settings; Firm Admin can manage organization profile.
2. Firm Admin can add users and assign roles; users only see permitted actions.
3. Firm Admin can create clients and cases with required fields and statuses.
4. Cases show assigned lawyers/staff and an activity timeline (creation, status change).
5. Dashboards show real counts: active cases, assigned cases, recent clients.
6. All API routes enforce RBAC and organization scope (Banwolaw only).
7. Responsive layout works on tablet (per [mobile](../modules/40-mobile-responsive.md) baseline).

---

## Out of scope (later phases)

- Intake forms, conflict check, documents, billing, portal, AI
- Full CRM communication history, invoices, appointments
- Global search indexing (stub OK), audit UI (logging only)

---

## Suggested implementation order

1. Project scaffold (Laravel API + Vue app)
2. Auth + RBAC + organization context middleware
3. Organization settings + user management
4. Client module
5. Case module + timeline
6. Dashboards
7. Initial setup wizard steps 1–6 only ([onboarding](../modules/39-onboarding.md))

---

## Related docs

- [Key workflow: New client → case](../01-planning/key-workflows.md) — steps 5–7 partial in Phase 1
- [Security](../modules/36-security-compliance.md) — implement auth, RBAC, session timeout in this phase
