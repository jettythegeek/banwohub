<script setup lang="ts">
import { computed, inject } from 'vue'
import { RouterLink } from 'vue-router'
import {
  PhBell,
  PhBriefcase,
  PhChatCircle,
  PhCheckCircle,
  PhClipboardText,
  PhWarningCircle,
} from '@phosphor-icons/vue'
import DashboardCaseCards from '@/components/dashboard/DashboardCaseCards.vue'
import DashboardDeadlineCard from '@/components/dashboard/DashboardDeadlineCard.vue'
import DashboardTasksPanel from '@/components/dashboard/DashboardTasksPanel.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { usePermissions } from '@/composables/usePermissions'
import type { useDashboard } from '@/composables/useDashboard'
import { humanize } from '@/lib/status'
import { useNotificationsStore } from '@/stores/notifications'

const dashboard = inject<ReturnType<typeof useDashboard>>('dashboard')!
const { can } = usePermissions()
const notifications = useNotificationsStore()

const {
  data,
  isLoading,
  isLoadingDeadlines,
  nextDeadline,
  deadlineDaysUntil,
  deadlineRingProgress,
} = dashboard

const summaryCards = computed(() => [
  {
    label: 'Assigned cases',
    value: data.value?.stats.assigned_cases ?? 0,
    icon: PhBriefcase,
    to: '/cases',
    accent: 'bg-primary-50 text-primary-700',
  },
  {
    label: 'Open tasks',
    value: data.value?.stats.open_tasks ?? 0,
    icon: PhCheckCircle,
    to: '/legal-projects',
    accent: 'bg-status-success/10 text-status-success-fg',
  },
  {
    label: 'Overdue',
    value: data.value?.stats.overdue_tasks ?? 0,
    icon: PhWarningCircle,
    to: '/legal-projects',
    accent: 'bg-status-danger/10 text-destructive',
  },
  {
    label: 'Unread messages',
    value: data.value?.stats.unread_messages ?? 0,
    icon: PhChatCircle,
    to: '/messages',
    accent: 'bg-accent-50 text-accent-700',
  },
])

function approvalLabel(type: string) {
  return humanize(type.replace('_', ' '))
}
</script>

<template>
  <div class="space-y-6">
    <Skeleton v-if="isLoading" variant="stats" :count="4" />
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <RouterLink
        v-for="card in summaryCards"
        :key="card.label"
        :to="card.to"
        class="bw-card p-5 transition-colors hover:border-border-strong"
      >
        <div class="flex items-start gap-3">
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
            :class="card.accent"
          >
            <component :is="card.icon" class="h-5 w-5" weight="fill" />
          </span>
          <div>
            <p class="text-sm text-muted-foreground">{{ card.label }}</p>
            <p class="text-2xl font-semibold tabular-nums text-foreground">{{ card.value }}</p>
          </div>
        </div>
      </RouterLink>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <DashboardTasksPanel
          :tasks="data?.my_tasks ?? []"
          :is-loading="isLoading"
          title="Today's work"
          subtitle="Your open tasks, prioritized by due date."
        />
      </div>
      <DashboardDeadlineCard
        :deadline="nextDeadline"
        :is-loading="isLoadingDeadlines"
        :days-until="deadlineDaysUntil"
        :ring-progress="deadlineRingProgress"
      />
    </div>

    <DashboardCaseCards
      v-if="!isLoading"
      :cases="data?.recent_cases ?? []"
      title="Assigned cases"
      subtitle="Matters where you are lead counsel or on the team."
      :limit="3"
    />

    <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-2">
      <section v-if="can('messages.view')" class="bw-card">
        <div class="bw-card-header">
          <div>
            <h2 class="font-semibold text-foreground">Client messages</h2>
            <p class="text-sm text-muted-foreground">Recent threads on your matters.</p>
          </div>
          <RouterLink to="/messages" class="text-sm font-medium text-primary-700 hover:underline">
            View all
          </RouterLink>
        </div>
        <ul v-if="data?.messages_preview?.length" class="divide-y divide-border">
          <li v-for="thread in data.messages_preview" :key="thread.id">
            <RouterLink
              :to="`/messages?thread=${thread.id}`"
              class="flex items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-surface-muted"
            >
              <div class="min-w-0">
                <p class="truncate font-medium text-foreground">{{ thread.subject }}</p>
                <p class="truncate text-sm text-muted-foreground">
                  {{ thread.latest_message?.body || 'No messages yet' }}
                </p>
              </div>
              <span
                v-if="thread.unread_count"
                class="bw-badge bw-badge-primary shrink-0"
              >
                {{ thread.unread_count }}
              </span>
            </RouterLink>
          </li>
        </ul>
        <EmptyState
          v-else
          :icon="PhChatCircle"
          title="No messages"
          message="Client conversations will appear here."
          class="border-0 shadow-none"
        />
      </section>

      <section v-if="data?.pending_approvals?.length" class="bw-card">
        <div class="bw-card-header">
          <div>
            <h2 class="font-semibold text-foreground">Pending approvals</h2>
            <p class="text-sm text-muted-foreground">Items awaiting your review.</p>
          </div>
        </div>
        <ul class="divide-y divide-border">
          <li
            v-for="approval in data.pending_approvals"
            :key="approval.id"
            class="flex items-center gap-3 px-5 py-4"
          >
            <PhClipboardText class="h-5 w-5 shrink-0 text-muted-foreground" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-foreground">
                {{ approvalLabel(approval.subject_type) }} #{{ approval.subject_id }}
              </p>
              <p v-if="approval.submitter" class="text-xs text-muted-foreground">
                Submitted by {{ approval.submitter.name }}
              </p>
            </div>
          </li>
        </ul>
      </section>

      <section class="bw-card">
        <div class="bw-card-header">
          <h2 class="font-semibold text-foreground">Notifications</h2>
          <RouterLink
            to="/notifications"
            class="text-sm font-medium text-primary-700 hover:underline"
          >
            View all
          </RouterLink>
        </div>
        <div class="flex items-center gap-3 px-5 py-4">
          <span
            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-700"
          >
            <PhBell class="h-5 w-5" weight="fill" />
          </span>
          <p class="text-sm text-foreground">
            <span class="font-semibold tabular-nums">{{ notifications.unread }}</span>
            unread
            {{ notifications.unread === 1 ? 'notification' : 'notifications' }}
          </p>
        </div>
      </section>
    </div>
  </div>
</template>
