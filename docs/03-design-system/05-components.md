# Component Guidelines

Component specs aligned with UI reference screens and Banwolaw tokens. All components are **flat** (no shadow).

---

## Buttons

### Variants

| Variant | Background | Border | Text | Use |
|---------|------------|--------|------|-----|
| **Primary** | `primary` | `primary` | white | Main action: Save, Start Timer |
| **Secondary (gold)** | `secondary` | `accent-600` | white | Warm CTA: highlights, onboarding |
| **Outline** | transparent | `border` | `foreground` | + New Matter, Create Invoice |
| **Ghost** | transparent | none | `foreground` | Toolbar icons, tertiary |
| **Destructive** | `status-danger-fg` | same | white | Delete, reject |

### Sizes

| Size | Height | Padding | Radius |
|------|--------|---------|--------|
| sm | 32px | `px-3` | `rounded-md` |
| default | 40px | `px-4` | `rounded-md` |
| lg | 44px | `px-6` | `rounded-md` |
| pill | 44px | `px-6` | `rounded-full` |

### Rules

- Icon + label: gap `space-2`; icon 16–20px.
- One filled primary per toolbar group; others outline/ghost.
- Focus: `ring-2 ring-ring ring-offset-2 ring-offset-background`.
- Disabled: `opacity-50`, no shadow (never use shadow for disabled state).

### Reference mapping

- `Start Timer` → **Primary** (teal)
- `+ New Matter` / `+ Create Invoice` → **Outline**
- Mobile `Create account` → **Primary pill** (reference uses gold — use teal for Hub auth primary; gold for secondary marketing CTAs if needed)

---

## Cards

### Structure

```html
<!-- Conceptual -->
<article class="rounded-lg border border-border bg-surface p-6">
  <header><!-- title + optional action --></header>
  <div><!-- content --></div>
</article>
```

### Variants

| Variant | Use |
|---------|-----|
| **Stat** | KPI number, delta, sparkline/bar |
| **Chart** | Full-width area/line with legend + dropdown |
| **List item** | Recent matter horizontal card in grid |
| **Kanban** | Lead card inside column |

### Stat card anatomy

1. Label (`text-sm text-muted`)
2. Value (`text-3xl font-bold tabular-nums`)
3. Delta pill (success/danger background tint)
4. Optional mini chart (bottom)

**No** `shadow-sm` — border only.

---

## Sidebar

### Anatomy (top → bottom)

1. **Logo block** — Banwolaw mark + “Hub” or product name, `p-4`, `border-b`
2. **Primary nav** — Dashboard, Intake (badge), Matters, …
3. **Section label** — “FINANCE” (`text-xs uppercase tracking-wider text-muted`, `px-3 py-2`)
4. **Secondary nav** — Billing, Report, Calendar
5. **Spacer** (`flex-1`)
6. **Utility** — Settings, Help & Support
7. **User card** — avatar, name, role, chevron; `border-t`, `p-3`

### Nav item states

| State | Style |
|-------|--------|
| Default | `text-muted-foreground`, icon + label |
| Hover | `bg-neutral-100` |
| Active | `bg-primary-muted text-primary font-medium` + optional `border-r-2 border-primary` |
| Badge | Circular count on Intake — `bg-primary text-primary-foreground text-xs min-w-5 h-5 rounded-full` |

Width: `w-64`. Background: `bg-surface`. Right edge: `border-r border-border`.

---

## Tables

Billing reference (`a3dd5953…png`).

### Container

- Wrap in `rounded-lg border border-border bg-surface overflow-hidden`
- Sticky header: `bg-surface-muted`, `border-b`

### Columns

- Checkbox column: 40px
- Primary column (Client): name `font-medium` + subtext `text-xs text-muted`
- Amount: right-aligned, `tabular-nums`
- Status: badge component
- Actions: text links `text-primary hover:underline`, not shadow buttons

### Row

- `border-b border-border last:border-0`
- Hover: `bg-neutral-100`
- Selected: `bg-primary-muted`

### Footer toolbar

- Left: Select All checkbox
- Right: outline buttons — Send Selected, Send Reminders, Record Payment

### Tabs above table

- Invoices | Payment — underline or pill tabs, active `border-b-2 border-primary` or filled pill on white segment control

---

## Forms

### Inputs

- Height 40px (default), `rounded-md`
- Border `border-border`; focus `border-primary ring-2 ring-primary/20`
- Label above field: `text-sm font-medium mb-1.5`
- Helper text: `text-xs text-muted mt-1`
- Error: `border-status-danger-fg`, message in danger color

### Select / combobox

- Match input height and border
- Chevron icon right; no shadow on dropdown panel — `border border-border rounded-md bg-surface`

### Search

- Icon left inside field; `pl-9`
- Placeholder: “Search Invoice”, “Search matters…”

### Filters

- Dropdown triggers: outline button style, same height as search

---

## Badges

Pill shape, `rounded-full`, `text-xs font-medium`, `px-2.5 py-0.5`.

| Variant | Background | Text |
|---------|------------|------|
| Success / Active / Paid | `status-success-bg` | `status-success-fg` |
| Warning / Pending | `status-warning-bg` | `status-warning-fg` |
| Danger / Overdue | `status-danger-bg` | `status-danger-fg` |
| Info / Sent | `status-info-bg` | `status-info-fg` |
| Neutral / Draft | `neutral-200` | `neutral-600` |
| Accent / AI | `accent-100` | `accent-700` |

Optional **dot** prefix for kanban column headers (8px circle).

Notification count badge: solid `primary`, white text, min width 20px.

---

## Tabs (matter detail)

Horizontal list below entity header.

- Inactive: `text-muted`, no background
- Active: `bg-surface text-primary font-medium` pill or bottom border
- Container on gray canvas — segment control with `bg-neutral-200 p-1 rounded-lg` optional

Tabs from reference: Overview, Details, Documents, …

---

## Breadcrumbs

`text-sm text-muted`, separator `/` or chevron.

Example: `Matters › CBCA v Chateau Beach LLC – 4268-0001`

Last segment: `text-foreground font-medium`.

---

## Kanban

See intake reference.

- **Column:** `rounded-xl border bg-surface-muted p-3 min-w-[280px]`
- **Card:** `rounded-lg border bg-surface p-4 space-y-3`, drag handle optional
- **Column header:** dot + title + dashed count circle + value + add button

---

## Modals / dialogs

- Overlay: `bg-neutral-950/40` (no blur required)
- Panel: `bg-surface border border-border rounded-lg`, max-width per context
- **No shadow** on panel
- Actions: primary right, cancel outline/ghost left

---

## Charts

- Flat grid lines only if needed: `stroke-border`
- Tooltips: bordered surface card, not floating shadow box
- Colors: see [02-colors.md](./02-colors.md) chart palette

---

## Empty states

- Centered icon 48px muted
- Title `text-lg font-semibold`
- Description `text-sm text-muted`
- Single primary action button

---

## shadcn/ui components to customize first

When initializing shadcn, override these for Banwolaw:

1. `button` — remove shadow utilities from variants
2. `card` — remove `shadow-sm` from default
3. `input`, `select`, `textarea` — border-only focus
4. `badge` — map to status tokens
5. `sidebar` — match nav anatomy above
6. `table` — billing-style dense mode
7. `tabs` — matter detail pattern
8. `dialog`, `sheet`, `dropdown-menu` — border, no shadow

Global CSS override:

```css
/* After shadcn theme */
.shadow, .shadow-sm, .shadow-md, .shadow-lg {
  box-shadow: none !important;
}
```

Prefer removing shadow classes at source over `!important` when possible.
