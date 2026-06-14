# Tailwind CSS & Vue Integration

Stack: **Vue 3**, **Vite**, **Tailwind CSS v4**, **Pinia**. Use semantic CSS variables from [tokens.css](./tokens.css).

---

## 1. CSS variables

Copy [tokens.css](./tokens.css) into the frontend:

```
frontend/src/styles/tokens.css
```

Import in `main.css`:

```css
@import "tailwindcss";
@import "./tokens.css";
```

---

## 2. Tailwind CSS v4 (Vite)

`vite.config.ts`:

```ts
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [tailwindcss(), vue()],
})
```

`src/styles/main.css`:

```css
@import "tailwindcss";
@import "./tokens.css";

@theme {
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-surface: var(--surface);
  --color-border: var(--border);
  --color-ring: var(--ring);
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-primary-muted: var(--primary-muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-destructive: var(--status-danger-fg);
  /* NO shadows — flat system */
  --shadow-sm: none;
  --shadow: none;
}
```

Utility class `.bw-card` in `tokens.css` provides flat bordered surfaces (no box-shadow).

---

## 3. Vue layout pattern

```vue
<!-- AppShell.vue -->
<div class="flex min-h-screen bg-background">
  <aside class="hidden lg:flex w-64 flex-col border-r border-border bg-surface">
    <!-- Sidebar -->
  </aside>
  <main class="flex-1 overflow-y-auto p-6 lg:p-8">
    <slot />
  </main>
</div>
```

---

## 4. Checklist before first UI PR

- [ ] `tokens.css` imported in `main.css`
- [ ] No `shadow-*` on cards or buttons
- [ ] Inter loaded in `index.html`
- [ ] Focus rings via `.bw-focus-ring`
- [ ] Sidebar matches [05-components.md](./05-components.md) anatomy
