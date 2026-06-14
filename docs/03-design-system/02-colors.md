# Color System

All colors derive from the **Banwolaw public website** palette. Use **semantic tokens** in code, not raw hex in components.

Source file: [tokens.css](./tokens.css)

---

## Brand primitives

### Primary (teal)

| Token | Hex | Usage |
|-------|-----|--------|
| `primary-950` | `#021419` | Deepest text on gold tint |
| `primary-900` | `#062702` | Dark footer, strong emphasis |
| `primary-800` | `#053742` | **Primary brand**, primary buttons, active nav text |
| `primary-700` | `#083944` | Hover on primary, chart series |
| `primary-600` | `#0a4f5e` | Interactive hover |
| `primary-500` | `#0d6678` | Links, icons |
| `primary-400` | `#1a8499` | Chart secondary |
| `primary-300` | `#4daaba` | Disabled primary text |
| `primary-200` | `#8ec9d4` | Borders on primary tint |
| `primary-100` | `#c5e3ea` | Light fills |
| `primary-50` | `#e8f3f6` | Active nav background, selected row |

### Accent (gold)

| Token | Hex | Usage |
|-------|-----|--------|
| `accent-700` | `#8a7348` | Gold text on light bg |
| `accent-600` | `#9a8252` | Hover on gold buttons |
| `accent-500` | `#B1915A` | **Primary gold**, secondary CTAs, highlights |
| `accent-400` | `#B99D6A` | Gold hover, chart accent |
| `accent-300` | `#FAD28C` | Soft highlight, input border accent, badges |
| `accent-200` | `#fce5b8` | Gold tint background |
| `accent-100` | `#fdf4e3` | Subtle gold wash |
| `accent-50` | `#fefbf5` | Page accent sections |

### Neutrals (official)

| Token | Hex | Usage |
|-------|-----|--------|
| `neutral-950` | `#000000` | Primary headings (sparingly) |
| `neutral-900` | `#333333` | Body text, titles |
| `neutral-800` | `#39374A` | Strong secondary text |
| `neutral-700` | `#3F444B` | Labels |
| `neutral-600` | `#4D525A` | Muted UI chrome |
| `neutral-500` | `#5A5A5A` | Placeholder, captions |
| `neutral-400` | `#CCCCCC` | Borders (strong) |
| `neutral-300` | `#E6E9EC` | **Default border**, dividers |
| `neutral-200` | `#F6F6F6` | **Canvas** background |
| `neutral-100` | `#FAFAFA` | Subtle surface alt |
| `neutral-50` | `#FFFFFF` | **Surface** (cards, sidebar) |

---

## Semantic tokens

Map primitives to UI roles:

| Semantic | Light mode value | Role |
|----------|------------------|------|
| `--background` | `#F6F6F6` | App canvas |
| `--foreground` | `#333333` | Default text |
| `--surface` | `#FFFFFF` | Cards, sidebar, modals |
| `--surface-muted` | `#FAFAFA` | Table header, secondary panels |
| `--border` | `#E6E9EC` | Default 1px borders |
| `--border-strong` | `#CCCCCC` | Emphasized dividers |
| `--primary` | `#053742` | Primary actions, brand |
| `--primary-foreground` | `#FFFFFF` | Text on primary |
| `--primary-muted` | `#e8f3f6` | Active nav, selected states |
| `--secondary` | `#B1915A` | Secondary brand / accent CTA |
| `--secondary-foreground` | `#FFFFFF` | Text on gold buttons |
| `--secondary-muted` | `#fdf4e3` | Gold tint surfaces |
| `--accent` | `#FAD28C` | Highlights, focus accents |
| `--muted` | `#5A5A5A` | Secondary text |
| `--muted-foreground` | `#4D525A` | Labels, help text |
| `--ring` | `#083944` | Focus ring |

---

## Status colors

Functional colors for legal workflows (kanban, badges, alerts). Not part of brand marketing palette but required for clarity.

| Semantic | Background | Foreground | Border | Use |
|----------|------------|------------|--------|-----|
| **Success** | `#ecfdf5` | `#047857` | `#a7f3d0` | Paid, Qualified, Active |
| **Warning** | `#fffbeb` | `#b45309` | `#FAD28C` | Pending, Reviewing, due soon |
| **Danger** | `#fef2f2` | `#b91c1c` | `#fecaca` | Overdue, Rejected, Depleted |
| **Info** | `#e8f3f6` | `#083944` | `#8ec9d4` | Sent, informational |
| **Neutral** | `#F6F6F6` | `#4D525A` | `#E6E9EC` | Draft, archived |

### Pipeline / kanban (intake reference)

| Stage | Dot / bar | Token |
|-------|-----------|-------|
| New Leads | Primary teal | `--primary` |
| Reviewing | Warning amber | `--status-warning-fg` |
| Rejected | Danger red | `--status-danger-fg` |
| Qualified | Success green | `--status-success-fg` |

---

## Chart palette

Use in order for multi-series charts (billing trend, practice areas):

1. `#053742` (primary-800)
2. `#B1915A` (accent-500)
3. `#083944` (primary-700)
4. `#B99D6A` (accent-400)
5. `#0d6678` (primary-500)
6. `#FAD28C` (accent-300)

Area fills: 15–25% opacity of stroke color — **no shadow** under chart areas.

---

## Contrast notes

- `#053742` on `#FFFFFF` — passes AA for normal text.
- `#B1915A` on `#FFFFFF` — use for large text / icons; for small text prefer `#8a7348` (accent-700).
- `#5A5A5A` on `#F6F6F6` — body secondary; verify for 12px captions.

---

## Usage examples

```css
/* Card on canvas */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  /* NO box-shadow */
}

/* Active sidebar item */
.nav-item-active {
  background: var(--primary-muted);
  color: var(--primary);
  border-right: 2px solid var(--primary);
}

/* Gold secondary button */
.btn-secondary {
  background: var(--secondary);
  color: var(--secondary-foreground);
  border: 1px solid var(--accent-600);
}
```
