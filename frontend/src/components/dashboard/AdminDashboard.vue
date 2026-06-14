<script setup lang="ts">
import { inject } from 'vue'
import { RouterLink } from 'vue-router'
import {
  PhArrowRight,
  PhBell,
  PhBriefcase,
  PhCalendarBlank,
  PhChartLineUp,
  PhCheckCircle,
  PhCurrencyDollar,
  PhDotsThree,
  PhFileText,
  PhFolderOpen,
  PhScales,
  PhUsers,
} from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { formatDue, formatDeadlineDate } from '@/composables/useDashboard'
import type { useDashboard } from '@/composables/useDashboard'
import { formatCurrency } from '@/lib/currency'
import {
  filingStatusDotVar,
  humanize,
  invoiceStatusDotVar,
  motionStatusDotVar,
  statusDotVar,
} from '@/lib/status'
import { useNotificationsStore } from '@/stores/notifications'

const dashboard = inject<ReturnType<typeof useDashboard>>('dashboard')!
const notifications = useNotificationsStore()

const {
  data,
  isLoading,
  isLoadingDeadlines,
  nextDeadline,
  deadlineDaysUntil,
  deadlineRingProgress,
  legalOpsCards,
  upcomingTasks,
  charts,
  maxCaseStatusCount,
  maxFilingStatusCount,
  maxMotionStatusCount,
  maxTaskWorkloadCount,
  invoiceDonutTotal,
  invoiceDonutGradient,
  revenueTrendPoints,
  revenueTrendArea,
} = dashboard
</script>

<template>
  <div class="space-y-6">
    <Skeleton v-if="isLoading" variant="stats" :count="4" />
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <RouterLink
        v-for="card in legalOpsCards"
        :key="card.label"
        :to="card.to"
        class="bw-card overflow-hidden transition-colors hover:border-border-strong"
      >
        <div class="h-1" :style="{ background: card.accent }" aria-hidden="true" />
        <div class="flex items-start justify-between gap-3 p-5 pb-4">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-foreground">{{ card.label }}</p>
            <p class="mt-3 text-3xl font-semibold tabular-nums text-foreground">
              {{ card.total }}
            </p>
            <p class="mt-0.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {{ card.totalLabel }}
            </p>
          </div>
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border bg-surface text-muted-foreground"
          >
            <component :is="card.icon" class="h-5 w-5" weight="fill" />
          </span>
        </div>
        <div class="flex divide-x divide-border border-t border-border">
          <div
            v-for="item in card.breakdown"
            :key="item.label"
            class="min-w-0 flex-1 px-4 py-3"
          >
            <p class="text-lg font-semibold tabular-nums text-foreground">
              {{ item.value }}
            </p>
            <p class="text-xs text-muted-foreground">{{ item.label }}</p>
          </div>
        </div>
      </RouterLink>
    </div>

    <Skeleton v-if="isLoading" class="h-64 rounded-xl" />
    <div v-else class="grid gap-6 lg:grid-cols-3">
      <section class="bw-card p-6 lg:col-span-2">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <PhBriefcase class="h-5 w-5 text-primary-700" weight="fill" />
            <div>
              <h2 class="font-semibold text-foreground">Cases by status</h2>
              <p class="text-sm text-muted-foreground">Practice-wide matter distribution.</p>
            </div>
          </div>
          <RouterLink to="/cases" class="text-sm font-medium text-primary-700 hover:underline">
            View all
          </RouterLink>
        </div>
        <div v-if="charts?.cases_by_status?.length" class="space-y-3">
          <div v-for="row in charts.cases_by_status" :key="row.status" class="space-y-1.5">
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
            <div class="h-2.5 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full transition-all"
                :style="{
                  width: `${(row.count / maxCaseStatusCount) * 100}%`,
                  backgroundColor: `var(${statusDotVar(row.status)})`,
                }"
              />
            </div>
          </div>
        </div>
        <EmptyState
          v-else
          :icon="PhBriefcase"
          title="No case data"
          message="Create matters to populate this chart."
        />
      </section>

      <section class="bw-card p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <PhFileText class="h-5 w-5 text-accent-600" weight="fill" />
            <div>
              <h2 class="font-semibold text-foreground">Filing pipeline</h2>
              <p class="text-sm text-muted-foreground">Status across court filings.</p>
            </div>
          </div>
          <RouterLink to="/filings" class="text-sm font-medium text-primary-700 hover:underline">
            View all
          </RouterLink>
        </div>
        <div v-if="charts?.filings_by_status?.length" class="space-y-3">
          <div v-for="row in charts.filings_by_status" :key="row.status" class="space-y-1.5">
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
          message="Court filings will appear here."
        />
      </section>
    </div>

    <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-3">
      <section class="bw-card p-6 lg:col-span-2">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <PhScales class="h-5 w-5 text-primary-700" weight="fill" />
            <div>
              <h2 class="font-semibold text-foreground">Motion workflow</h2>
              <p class="text-sm text-muted-foreground">Draft through filing-ready motions.</p>
            </div>
          </div>
          <RouterLink to="/motions" class="text-sm font-medium text-primary-700 hover:underline">
            View all
          </RouterLink>
        </div>
        <div v-if="charts?.motions_by_status?.length" class="space-y-3">
          <div v-for="row in charts.motions_by_status" :key="row.status" class="space-y-1.5">
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="inline-flex items-center gap-2 text-foreground">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: `var(${motionStatusDotVar(row.status)})` }"
                />
                {{ humanize(row.status) }}
              </span>
              <span class="tabular-nums text-muted-foreground">{{ row.count }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full"
                :style="{
                  width: `${(row.count / maxMotionStatusCount) * 100}%`,
                  backgroundColor: `var(${motionStatusDotVar(row.status)})`,
                }"
              />
            </div>
          </div>
        </div>
        <EmptyState
          v-else
          :icon="PhScales"
          title="No motions yet"
          message="Draft motions to track workflow here."
        />
      </section>

      <section class="bw-card p-6">
        <div class="mb-4 flex items-center gap-2">
          <PhCheckCircle class="h-5 w-5 text-status-success-fg" weight="fill" />
          <div>
            <h2 class="font-semibold text-foreground">Task workload</h2>
            <p class="text-sm text-muted-foreground">Open tasks across the firm.</p>
          </div>
        </div>
        <div v-if="charts?.task_workload?.length" class="space-y-3">
          <div v-for="row in charts.task_workload" :key="row.status" class="space-y-1.5">
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
          message="Firm task workload will show here."
        />
      </section>
    </div>

    <section v-if="!isLoading" class="bw-card">
      <div class="bw-card-header">
        <div>
          <h2 class="font-semibold text-foreground">Your recent matters</h2>
          <p class="text-sm text-muted-foreground">Latest cases across the practice.</p>
        </div>
        <RouterLink
          to="/cases"
          class="inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline"
        >
          All cases <PhArrowRight class="h-3.5 w-3.5" />
        </RouterLink>
      </div>
      <div
        v-if="data?.recent_cases?.length"
        class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3"
      >
        <RouterLink
          v-for="c in data.recent_cases.slice(0, 3)"
          :key="c.id"
          :to="`/cases/${c.id}`"
          class="bw-card overflow-hidden transition-colors hover:border-border-strong"
        >
          <div class="h-1 bg-action-teal" aria-hidden="true" />
          <div class="flex items-start justify-between gap-2 p-4">
            <div class="flex min-w-0 items-center gap-3">
              <AppAvatar :name="c.title" size="sm" tone="primary" />
              <p class="truncate text-sm font-semibold text-foreground">{{ c.title }}</p>
            </div>
            <PhDotsThree class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
          </div>
          <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
            <StatusBadge :status="c.status" />
            <span
              class="rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-muted-foreground"
            >
              Case #{{ c.id }}
            </span>
          </div>
          <div class="flex divide-x divide-border border-t border-border text-xs text-muted-foreground">
            <div class="flex flex-1 items-center gap-1.5 px-4 py-2.5">
              <PhFolderOpen class="h-3.5 w-3.5 shrink-0" />
              <span class="truncate">Matter</span>
            </div>
            <div class="flex flex-1 items-center gap-1.5 px-4 py-2.5">
              <PhBriefcase class="h-3.5 w-3.5 shrink-0" />
              <span class="truncate">{{ humanize(c.status) }}</span>
            </div>
          </div>
        </RouterLink>
      </div>
      <EmptyState
        v-else
        :icon="PhBriefcase"
        title="No cases yet"
        message="New cases will show up here."
        class="border-0 shadow-none"
      />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="bw-card lg:col-span-2">
        <div class="bw-card-header">
          <div>
            <h2 class="font-semibold text-foreground">My tasks</h2>
            <p class="text-sm text-muted-foreground">
              Open work assigned to you, soonest due first.
            </p>
          </div>
        </div>
        <Skeleton v-if="isLoading" variant="panel" :rows="4" />
        <div v-else-if="data?.my_tasks?.length" class="divide-y divide-border">
          <RouterLink
            v-for="task in data.my_tasks"
            :key="task.id"
            :to="task.case ? `/cases/${task.case.id}/tasks` : '/dashboard'"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-surface-muted"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-foreground">{{ task.title }}</p>
              <p v-if="task.case" class="truncate text-xs text-muted-foreground">
                {{ task.case.title }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <StatusBadge v-if="task.is_overdue" status="overdue" label="Overdue" />
              <StatusBadge :status="task.priority" :dot="false" />
              <span class="hidden text-xs tabular-nums text-muted-foreground sm:inline">
                {{ formatDue(task.due_at) }}
              </span>
            </div>
          </RouterLink>
        </div>
        <EmptyState
          v-else
          :icon="PhCheckCircle"
          title="You're all caught up"
          message="No open tasks are assigned to you right now."
        />
      </section>

      <div class="space-y-6">
        <section class="bw-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Next deadline
              </p>
              <template v-if="isLoadingDeadlines">
                <p class="mt-2 text-lg font-semibold text-foreground">…</p>
              </template>
              <template v-else-if="nextDeadline">
                <p class="mt-2 line-clamp-2 text-sm font-semibold text-foreground">
                  {{ nextDeadline.title }}
                </p>
                <p class="mt-1 text-xs tabular-nums text-muted-foreground">
                  {{ formatDeadlineDate(nextDeadline.starts_at) }}
                  <span v-if="deadlineDaysUntil !== null">
                    · {{ deadlineDaysUntil === 0 ? 'Today' : `${deadlineDaysUntil}d` }}
                  </span>
                </p>
                <RouterLink
                  v-if="nextDeadline.case"
                  :to="`/cases/${nextDeadline.case.id}/calendar`"
                  class="mt-2 inline-block text-xs font-medium text-primary-700 hover:underline"
                >
                  {{ nextDeadline.case.title }}
                </RouterLink>
              </template>
              <p v-else class="mt-2 text-sm text-muted-foreground">No upcoming deadlines</p>
            </div>
            <div
              class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-accent-700"
              :style="{
                background: nextDeadline
                  ? `conic-gradient(var(--color-accent-700) ${deadlineRingProgress}%, var(--color-muted) 0)`
                  : 'conic-gradient(var(--color-muted) 0% 100%)',
              }"
              role="img"
              :aria-label="
                nextDeadline
                  ? `Next deadline in ${deadlineDaysUntil ?? 0} days`
                  : 'No upcoming deadlines'
              "
            >
              <span class="flex h-10 w-10 items-center justify-center rounded-full bg-surface">
                <PhCalendarBlank
                  class="h-5 w-5"
                  :class="nextDeadline ? 'text-accent-700' : 'text-muted-foreground'"
                  weight="fill"
                />
              </span>
            </div>
          </div>
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

        <section class="bw-card">
          <div class="bw-card-header">
            <h2 class="font-semibold text-foreground">Upcoming</h2>
          </div>
          <Skeleton v-if="isLoading" variant="panel" :rows="3" />
          <div v-else-if="upcomingTasks.length" class="divide-y divide-border">
            <div
              v-for="task in upcomingTasks"
              :key="task.id"
              class="flex items-center gap-3 px-5 py-3"
            >
              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-surface text-muted-foreground"
              >
                <PhCalendarBlank class="h-4 w-4" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-foreground">{{ task.title }}</p>
                <p class="text-xs tabular-nums text-muted-foreground">
                  {{ formatDue(task.due_at) }}
                </p>
              </div>
            </div>
          </div>
          <p v-else class="px-5 py-6 text-sm text-muted-foreground">No upcoming deadlines.</p>
        </section>
      </div>
    </div>

    <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-3">
      <section class="bw-card p-6 lg:col-span-2">
        <div class="mb-4 flex items-center gap-2">
          <PhChartLineUp class="h-5 w-5 text-muted-foreground" />
          <div>
            <h2 class="text-sm font-semibold text-foreground">Revenue trend</h2>
            <p class="text-xs text-muted-foreground">Collected payments, last six months.</p>
          </div>
        </div>
        <div v-if="charts?.revenue_trend?.length" class="space-y-3">
          <svg
            viewBox="0 0 280 72"
            class="h-20 w-full text-primary-600"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <defs>
              <linearGradient id="revenue-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="currentColor" stop-opacity="0.25" />
                <stop offset="100%" stop-color="currentColor" stop-opacity="0.02" />
              </linearGradient>
            </defs>
            <polygon :points="revenueTrendArea" fill="url(#revenue-fill)" />
            <polyline
              :points="revenueTrendPoints"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <div class="flex justify-between text-xs text-muted-foreground">
            <span v-for="row in charts.revenue_trend" :key="row.month" class="tabular-nums">
              {{ row.label }}
            </span>
          </div>
        </div>
        <EmptyState
          v-else
          :icon="PhChartLineUp"
          title="No revenue data"
          message="Recorded payments will build this trend."
        />
      </section>

      <section class="bw-card p-6">
        <div class="mb-4 flex items-center gap-2">
          <PhCurrencyDollar class="h-5 w-5 text-muted-foreground" />
          <div>
            <h2 class="text-sm font-semibold text-foreground">Invoices</h2>
            <p class="text-xs text-muted-foreground">Paid, pending, and overdue.</p>
          </div>
        </div>
        <div
          v-if="charts?.invoices_by_status?.length && invoiceDonutTotal > 0"
          class="flex flex-col items-center gap-5 sm:flex-row sm:items-start"
        >
          <div
            class="relative flex h-28 w-28 shrink-0 items-center justify-center rounded-full"
            :style="{ background: invoiceDonutGradient }"
            role="img"
            :aria-label="`${invoiceDonutTotal} invoices by status`"
          >
            <div
              class="flex h-20 w-20 flex-col items-center justify-center rounded-full bg-surface text-center"
            >
              <span class="text-xl font-semibold tabular-nums text-foreground">
                {{ invoiceDonutTotal }}
              </span>
              <span class="text-xs text-muted-foreground">invoices</span>
            </div>
          </div>
          <ul class="min-w-0 flex-1 space-y-2">
            <li
              v-for="row in charts.invoices_by_status"
              :key="row.status"
              class="flex items-center justify-between gap-3 text-sm"
            >
              <span class="inline-flex items-center gap-2 text-foreground">
                <span
                  class="h-2 w-2 shrink-0 rounded-full"
                  :style="{ backgroundColor: `var(${invoiceStatusDotVar(row.status)})` }"
                />
                {{ humanize(row.status) }}
              </span>
              <span class="text-right text-xs">
                <span class="block font-medium tabular-nums text-foreground">{{ row.count }}</span>
                <span class="block tabular-nums text-muted-foreground">
                  {{ formatCurrency(row.amount) }}
                </span>
              </span>
            </li>
          </ul>
        </div>
        <EmptyState
          v-else
          :icon="PhCurrencyDollar"
          title="No invoices"
          message="Issued invoices will appear here."
        />
      </section>
    </div>

    <section v-if="!isLoading" class="bw-card">
      <div class="bw-card-header">
        <h2 class="font-semibold text-foreground">Recent clients</h2>
        <RouterLink
          to="/clients"
          class="inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline"
        >
          All clients <PhArrowRight class="h-3.5 w-3.5" />
        </RouterLink>
      </div>
      <div v-if="data?.recent_clients?.length" class="divide-y divide-border">
        <RouterLink
          v-for="client in data.recent_clients"
          :key="client.id"
          :to="`/clients/${client.id}`"
          class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-surface-muted"
        >
          <AppAvatar :name="client.name" size="sm" tone="accent" />
          <span class="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
            {{ client.name }}
          </span>
          <StatusBadge :status="client.status" />
        </RouterLink>
      </div>
      <EmptyState
        v-else
        :icon="PhUsers"
        title="No clients yet"
        message="New clients will show up here."
      />
    </section>
  </div>
</template>
