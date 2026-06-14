# Banwolaw Hub — Design System

High-fidelity design direction for Banwolaw Hub, a modern legal practice management application. Tokens and guidelines align with the **Banwolaw brand palette**, the **UI reference** folder at the repo root, and the stack in [tech-stack.md](../02-tech-stack/tech-stack.md) (Vue 3, Tailwind CSS v4).

> **Visual reference:** [`UI reference/`](../../UI%20reference/) — legal practice management mockups (CaseIQ-style dashboard, intake kanban, billing, matter detail) plus supplementary layout inspiration.

---

## Design direction (summary)

| Principle | Direction |
|-----------|-----------|
| **Aesthetic** | Flat, modern, professional — suitable for confidential legal work |
| **Depth** | **No box shadows.** Use borders, surface contrast, and spacing instead |
| **Brand** | Deep teal primary (`#053742` family), gold accent (`#B1915A` family), neutral grays |
| **Layout** | Persistent left sidebar + main content; card-based dashboards; tabbed detail views |
| **Fidelity** | Match reference density, hierarchy, and component patterns — recolored for Banwolaw |

---

## Documentation index

| Document | Contents |
|----------|----------|
| [01-principles.md](./01-principles.md) | Design principles, do/don't, accessibility |
| [02-colors.md](./02-colors.md) | Semantic color tokens, scales, status colors |
| [03-typography.md](./03-typography.md) | Type scale, weights, usage |
| [04-spacing-and-layout.md](./04-spacing-and-layout.md) | Grid, spacing, radius, borders, breakpoints |
| [05-components.md](./05-components.md) | Buttons, cards, tables, sidebar, forms, badges |
| [06-tailwind-vue.md](./06-tailwind-vue.md) | Tailwind v4 + Vue integration |
| [ui-reference-summary.md](./ui-reference-summary.md) | Patterns extracted from reference images |
| [tokens.css](./tokens.css) | CSS custom properties (copy to frontend when scaffold exists) |

---

## Quick start for developers

1. Read [ui-reference-summary.md](./ui-reference-summary.md) for layout and screen patterns.
2. Import [tokens.css](./tokens.css) in the Vue app (`src/styles/main.css`).
3. Follow [06-tailwind-vue.md](./06-tailwind-vue.md) to wire Tailwind theme variables.
4. Apply [05-components.md](./05-components.md) when building each UI surface.
5. **Disable shadows globally** — see principles and Tailwind config (`boxShadow: none` or `--shadow-*: none`).

---

## Related docs

- [Product goals](../00-foundation/02-goals-and-principles.md)
- [Dashboard module](../modules/05-dashboard.md)
- [Mobile & responsive](../modules/40-mobile-responsive.md)
- [Non-functional requirements](../01-planning/non-functional-requirements.md) (accessibility)
