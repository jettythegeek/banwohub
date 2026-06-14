# UI Reference Summary

**Location:** [`UI reference/`](../../UI%20reference/) (repo root)

The folder contains **~60 image assets** (PNG, WebP, GIF, JPG) — primarily high-fidelity **legal practice management** mockups branded as “CaseIQ,” plus supplementary mobile and brand-layout references.

Use these as **structural and compositional** references. Recolor purple accents to Banwolaw teal/gold and **remove all drop shadows** in implementation.

---

## Primary reference screens (legal PMS)

### Dashboard (`02619a87…png`, `148aaf3b…png`)

**Layout**

- Fixed **left sidebar** (~240–280px), white surface, right border
- **Main canvas** on light gray (`#F6F6F6` / `#FAFAFA`)
- **Page header:** title left, actions right (`+ New Matter`, `Start Timer`, grid, notifications)

**Components**

- **KPI stat cards** — large metric, delta badge (+/- %), mini chart or progress pills
- **Wide chart card** — area/line chart with legend and period dropdown
- **Donut / gauge** — invoice status breakdown (Paid / Pending / Overdue)
- **Recent matters row** — horizontal cards: avatar, matter name, status badge, task count, metadata

**Patterns**

- Card grid: 2–3 columns on desktop; full-width list sections below
- Section titles inside cards (e.g. “Practice Areas”, “Your recent matters”)
- Notification badge on sidebar “Intake” item

---

### Intake / Kanban (`148aaf3b…png`)

**Layout**

- Same shell as dashboard
- **Work Status** summary bar — segmented pipeline (New → Reviewing → Rejected → Qualified) with counts
- **View toggle:** Kanban | Lists
- **Toolbar:** search, status filter, time filter

**Kanban columns**

- Column header: colored dot, title, count in dashed circle, total value, `+` add
- **Lead cards:** avatar/initials, name, date/email/phone with icons, dollar amount with icon
- Optional note indicator on cards

**Patterns**

- Status encoded by **color dot + column**, not shadow
- Cards: white fill, 1px border, 12–16px radius

---

### Billing (`a3dd5953…png`)

**Layout**

- Three **summary cards** top row: Outstanding Balance, Billable Hours, Revenue at Risk
- **Tab strip:** Invoices | Payment
- **Data table** with bulk actions footer

**Table columns**

- Checkbox, INV#, Client (name + matter subtext), Model, Due Date, Amount, Status badge, Action links

**Summary card patterns**

- Aging breakdown horizontal bar (multi-segment)
- Vertical bar chart by category
- “AI-Prioritized” badge + priority action list

**Actions**

- `+ Create Invoice` (outline), `Start Timer` (filled primary)
- Row actions: View Invoice, Send Reminder, View Details
- Bulk: Select All, Send Selected, Send Reminders, Record Payment

---

### Matter detail / Overview (`e1c040d5…png`)

**Layout**

- **Breadcrumbs:** Matters › [Case name — ID]
- **Entity header:** case title, status badge (Active), Edit button
- **Horizontal tabs:** Overview, Details, Documents, …
- **Metric grid** + wide chart + side widgets

**Widgets**

- Unbilled Revenue, Trust Balance (with “Depleted” danger badge), Next Deadline (circular progress)
- Billing Trend multi-line chart
- Case Value Breakdown — labeled horizontal progress bars
- Case Summary — tags + narrative text + “View Details”
- Mini calendar widget

**Patterns**

- Tab bar: pill/active tab on white; inactive tabs text-only on gray
- Tags/chips for case attributes (“Strong Case”, “$15k–25k”)
- Mix of **small KPI cards** and **full-width analytics**

---

## Secondary references

| Asset | Notes |
|-------|--------|
| `5a41d324…png` | Brand board: typography pairing (geometric sans), card radii, list-with-avatar pattern, progress bars |
| `temp_Screenshot…jpg` | Mobile form: rounded inputs, gold/tan borders, pill primary button, step indicator |
| WebP / GIF files | Additional motion and component variants — same flat card/sidebar vocabulary |

---

## Typography hints (from reference)

- **Sans-serif throughout** — geometric, neutral (Inter, DM Sans, or similar)
- **Hierarchy:** page title 24–32px bold; card titles 16–18px semibold; metrics 28–36px bold; labels 12–14px medium; metadata 12px regular muted
- **Numeric data:** tabular figures for currency and hours where possible

---

## Layout rules to preserve

1. **Sidebar always visible** on desktop; collapsible on tablet/mobile
2. **White chrome** (sidebar, cards, modals) on **gray canvas**
3. **Generous padding:** 24px inside cards; 16–24px between grid items
4. **Rounded corners:** 12px cards, 8px buttons/inputs, full pill for badges and primary CTAs
5. **Depth via border** `#E6E9EC` / `#CCCCCC`, not shadow
6. **Gold accent** for secondary emphasis; **teal** for primary actions and active nav
7. **Status colors** consistent across kanban, badges, and charts

---

## Mapping reference → Banwolaw Hub

| Reference (CaseIQ) | Banwolaw Hub |
|--------------------|--------------|
| Purple primary | `--color-primary` (`#053742`, `#083944`) |
| Purple active nav | `--color-primary-muted` background + primary text |
| Purple charts | Primary teal + gold accent series |
| Soft shadows | **Removed** — `border: 1px solid var(--color-border)` |
| “CaseIQ” branding | Banwolaw logo + “Banwolaw Hub” product name |
