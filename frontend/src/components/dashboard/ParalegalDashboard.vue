<script setup lang="ts">
import { computed, inject } from 'vue'
import { RouterLink } from 'vue-router'
import {
  PhBell,
  PhCheckCircle,
  PhFile,
  PhFileText,
  PhWarningCircle,
} from '@phosphor-icons/vue'
import DashboardDeadlineCard from '@/components/dashboard/DashboardDeadlineCard.vue'
import DashboardTasksPanel from '@/components/dashboard/DashboardTasksPanel.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { usePermissions } from '@/composables/usePermissions'
import type { useDashboard } from '@/composables/useDashboard'
import { filingStatusDotVar, humanize, statusDotVar } from '@/lib/status'
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
  charts,
  maxFilingStatusCount,
  maxTaskWorkloadCount,
} = dashboard

const summaryCards = computed(() => [
  {
    label: 'Open tasks',
    value: data.value?.stats.open_tasks ?? 0,
    icon: PhCheckCircle,
    to: '/legal-projects',
    accent: 'bg-primary-50 text-primary-700',
  },
  {
    label: 'Overdue',
    value: data.value?.stats.overdue_tasks ?? 0,
    icon: PhWarningCircle,
    to: '/legal-projects',
    accent: 'bg-status-danger/10 text-destructive',
  },
  {
    label: 'Filings',
    value: data.value?.legal_ops.filings.total ?? 0,
    icon: PhFileText,
    to: '/filings',
    accent: 'bg-accent-50 text-accent-700',
  },
  {
    label: 'Corrections',
    value: data.value?.legal_ops.filings.corrections ?? 0,
    icon: PhFileText,
    to: '/filings',
    accent: 'bg-warning-50 text-warning-700',
  },
])

function documentReasonLabel(reason: string) {
  return reason === 'client_upload' ? 'Client upload' : 'AI review'
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

    <DashboardTasksPanel
      :tasks="data?.my_tasks ?? []"
      :is-loading="isLoading"
      title="My tasks"
      subtitle="Your open work on assigned matters — start here."
    />

    <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-3">
      <section class="bw-card p-6 lg:col-span-2">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <PhFileText class="h-5 w-5 text-accent-600" weight="fill" />
            <div>
              <h2 class="font-semibold text-foreground">Filing pipeline</h2>
              <p class="text-sm text-muted-foreground">Filings on your assigned matters.</p>
            </div>
          </div>
          <RouterLink
            v-if="can('filings.create')"
            to="/filings"
            class="text-sm font-medium text-primary-700 hover:underline"
          >
            New filing
          </RouterLink>
        </div>
        <div v-if="charts?.filings_by_status?.length" class="space-y-3">
          <div
            v-for="row in charts.filings_by_status"
            :key="row.status"
            class="space-y-1.5"
          >
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="inline-flex items-center gap-2 text-foreground">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: `var(${filingStatusDotVar(row.status)})` }"
                />
                {{ humanize(row.status) }}
              </span>
              <span class="tabular-nums text-muted-foreground">{{ row.count }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full"
                :style="{
                  width: `${(row.count / maxFilingStatusCount) * 100}%`,
                  backgroundColor: `var(${filingStatusDotVar(row.status)})`,
                }"
              />
            </div>
          </div>
        </div>
        <EmptyState
          v-else
          :icon="PhFileText"
          title="No filings yet"
          message="Court filings on your matters will appear here."
        />
      </section>

      <section class="bw-card p-6">
        <div class="mb-4 flex items-center gap-2">
          <PhCheckCircle class="h-5 w-5 text-status-success-fg" weight="fill" />
          <div>
            <h2 class="font-semibold text-foreground">My workload</h2>
            <p class="text-sm text-muted-foreground">Tasks by status.</p>
          </div>
        </div>
        <div v-if="charts?.task_workload?.length" class="space-y-3">
          <div
            v-for="row in charts.task_workload"
            :key="row.status"
            class="space-y-1.5"
          >
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="inline-flex items-center gap-2 text-foreground">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: `var(${statusDotVar(row.status)})` }"
                />
                {{ humanize(row.status) }}
              </span>
              <span class="tabular-nums text-muted-foreground">{{ row.count }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full"
                :style="{
                  width: `${(row.count / maxTaskWorkloadCount) * 100}%`,
                  backgroundColor: `var(${statusDotVar(row.status)})`,
                }"
              />
            </div>
          </div>
        </div>
        <EmptyState
          v-else
          :icon="PhCheckCircle"
          title="No open tasks"
          message="Your task breakdown will show here."
        />
      </section>
    </div>

    <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-3">
      <section class="bw-card lg:col-span-2">
        <div class="bw-card-header">
          <div>
            <h2 class="font-semibold text-foreground">Documents needing attention</h2>
            <p class="text-sm text-muted-foreground">
              Client uploads and drafts on your assigned cases.
            </p>
          </div>
        </div>
        <ul v-if="data?.documents_attention?.length" class="divide-y divide-border">
          <li v-for="doc in data.documents_attention" :key="doc.id">
            <RouterLink
              :to="doc.case ? `/cases/${doc.case.id}/documents` : '/dashboard'"
              class="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-surface-muted"
            >
              <PhFile class="h-5 w-5 shrink-0 text-muted-foreground" />
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-foreground">{{ doc.name }}</p>
                <p class="text-sm text-muted-foreground">
                  {{ documentReasonLabel(doc.reason) }}
                  <span v-if="doc.case"> · {{ doc.case.title }}</span>
                </p>
              </div>
            </RouterLink>
          </li>
        </ul>
        <EmptyState
          v-else
          :icon="PhFile"
          title="All clear"
          message="No documents need your attention right now."
          class="border-0 shadow-none"
        />
      </section>

      <div class="space-y-6">
        <DashboardDeadlineCard
          :deadline="nextDeadline"
          :is-loading="isLoadingDeadlines"
          :days-until="deadlineDaysUntil"
          :ring-progress="deadlineRingProgress"
        />

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
  </div>
</template>
