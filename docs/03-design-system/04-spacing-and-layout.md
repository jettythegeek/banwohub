# Spacing, Layout, Radius & Borders

Layout rules extracted from the UI reference and adapted for a **flat, border-defined** Banwolaw Hub shell.

---

## Spacing scale

4px base grid. Prefer multiples of 4; use 8px rhythm for section gaps.

| Token | Value | Typical use |
|-------|-------|-------------|
| `space-0` | 0 | — |
| `space-1` | 4px | Tight icon gap |
| `space-2` | 8px | Inline gaps, badge padding |
| `space-3` | 12px | Input padding-y, compact lists |
| `space-4` | 16px | Card gap (mobile), form field gap |
| `space-5` | 20px | — |
| `space-6` | 24px | **Card padding**, page padding (mobile) |
| `space-8` | 32px | Section gaps |
| `space-10` | 40px | Large section breaks |
| `space-12` | 48px | Page top padding (desktop) |

---

## App shell layout

```
┌─────────────────────────────────────────────────────────┐
│ Sidebar (fixed)  │  Main (scroll)                        │
│ 240–280px        │  flex-1, bg-canvas                    │
│ bg-surface       │  padding: 24–32px                     │
│ border-r         │                                       │
└─────────────────────────────────────────────────────────┘
```

| Region | Width / behavior |
|--------|------------------|
| Sidebar | `w-64` (256px) desktop; drawer overlay `< lg` |
| Main content max | `max-w-[1600px]` optional centering on ultra-wide |
| Page header height | ~56–64px including title row |
| Card grid gap | `gap-4` (16px) or `gap-6` (24px) |

### Breakpoints (Tailwind defaults)

| Breakpoint | Layout |
|------------|--------|
| `< md` | Single column; sidebar hidden / hamburger |
| `md–lg` | 2-column card grid |
| `≥ lg` | Full sidebar + 2–3 column dashboard grid |
| `≥ xl` | Matter detail: 3–4 column metric grid |

---

## Border radius

Reference uses **soft, modern corners** — consistent across components.

| Token | Value | Use |
|-------|-------|-----|
| `radius-sm` | 6px | Chips, small badges |
| `radius-md` | 8px | Buttons, inputs, dropdowns |
| `radius-lg` | 12px | **Cards**, modals |
| `radius-xl` | 16px | Large panels, kanban columns |
| `radius-full` | 9999px | Pills, avatar, notification dots |

**Default card:** `rounded-lg` (12px)

---

## Borders (depth without shadow)

Shadows are **forbidden**. Use borders and surface contrast:

| Element | Border |
|---------|--------|
| Card | `1px solid var(--border)` |
| Sidebar | `border-r border-border` only |
| Input default | `1px solid var(--border)` |
| Input focus | `1px solid var(--primary)` + `ring-2 ring-primary/20` |
| Input accent (intake forms) | optional `border-accent-300` for marketing-style fields |
| Table | row `border-b border-border`; no outer shadow |
| Divider | `border-border` or `bg-border` 1px |
| Active nav | `border-r-2 border-primary` optional |

### Hover / active surfaces

- List row hover: `bg-neutral-100` (`#FAFAFA`)
- Selected row: `bg-primary-muted`
- **No** hover shadow on cards

---

## Z-index scale

| Layer | z-index |
|-------|---------|
| Base content | 0 |
| Sticky table header | 10 |
| Sidebar (mobile drawer) | 40 |
| Dropdown / popover | 50 |
| Modal overlay | 50 |
| Toast | 60 |

---

## Grid patterns

### Dashboard

- Row 1: 3 equal KPI cards (`grid-cols-1 md:grid-cols-3`)
- Row 2: 2/3 chart + 1/3 donut (`lg:grid-cols-3`, chart spans 2)
- Row 3: full-width recent matters list

### Matter overview

- Metrics: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`
- Chart + sidebar widgets: `lg:grid-cols-3`

### Kanban

- Horizontal scroll columns: `min-w-[280px]` per column
- Column gap: `gap-4`
- Column surface: `bg-surface-muted` with `border border-border rounded-xl`

---

## Icon sizing

| Context | Size |
|---------|------|
| Nav | 20px (`h-5 w-5`) |
| Inline with text | 16px |
| Empty states | 48px |
| Button icon | 16–20px |

Use **stroke** icons (Lucide — shadcn default), 1.5–2px stroke, `text-muted` default / `text-primary` active.

---

## Density modes (future)

Default: **comfortable** (reference fidelity).

Optional compact mode for billing tables: reduce cell padding from `py-4` to `py-2` — admin setting only.
