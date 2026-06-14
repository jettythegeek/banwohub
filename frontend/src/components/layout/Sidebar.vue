<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  PhBell,
  PhBriefcase,
  PhCalendarBlank,
  PhChartBar,
  PhChatCircle,
  PhRobot,
  PhClipboardText,
  PhClock,
  PhArticle,
  PhFileText,
  PhFolderOpen,
  PhReceipt,
  PhGear,
  PhBooks,
  PhTray,
  PhChartLineUp,
  PhGraduationCap,
  PhKanban,
  PhScales,
  PhSignOut,
  PhSquaresFour,
  PhUserCircleGear,
  PhUsers,
} from '@phosphor-icons/vue'
import type { Component } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { prefetchByName } from '@/router'
import AppAvatar from '@/components/common/AppAvatar.vue'

const emit = defineEmits<{ navigate: [] }>()

const auth = useAuthStore()
const notifications = useNotificationsStore()
const { can, primaryRole } = usePermissions()
const route = useRoute()

type NavItem = {
  href: string
  name: string
  label: string
  icon: Component
  badge?: () => number
}

type NavSection = {
  heading?: string
  items: NavItem[]
}

const sections = computed<NavSection[]>(() => {
  const practiceItems: NavItem[] = [
    {
      href: '/notifications',
      name: 'notifications',
      label: 'Notifications',
      icon: PhBell,
      badge: () => notifications.unread,
    },
    { href: '/settings', name: 'settings', label: 'Settings', icon: PhGear },
  ]

  if (can('users.manage')) {
    practiceItems.push({
      href: '/settings/users',
      name: 'settings-users',
      label: 'Team',
      icon: PhUserCircleGear,
    })
  }

  const workspaceItems: NavItem[] = [
    { href: '/clients', name: 'clients', label: 'Clients', icon: PhUsers },
    { href: '/cases', name: 'cases', label: 'Cases', icon: PhBriefcase },
    { href: '/intake', name: 'intake', label: 'Intake', icon: PhClipboardText },
    {
      href: '/conflict-checks',
      name: 'conflict-checks',
      label: 'Conflicts',
      icon: PhScales,
    },
  ]

  if (can('time-entries.view')) {
    workspaceItems.push({
      href: '/time-tracking',
      name: 'time-tracking',
      label: 'Time tracking',
      icon: PhClock,
    })
  }

  if (can('invoices.view')) {
    workspaceItems.push({
      href: '/invoices',
      name: 'invoices',
      label: 'Invoices',
      icon: PhReceipt,
    })
  }

  if (can('messages.view')) {
    workspaceItems.push({
      href: '/messages',
      name: 'messages',
      label: 'Messages',
      icon: PhChatCircle,
    })
  }

  if (can('appointments.view')) {
    workspaceItems.push({
      href: '/calendar',
      name: 'calendar',
      label: 'Calendar',
      icon: PhCalendarBlank,
    })
  }

  if (can('reports.view')) {
    workspaceItems.push({
      href: '/reports',
      name: 'reports',
      label: 'Reports',
      icon: PhChartBar,
    })
  }

  if (can('ai.use')) {
    workspaceItems.push({
      href: '/ai-assistant',
      name: 'ai-assistant',
      label: 'AI assistant',
      icon: PhRobot,
    })
  }

  if (can('filings.view')) {
    workspaceItems.push({
      href: '/filings',
      name: 'filings',
      label: 'Filings',
      icon: PhFileText,
    })
  }

  if (can('evidence.view')) {
    workspaceItems.push({
      href: '/evidence',
      name: 'evidence',
      label: 'Evidence',
      icon: PhFolderOpen,
    })
  }

  if (can('briefs.view')) {
    workspaceItems.push({
      href: '/briefs',
      name: 'briefs',
      label: 'AI Brief Writer',
      icon: PhArticle,
    })
  }

  if (can('motions.view')) {
    workspaceItems.push({
      href: '/motions',
      name: 'motions',
      label: 'Motions',
      icon: PhScales,
    })
  }

  if (can('research.view')) {
    workspaceItems.push({
      href: '/research',
      name: 'research',
      label: 'Research Command Center',
      icon: PhBooks,
    })
  }

  if (can('ediscovery.view')) {
    workspaceItems.push({
      href: '/e-discovery',
      name: 'e-discovery',
      label: 'E-discovery',
      icon: PhTray,
    })
  }

  if (can('knowledge.view')) {
    workspaceItems.push({
      href: '/knowledge',
      name: 'knowledge',
      label: 'Knowledge',
      icon: PhArticle,
    })
  }

  if (can('projects.view')) {
    workspaceItems.push({
      href: '/legal-projects',
      name: 'legal-projects',
      label: 'Projects',
      icon: PhKanban,
    })
  }

  if (can('analytics.view')) {
    workspaceItems.push({
      href: '/legal-analytics',
      name: 'legal-analytics',
      label: 'Analytics',
      icon: PhChartLineUp,
    })
  }

  if (can('training.view')) {
    workspaceItems.push({
      href: '/training',
      name: 'training',
      label: 'Training',
      icon: PhGraduationCap,
    })
  }

  return [
    {
      items: [
        { href: '/dashboard', name: 'dashboard', label: 'Dashboard', icon: PhSquaresFour },
      ],
    },
    {
      heading: 'Workspace',
      items: workspaceItems,
    },
    {
      heading: 'Practice',
      items: practiceItems,
    },
  ]
})

function isActive(href: string) {
  return route.path === href || route.path.startsWith(`${href}/`)
}

async function handleLogout() {
  await auth.logout()
  window.location.href = '/login'
}
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-30 flex h-dvh w-64 flex-col bg-sidebar-bg"
  >
    <div class="flex h-16 shrink-0 items-center gap-2.5 px-5">
      <span
        class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-gold text-accent-gold-fg"
      >
        <PhScales class="h-5 w-5" weight="fill" />
      </span>
      <div class="min-w-0 leading-tight">
        <p class="truncate text-sm font-semibold text-sidebar-fg">Banwolaw Hub</p>
        <p class="truncate text-[11px] text-sidebar-muted">Practice workspace</p>
      </div>
    </div>

    <nav class="sidebar-nav flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-3 py-4">
      <div v-for="(section, i) in sections" :key="i" class="space-y-1">
        <p
          v-if="section.heading"
          class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-sidebar-muted"
        >
          {{ section.heading }}
        </p>
        <RouterLink
          v-for="item in section.items"
          :key="item.href"
          :to="item.href"
          class="bw-focus-ring flex items-center gap-3 rounded-full px-3 py-2 text-sm font-medium transition-colors"
          :class="
            isActive(item.href)
              ? 'bg-sidebar-active-bg text-sidebar-active-fg'
              : 'text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-fg'
          "
          @mouseenter="prefetchByName(item.name)"
          @focus="prefetchByName(item.name)"
          @click="emit('navigate')"
        >
          <component
            :is="item.icon"
            class="h-[18px] w-[18px] shrink-0"
            :weight="isActive(item.href) ? 'fill' : 'regular'"
          />
          <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
          <span
            v-if="item.badge && item.badge()"
            class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-destructive px-1.5 text-[11px] font-semibold text-white"
          >
            {{ item.badge() }}
          </span>
        </RouterLink>
      </div>
    </nav>

    <div class="shrink-0 border-t border-sidebar-border p-3">
      <div class="flex items-center gap-3 rounded-md px-2 py-2">
        <AppAvatar :name="auth.user?.name" size="sm" tone="accent" />
        <div class="min-w-0 flex-1 leading-tight">
          <p class="truncate text-sm font-medium text-sidebar-fg">
            {{ auth.user?.name }}
          </p>
          <p class="truncate text-[11px] text-sidebar-muted">
            {{ primaryRole || auth.user?.email }}
          </p>
        </div>
        <button
          type="button"
          class="bw-focus-ring rounded-md p-2 text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-fg"
          aria-label="Sign out"
          @click="handleLogout"
        >
          <PhSignOut class="h-[18px] w-[18px]" weight="regular" />
        </button>
      </div>
    </div>
  </aside>
</template>
