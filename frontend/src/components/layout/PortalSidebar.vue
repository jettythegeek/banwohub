<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  PhBriefcase,
  PhCalendarBlank,
  PhChatCircle,
  PhClipboardText,
  PhGear,
  PhReceipt,
  PhScales,
  PhSignOut,
  PhSquaresFour,
} from '@phosphor-icons/vue'
import type { Component } from 'vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import { usePortalAuthStore } from '@/stores/portalAuth'

const emit = defineEmits<{ navigate: [] }>()

const auth = usePortalAuthStore()
const route = useRoute()

type NavItem = {
  href: string
  label: string
  icon: Component
  exact?: boolean
}

const navItems: NavItem[] = [
  { href: '/portal', label: 'Dashboard', icon: PhSquaresFour, exact: true },
  { href: '/portal/messages', label: 'Messages', icon: PhChatCircle },
  { href: '/portal/cases', label: 'My cases', icon: PhBriefcase },
  { href: '/portal/invoices', label: 'Invoices', icon: PhReceipt },
  { href: '/portal/appointments', label: 'Appointments', icon: PhCalendarBlank },
  { href: '/portal/intake', label: 'Intake forms', icon: PhClipboardText },
  { href: '/portal/profile', label: 'Settings', icon: PhGear },
]

const unreadMessages = computed(() => auth.dashboardUnread ?? 0)

function isActive(href: string, exact?: boolean) {
  if (exact) return route.path === href
  return route.path === href || route.path.startsWith(`${href}/`)
}
</script>

<template>
  <aside class="fixed inset-y-0 left-0 z-30 flex h-dvh w-64 flex-col bg-sidebar-bg">
    <div class="flex h-16 shrink-0 items-center gap-2.5 px-5">
      <span
        class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-gold text-accent-gold-fg"
      >
        <PhScales class="h-5 w-5" weight="fill" />
      </span>
      <div class="min-w-0 leading-tight">
        <p class="truncate text-sm font-semibold text-sidebar-fg">Banwolaw</p>
        <p class="truncate text-[11px] text-sidebar-muted">Client portal</p>
      </div>
    </div>

    <nav class="sidebar-nav flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
      <RouterLink
        v-for="item in navItems"
        :key="item.href"
        :to="item.href"
        class="bw-focus-ring flex items-center gap-3 rounded-full px-3 py-2 text-sm font-medium transition-colors"
        :class="
          isActive(item.href, item.exact)
            ? 'bg-sidebar-active-bg text-sidebar-active-fg'
            : 'text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-fg'
        "
        @click="emit('navigate')"
      >
        <component
          :is="item.icon"
          class="h-[18px] w-[18px] shrink-0"
          :weight="isActive(item.href, item.exact) ? 'fill' : 'regular'"
        />
        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
        <span
          v-if="item.href === '/portal/messages' && unreadMessages > 0"
          class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-destructive px-1.5 text-[11px] font-semibold text-white"
        >
          {{ unreadMessages > 9 ? '9+' : unreadMessages }}
        </span>
      </RouterLink>
    </nav>

    <div class="shrink-0 border-t border-sidebar-border p-3">
      <div class="flex items-center gap-3 rounded-md px-2 py-2">
        <AppAvatar :name="auth.user?.name" size="sm" tone="accent" />
        <div class="min-w-0 flex-1 leading-tight">
          <p class="truncate text-sm font-medium text-sidebar-fg">
            {{ auth.user?.name }}
          </p>
          <p class="truncate text-[11px] text-sidebar-muted">
            {{ auth.user?.email }}
          </p>
        </div>
        <slot name="sign-out" />
      </div>
    </div>
  </aside>
</template>
