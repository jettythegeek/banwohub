<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  PhBell,
  PhCaretDown,
  PhGear,
  PhList,
  PhMagnifyingGlass,
  PhPlus,
  PhSignOut,
} from '@phosphor-icons/vue'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import AppAvatar from '@/components/common/AppAvatar.vue'

defineEmits<{ 'toggle-sidebar': [] }>()

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const notifications = useNotificationsStore()
const { primaryRole } = usePermissions()

const title = computed(() => (route.meta.title as string) || 'Dashboard')
const menuOpen = ref(false)
const addMenuOpen = ref(false)
const searchQuery = ref('')

const addRoutes: Record<string, string> = {
  clients: '/clients/new',
  cases: '/cases/new',
  intake: '/intake',
  invoices: '/invoices/new',
  'time-tracking': '/time-tracking',
  messages: '/messages',
  calendar: '/calendar',
  briefs: '/briefs',
  motions: '/motions',
  knowledge: '/knowledge',
  research: '/research',
}

const addRoute = computed(() => {
  const name = route.name as string | undefined
  if (!name) return null
  const base = name.split('-')[0]
  if (addRoutes[name]) return addRoutes[name]
  if (base && addRoutes[base]) return addRoutes[base]
  return null
})

function submitSearch() {
  const q = searchQuery.value.trim()
  if (q.length < 2) return
  void router.push({ name: 'search', query: { q } })
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  addMenuOpen.value = false
  if (menuOpen.value) {
    document.addEventListener('click', closeMenus)
  }
}

function toggleAddMenu() {
  addMenuOpen.value = !addMenuOpen.value
  menuOpen.value = false
  if (addMenuOpen.value) {
    document.addEventListener('click', closeMenus)
  }
}

function closeMenus() {
  menuOpen.value = false
  addMenuOpen.value = false
  document.removeEventListener('click', closeMenus)
}

onBeforeUnmount(() => document.removeEventListener('click', closeMenus))

async function handleLogout() {
  await auth.logout()
  window.location.href = '/login'
}
</script>

<template>
  <header
    class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-surface px-4 sm:px-6"
  >
    <button
      type="button"
      class="bw-btn bw-btn-ghost bw-btn-icon lg:hidden"
      aria-label="Open navigation"
      @click="$emit('toggle-sidebar')"
    >
      <PhList class="h-5 w-5" />
    </button>

    <h1 class="truncate text-lg font-semibold tracking-tight text-foreground">
      {{ title }}
    </h1>

    <div class="ml-auto flex items-center gap-2">
      <form
        class="relative hidden md:block"
        role="search"
        @submit.prevent="submitSearch"
      >
        <PhMagnifyingGlass
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
        />
        <input
          v-model="searchQuery"
          type="search"
          aria-label="Search"
          placeholder="Search…"
          class="bw-input w-56 border-transparent bg-surface-muted pl-9"
          minlength="2"
        />
      </form>

      <div v-if="addRoute" class="relative hidden sm:block">
        <RouterLink
          :to="addRoute"
          class="bw-btn-accent-icon bw-focus-ring"
          aria-label="Add new"
          @click="closeMenus"
        >
          <PhPlus class="h-5 w-5" weight="bold" />
        </RouterLink>
      </div>
      <button
        v-else
        type="button"
        class="bw-btn-accent-icon bw-focus-ring hidden sm:inline-flex"
        aria-label="Add new"
        @click.stop="toggleAddMenu"
      >
        <PhPlus class="h-5 w-5" weight="bold" />
      </button>

      <RouterLink
        to="/notifications"
        class="bw-btn bw-btn-ghost bw-btn-icon relative"
        aria-label="Notifications"
      >
        <PhBell class="h-5 w-5" />
        <span
          v-if="notifications.unread"
          class="absolute right-1 top-1 inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-primary-foreground"
        >
          {{ notifications.unread > 9 ? '9+' : notifications.unread }}
        </span>
      </RouterLink>

      <div class="relative">
        <button
          type="button"
          class="bw-focus-ring flex items-center gap-2 rounded-md py-1 pl-1 pr-2 hover:bg-surface-muted"
          aria-haspopup="menu"
          :aria-expanded="menuOpen"
          @click.stop="toggleMenu"
        >
          <AppAvatar :name="auth.user?.name" size="sm" />
          <span class="hidden text-left leading-tight sm:block">
            <span class="block max-w-[140px] truncate text-sm font-medium text-foreground">
              {{ auth.user?.name }}
            </span>
            <span class="block max-w-[140px] truncate text-[11px] text-muted-foreground">
              {{ primaryRole || auth.user?.email }}
            </span>
          </span>
          <PhCaretDown class="h-4 w-4 text-muted-foreground" />
        </button>

        <div
          v-if="menuOpen"
          class="absolute right-0 mt-2 w-56 overflow-hidden rounded-lg border border-border bg-surface py-1"
          role="menu"
        >
          <div class="border-b border-border px-4 py-3">
            <p class="truncate text-sm font-medium text-foreground">
              {{ auth.user?.name }}
            </p>
            <p class="truncate text-xs text-muted-foreground">
              {{ auth.user?.email }}
            </p>
          </div>
          <RouterLink
            to="/settings"
            class="flex items-center gap-2.5 px-4 py-2 text-sm text-foreground hover:bg-surface-muted"
            role="menuitem"
            @click="closeMenus"
          >
            <PhGear class="h-4 w-4 text-muted-foreground" />
            Settings
          </RouterLink>
          <button
            type="button"
            class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-destructive hover:bg-surface-muted"
            role="menuitem"
            @click="handleLogout"
          >
            <PhSignOut class="h-4 w-4" />
            Sign out
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
