# BanwoHub Design System (tiimi layout + BanwoHub brand)

**Version:** 1.1  
**Stack:** Vue 3 + Tailwind CSS v4 + CSS custom properties  
**Layout references:** `UI reference/` (tiimi HR/recruitment mockups)  
**Brand colors:** `docs/03-design-system/tokens.css` (BanwoHub teal + gold)  
**Implementation:** `frontend/src/styles/tokens.css`, `frontend/src/styles/main.css`

This document is the authoritative guide when building or adjusting **any** BanwoHub UI. Re-read it before each page redesign.

---

## 1. Design philosophy

BanwoHub adopts the **tiimi layout** (bright workspace, dark sidebar, card-based clarity) while keeping **BanwoHub brand colors** — deep teal and warm gold. Legal practice data lives in **white cards on a light gray canvas** — never gray/muted backgrounds for card containers.

| Principle | Rule |
|-----------|------|
| Bright workspace | Main area `#F8F9FB`; cards **always** `#FFFFFF` |
| Teal shell | Sidebar `#053742` (BanwoHub brand teal); white top header |
| Accent hierarchy | **BanwoHub gold** = create / active nav; **BanwoHub teal** = save / publish / send |
| Card-first | Every content block is a white card with padding, radius, subtle shadow |
| No inline add forms on list pages | Header **Add** button → modal or dedicated route only |
| Gray only for chrome | `--surface-muted` is for inputs, hover rows, progress tracks — **not** card bodies |

---

## 2. Layout structure

```
┌──────────────┬────────────────────────────────────────────┬──┐
│              │  Top header (white, 64px)                  │  │
│  Dark        │  org · search · [+] · bell · avatar        │U │
│  sidebar     ├────────────────────────────────────────────┤t │
│  256px       │  Light gray workspace (#F8F9FB)           │i │
│              │  ┌──────────────────────────────────────┐ │l │
│  grouped nav │  │ White card(s) with shadow            │ │  │
│  gold pill   │  │ Page header · tabs · content         │ │b │
│  active      │  └──────────────────────────────────────┘ │a │
│              │                                            │r │
└──────────────┴────────────────────────────────────────────┴──┘
```

### 2.1 Sidebar (teal navigation)

- **Width:** 256px (`--sidebar-width`)
- **Background:** `#053742` (`--sidebar-bg` → `--primary-800`; BanwoHub brand teal — not tiimi charcoal `#1A1D23`)
- **Text:** white primary; `rgba(255,255,255,0.55)` muted
- **Brand block:** logo mark + product name at top (64px height)
- **Section headers:** uppercase, 11px, semibold, tracking-wider, muted (e.g. `WORKSPACE`, `PRACTICE`)
- **Nav items:** icon + label, 14px medium, 8px vertical padding
- **Hover:** `rgba(255,255,255,0.08)` background
- **Active state:** **gold rounded pill** (`#B1915A` bg, white text/icon) — full width of nav item, `border-radius: 9999px`. No left accent bar.
- **Footer:** user avatar, name, role, sign-out icon

*Reference screens:* Inbox, Candidates list, Job editor, Employee grid, Career site editor.

### 2.2 Top header (white bar)

- **Height:** 64px; sticky; white background; bottom border `#E6E9EC`
- **Left:** mobile menu toggle (lg hidden); page title optional (tiimi uses minimal title here — page titles live in content)
- **Center/right cluster:**
  - Organization switcher (dropdown with logo + name)
  - Search input (rounded, light gray fill, icon left)
  - **Gold circular + button** (primary create — opens contextual add menu or navigates)
  - Notification bell with red count badge
  - User avatar + dropdown (settings, sign out)

*Reference:* All tiimi screens share this header pattern.

### 2.3 Main workspace

- **Background:** `#F8F9FB` (`--background`)
- **Padding:** 24–32px (`p-6 lg:p-8`)
- **Max width:** 1400px centered
- **Page header:** title (24px semibold), optional subtitle (14px muted), actions right-aligned
- **Content:** one or more `.bw-card` containers

### 2.4 Right utility bar (optional)

Thin ~48px strip with stacked icon buttons (Task, Notes, Folders). Use when a view has auxiliary tools. Color-coded square icons: blue / orange / green.

*Reference:* Inbox, Job editor, Career site editor.

---

## 3. Color palette

### 3.1 Core tokens

| Token | Hex | Usage |
|-------|-----|-------|
| `--sidebar-bg` | `#053742` | Sidebar background (BanwoHub brand teal) |
| `--background` | `#F8F9FB` | Main workspace |
| `--surface` | `#FFFFFF` | **Cards**, header, modals — never substitute gray |
| `--surface-muted` | `#FAFAFA` | Input fills, hover rows, progress tracks only |
| `--border` | `#E6E9EC` | Card borders, dividers |
| `--foreground` | `#1F2937` | Primary text |
| `--muted-foreground` | `#6B7280` | Labels, secondary text |

### 3.2 Brand accent colors (BanwoHub — not tiimi yellow)

| Token | Hex | Usage |
|-------|-----|-------|
| `--accent-gold` / `--accent-500` | `#B1915A` | Add buttons, active nav pill, active tab underline |
| `--accent-gold-hover` / `--accent-600` | `#9A8252` | Gold hover |
| `--accent-gold-fg` | `#FFFFFF` | Text on gold buttons / nav pill |
| `--primary-800` / `--primary` | `#053742` | Brand teal (headings, emphasis) |
| `--action-teal` / `--primary-600` | `#0A4F5E` | Save, Publish, Send, Reply |
| `--action-teal-hover` / `--primary-700` | `#083944` | Teal hover |
| `--action-teal-fg` | `#FFFFFF` | Text on teal |
| `--selection-teal` / `--primary-50` | `#E8F3F6` | Selected list row highlight |

> **Note:** Use `--accent-gold` (`#B1915A`) for brand create/active accents. Do **not** use tiimi `#FFD640`.

### 3.3 Status colors

| Status | Background | Foreground |
|--------|------------|------------|
| Success / Active | `#ECFDF5` | `#047857` |
| Warning / Pending | `#FFFBEB` | `#B45309` |
| Danger / Overdue | `#FEF2F2` | `#B91C1C` |
| Info | `#E8F3F6` | `#083944` |
| Neutral | `#F3F4F6` | `#6B7280` |

### 3.4 Card accent bars (grid cards)

Employee/job grid cards use a **3–4px colored top border** by category:
- Teal `#0A4F5E`, Gold `#B1915A`, Blue `#4A7FD4`, Purple `#7C5CBF`, Coral `#E07A5F`, Green `#4CAF7D`

---

## 4. Typography

**Font family:** Inter, system-ui sans (`--font-sans`)

| Level | Size | Weight | Tracking | Color |
|-------|------|--------|----------|-------|
| Page title | 24px (1.5rem) | 600 | tight | foreground |
| Section title | 16–18px | 600 | normal | foreground |
| Card title | 14px | 600 | normal | foreground |
| Body | 14px | 400 | normal | foreground |
| Label | 12–13px | 500 | normal | muted-foreground |
| Eyebrow / section header | 11–12px | 600 | wide uppercase | muted-foreground |
| Tab label | 12–13px | 600 | wide uppercase | muted → foreground when active |
| Meta / timestamp | 12px | 400 | normal | muted-foreground |

---

## 5. Spacing & radius

| Token | Value |
|-------|-------|
| `--radius-sm` | 6px |
| `--radius-md` | 8px |
| `--radius-lg` | 12px |
| `--radius-xl` | 16px |
| `--radius-full` | 9999px |
| `--space-card` | 24px (card padding) |
| `--space-page-x/y` | 32px |
| Card gap | 24px (`space-y-6`) |
| Form field gap | 16px |

---

## 6. Shadows

tiimi uses **soft elevation** on white cards (unlike the prior BanwoHub no-shadow rule).

| Token | Value |
|-------|-------|
| `--shadow-card` | `0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)` |
| `--shadow-md` | `0 4px 12px rgba(0,0,0,0.08)` |
| `--shadow-modal` | `0 8px 24px rgba(0,0,0,0.12)` |

---

## 7. Components

### 7.1 Cards (`.bw-card`)

```html
<section class="bw-card overflow-hidden">…</section>
```

- White background, `--radius-lg`, `--shadow-card`
- Border: `1px solid var(--border)` (subtle)
- Header row: flex justify-between, border-bottom, padding 16–20px
- Body padding: 20–24px

**Grid cards** (employees, jobs): checkbox top-left, status badge top-right, avatar, name, metadata grid, contact row. Optional colored top accent bar.

### 7.2 Buttons

| Class | Appearance | When to use |
|-------|------------|-------------|
| `.bw-btn-accent` | BanwoHub gold fill, white text | Add New, create actions |
| `.bw-btn-action` | BanwoHub teal fill, white text | Save, Publish, Send |
| `.bw-btn-primary` | Alias → `.bw-btn-action` | Primary confirm |
| `.bw-btn-outline` | White + border | Secondary (Share, Cancel) |
| `.bw-btn-ghost` | Transparent | Tertiary, icon buttons |
| `.bw-btn-danger` | Red outline | Delete |
| `.bw-btn-accent-icon` | Gold circle, + icon | Header global add |

Sizes: `.bw-btn-sm`, `.bw-btn-icon`

**Never** place a permanent create form on a list page. The gold Add button opens `BwModal` or navigates to `/resource/new`.

### 7.3 Tabs (`.bw-tabs` / `.bw-tab`)

- Uppercase labels, semibold, muted default
- Active: foreground color + **2–3px gold bottom border** (`--accent-gold`)
- Container: border-bottom on tab strip

### 7.4 Forms

- Labels **above** inputs (`.bw-label`)
- Inputs: light gray background (`--surface-muted`), 1px subtle border, 8px radius
- No heavy dark borders; focus ring teal
- Group fields in white card on gray workspace
- Rich text editors: toolbar top, Save/Cancel top-right of card (Save = teal)

### 7.5 Badges & pills (`.bw-badge-*`)

Rounded-full or rounded-md pills. Light tinted background + darker text + matching border.

Skill/tag pills: light blue-gray bg `#E8EEF7`, text `#3B5998`.

### 7.6 Tables & lists

- Table header: 12px uppercase muted labels, border-bottom
- Rows: white bg, divide borders, hover `--surface-muted`
- **Selected row:** `--selection-teal` background
- Unread badge: red circle, white count
- Row actions: ghost buttons or teal text links

### 7.7 Modals (`.bw-modal`)

- Overlay: `rgba(0,0,0,0.4)`
- Panel: white, `--radius-xl`, `--shadow-modal`, max-width 560px (forms) or 720px (wide)
- Header: title + close
- Footer: Cancel (outline) + Save (teal)

### 7.8 Empty & loading states

Use `EmptyState` and `Skeleton` inside white cards. Copy is concise; primary action is gold Add button in page header.

---

## 8. Reference screen catalog

### 8.1 Inbox / messaging (`c0e4a9…`, `5020c4…`)

Three-column inbox: thread list (avatars, unread badges, teal selection), conversation panel (email/WhatsApp toggle, teal Reply/Send), candidate profile card (rating stars, applied-for block, schedule). Tabs: RECRUITMENT INBOX | WORK INBOX with yellow underline.

### 8.2 Candidates list + detail drawer (`472b46…`)

Table with checkboxes, ratings, stage dropdowns. Right drawer: profile header, stage indicators, tabs (DETAILS, RESUME, PIPELINE, INTERVIEWS), two-column label/value grid, skill pills, ghost Send Message/Email.

### 8.3 Job editor (`67807c…`)

Split layout: main editor (title, rich description) + right metadata card (department, location, skills pills, hiring manager avatar, toggle). Header: Published badge (teal), Share (outline). Tabs: CANDIDATES | JOB DETAILS.

### 8.4 Employee grid (`d42285…`)

Card grid with status badges (Active/Not Active/Unverified), bulk select, filters, pagination with yellow active page. Card menu dropdown with teal hover on Edit.

### 8.5 Career site editor (`8492f8…`)

Live preview center + settings accordion right. Toolbar: device toggles, zoom. Publish (teal), Preview (outline). Job cards with APPLY (teal).

### 8.6 Calendar / scheduling (refs `1cf53f…`, `1f8b28…`)

Month grid on white card, event pills, sidebar mini-calendar. New event via modal, not inline.

### 8.7 Tasks / kanban (refs `b3887e…`, `b7551a…` GIFs)

Columns with colored dots, dashed count circles, draggable cards. Add card via column + or header yellow +.

### 8.8 Settings / forms (refs `fa625e…`, `d5d2ec…`)

Accordion sections, toggle switches (green on), upload zones, color pickers on white settings panel.

---

## 9. Page implementation checklist

Before marking a page **done**:

1. Re-read this document
2. Glance at 1–2 reference images for that page type (list / detail / editor)
3. Confirm workspace bg is light gray; **all card containers** are `#FFFFFF` (no `bg-muted` / `bg-surface-muted` on cards)
4. Confirm sidebar active uses BanwoHub gold pill (layout-level)
5. Page header has title + gold Add (if create is supported)
6. **No inline add form** on list pages — modal or `/new` route only
7. Tabs use uppercase + gold underline
8. Save/confirm actions use BanwoHub teal; Add uses BanwoHub gold
9. Tables/lists use teal selection highlight
10. Update `docs/UI_INVENTORY.md` status

---

## 10. CSS class quick reference

```html
<!-- Page shell -->
<div class="space-y-6">
  <PageHeader title="…">
    <template #actions>
      <button class="bw-btn bw-btn-accent">…</button>
    </template>
  </PageHeader>
  <section class="bw-card">…</section>
</div>

<!-- Tabs -->
<nav class="bw-tabs">
  <button class="bw-tab bw-tab-active">ACTIVE</button>
  <button class="bw-tab">OTHER</button>
</nav>

<!-- Form -->
<label class="bw-label" for="x">Label</label>
<input id="x" class="bw-input" />
<button class="bw-btn bw-btn-action">Save</button>
```

---

## 11. BanwoHub ↔ tiimi mapping

| tiimi module | BanwoHub equivalent |
|--------------|---------------------|
| Candidates | Clients |
| Jobs | Cases / Intake |
| Employee | Team / Users |
| Inbox | Messages |
| Calendar | Calendar |
| Career site | Portal (client-facing) |
| Tasks | Case tasks / Legal projects |
| Pipeline/Kanban | Intake kanban |

Apply tiimi **patterns**, not literal HR copy.
