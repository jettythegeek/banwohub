# Typography

Banwolaw Hub uses a **single sans-serif family** with clear weight steps — matching the clean, geometric tone of the UI reference and Banwolaw public site.

---

## Font stack

**Primary (recommended):** [Inter](https://rsms.me/inter/) — excellent tabular figures, wide language support, shadcn default.

**Alternatives (if brand team specifies):** DM Sans, Plus Jakarta Sans, or Euclid Circular B (see secondary reference in UI folder).

```css
--font-sans: "Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
--font-mono: ui-monospace, "SF Mono", "Cascadia Code", monospace;
```

Load via `next/font/google`:

```tsx
import { Inter } from "next/font/google";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-sans",
  display: "swap",
});
```

---

## Type scale

Base size: **16px** (`1rem`). Scale ratio ~1.25 (major third).

| Token | Size | Line height | Weight | Usage |
|-------|------|-------------|--------|--------|
| `text-xs` | 12px / 0.75rem | 1.33 | 400–500 | Captions, table meta, badge text |
| `text-sm` | 14px / 0.875rem | 1.43 | 400–500 | Labels, secondary body, nav items |
| `text-base` | 16px / 1rem | 1.5 | 400 | Default body |
| `text-lg` | 18px / 1.125rem | 1.44 | 500–600 | Card titles, section headers |
| `text-xl` | 20px / 1.25rem | 1.4 | 600 | Sub-page titles |
| `text-2xl` | 24px / 1.5rem | 1.33 | 600–700 | Page titles (Dashboard, Billing) |
| `text-3xl` | 30px / 1.875rem | 1.25 | 700 | Hero metrics (optional) |
| `text-4xl` | 36px / 2.25rem | 1.2 | 700 | KPI numbers in stat cards |

---

## Weight usage

| Weight | Token | Use |
|--------|-------|-----|
| 400 | `font-normal` | Body, descriptions |
| 500 | `font-medium` | Labels, nav, table headers |
| 600 | `font-semibold` | Card titles, buttons, tab active |
| 700 | `font-bold` | Page titles, large metrics |

Avoid 300 (light) for UI chrome — insufficient contrast on gray canvas.

---

## Numeric and financial data

- Enable tabular figures: `font-variant-numeric: tabular-nums` (Tailwind: `tabular-nums`).
- Currency: `$1,430.44` — locale-aware formatting in app logic; display with consistent decimal alignment in tables.
- Hours: `142.5h` — suffix in muted smaller text if needed.

---

## Hierarchy patterns (from reference)

### Page header

```
Page title          text-2xl font-semibold text-foreground
Breadcrumb          text-sm text-muted
```

### Stat card

```
Metric value        text-3xl or text-4xl font-bold tabular-nums
Metric label        text-sm font-medium text-muted
Delta badge         text-xs font-medium (+ success / − danger)
```

### Table

```
Column header       text-xs font-medium uppercase tracking-wide text-muted
Primary cell        text-sm font-medium text-foreground
Subtext (matter)    text-xs text-muted
```

### Sidebar

```
Section label       text-xs font-semibold uppercase tracking-wider text-muted
Nav item            text-sm font-medium
User name           text-sm font-semibold
User role           text-xs text-muted
```

---

## Letter spacing

- Uppercase labels (FINANCE, table headers): `tracking-wide` (0.025em) or `tracking-wider` (0.05em).
- Default body: normal tracking.

---

## Line length

- Prose / case summary: max **65ch** for readability.
- Dashboard cards: no artificial line clamp on critical legal summaries unless in compact list view.

---

## shadcn/ui mapping

In `components/ui/*`, use semantic classes:

- `text-foreground`, `text-muted-foreground`
- `text-primary`, `text-destructive`
- Headings: compose with scale tokens above, not ad-hoc pixel sizes
