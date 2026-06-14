# Design Principles

Banwolaw Hub should feel like **confident, modern legal software** — calm enough for long work sessions, precise enough for billing and deadlines, and trustworthy for confidential matter data.

---

## Core principles

### 1. Flat and intentional

- **No box shadows** on cards, buttons, dropdowns, modals, or sidebar.
- Create hierarchy with **surface color**, **1px borders**, and **spacing**.
- Elevation is expressed as: `background` → `border` → `accent stripe` → `focus ring` — never blur/shadow stacks.

### 2. High fidelity from reference

- Follow layout patterns in [ui-reference-summary.md](./ui-reference-summary.md): sidebar shell, dashboard grids, kanban intake, billing tables, matter tabs.
- Match **information density** of the reference — do not oversimplify KPI cards or strip table columns without product reason.
- Recolor and rebrand; do not copy third-party logos or names.

### 3. Brand-aligned

- **Primary:** Banwolaw deep teal (`#053742`, `#083944`, `#062702`).
- **Accent:** Gold family (`#B1915A`, `#B99D6A`, `#FAD28C`) for secondary CTAs, highlights, and warm emphasis.
- Neutrals from the official palette only — see [02-colors.md](./02-colors.md).

### 4. Lawyer-first clarity

- One primary action per screen region (e.g. `Start Timer`, `+ New Matter`, `Create Invoice`).
- Status must be scannable: badges, dots, and pipeline bars — not icon-only ambiguity.
- Destructive actions require explicit labeling and confirmation.

### 5. Case-centric context

- Breadcrumbs and matter headers on detail views.
- Global actions remain available; **context** (current matter/client) visible when relevant.

### 6. Accessible and consistent

- WCAG 2.1 AA contrast for text and interactive elements.
- Visible focus rings (primary or accent outline, 2px).
- Do not rely on color alone for status — pair with label text or icon.

---

## Do / Don't

| Do | Don't |
|----|--------|
| Use `border border-border` on cards and inputs | Use `shadow-sm`, `shadow-md`, or Material-style elevation |
| Use `--color-surface` vs `--color-canvas` for depth | Stack multiple shadow layers |
| Use gold accent sparingly for emphasis | Use gold for large background fields |
| Keep sidebar white with border-right | Dark sidebar unless explicitly requested later |
| Use semantic tokens (`text-muted`, `bg-primary`) | Hard-code hex in components |
| Tabular numbers for currency and hours | Proportional figures in financial tables |

---

## Motion

- Subtle transitions only: **150–200ms** for hover, focus, panel open.
- Prefer opacity and border-color changes over scale/shadow animation.
- Respect `prefers-reduced-motion`.

---

## Dark mode

**Phase 1:** Light mode only (matches reference and Banwolaw public site).

**Future:** Token structure in [tokens.css](./tokens.css) reserves `.dark` overrides; implement when product requires it. Primary teal lightens slightly on dark surfaces; gold accent unchanged.
