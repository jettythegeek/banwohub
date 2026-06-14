<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'
import {
  PhArrowDown,
  PhArrowRight,
  PhArrowUp,
  PhBell,
  PhBriefcase,
  PhCalendarBlank,
  PhChartLineUp,
  PhCheckCircle,
  PhDotsThree,
  PhFileText,
  PhFolderOpen,
  PhHash,
  PhKanban,
  PhScales,
  PhTrendUp,
  PhUser,
  PhWarningCircle,
} from '@phosphor-icons/vue'
import type { ApexOptions } from 'apexcharts'
import type { Component } from 'vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { usePermissions } from '@/composables/usePermissions'
import { api, calendarHubApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import {
  CHART,
  aggregateCaseStatusGroups,
  baseAreaChartOptions,
  donutChartOptions,
  miniBarOptions,
  monthOverMonthTrend,
  radialGaugeOptions,
  semiGaugeOptions,
  sparklineOptions,
} from '@/lib/dashboard-charts'
import { humanize } from '@/lib/status'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import type { CalendarHubItem, DashboardData, DashboardTask } from '@/types'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const { primaryRole } = usePermissions()
const data = ref<DashboardData | null>(null)
const nextDeadline = ref<CalendarHubItem | null>(null)
const isLoading = ref(true)
const isLoadingDeadlines = ref(true)
const error = ref<string | null>(null)

const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? 'there')

const dashboardSubtitle = computed(() => {
  const role = primaryRole.value
  if (role === 'Firm Admin' || role === 'System Admin') {
    return 'Cases, filings, motions, and project workload at a glance.'
  }
  if (role === 'Lawyer' || role === 'Partner') {
    return 'Your caseload and legal operations at a glance.'
  }
  return 'Your work at a glance.'
})

type MetricCard = {
  key: string
  label: string
  total: number
  subtitle: string
  icon: Component
  to: string
  tint: string
  iconBg: string
  iconColor: string
  trend: { pct: number; positive: boolean }
  breakdown: { value: number; label: string; color: string }[]
}

const charts = computed(() => data.value?.charts)

const legalOpsCards = computed<MetricCard[]>(() => {
  const ops = data.value?.legal_ops
  if (!ops) return []

  const caseTrend = monthOverMonthTrend(charts.value?.case_activity_trend ?? [])
  const filingTrend = monthOverMonthTrend(charts.value?.filing_activity_trend ?? [])
  const motionReadyPct =
    ops.motions.total > 0
      ? Math.round((ops.motions.filing_ready / ops.motions.total) * 100)
      : 0
  const projectHealth =
    ops.projects.open_tasks > 0
      ? Math.round(
          ((ops.projects.open_tasks - ops.projects.overdue_tasks) / ops.projects.open_tasks) *
            100,
        )
      : 100

  return [
    {
      key: 'cases',
      label: 'Cases',
      total: ops.cases.total,
      subtitle: `${ops.cases.active} active`,
      icon: PhBriefcase,
      to: '/cases',
      tint: 'bg-primary-50',
      iconBg: 'bg-primary-100',
      iconColor: 'text-primary-700',
      trend: caseTrend,
      breakdown: [
        { value: ops.cases.active, label: 'Active', color: CHART.teal },
        { value: ops.cases.new, label: 'New', color: CHART.tealLight },
        { value: ops.cases.assigned, label: 'Mine', color: CHART.gold },
      ],
    },
    {
      key: 'filings',
      label: 'Filings',
      total: ops.filings.total,
      subtitle: `${ops.filings.pending_court} at court`,
      icon: PhFileText,
      to: '/filings',
      tint: 'bg-accent-100',
      iconBg: 'bg-accent-200',
      iconColor: 'text-accent-700',
      trend: filingTrend,
      breakdown: [
        { value: ops.filings.pending_court, label: 'Court', color: CHART.gold },
        { value: ops.filings.corrections, label: 'Fix', color: CHART.orange },
        { value: ops.filings.completed, label: 'Done', color: CHART.green },
      ],
    },
    {
      key: 'motions',
      label: 'Motions',
      total: ops.motions.total,
      subtitle: `${motionReadyPct}% filing ready`,
      icon: PhScales,
      to: '/motions',
      tint: 'bg-[#ECFDF5]',
      iconBg: 'bg-[#D1FAE5]',
      iconColor: 'text-[#047857]',
      trend: { pct: motionReadyPct, positive: motionReadyPct >= 50 },
      breakdown: [
        { value: ops.motions.draft, label: 'Draft', color: CHART.purpleLight },
        { value: ops.motions.review, label: 'Review', color: CHART.purple },
        { value: ops.motions.filing_ready, label: 'Ready', color: CHART.green },
      ],
    },
    {
      key: 'projects',
      label: 'Legal projects',
      total: ops.projects.open_matters,
      subtitle: `${ops.projects.open_tasks} open tasks`,
      icon: PhKanban,
      to: '/legal-projects',
      tint: 'bg-[#F5F3FF]',
      iconBg: 'bg-[#EDE9FE]',
      iconColor: 'text-[#6D28D9]',
      trend: { pct: projectHealth, positive: ops.projects.overdue_tasks === 0 },
      breakdown: [
        { value: ops.projects.open_tasks, label: 'Tasks', color: CHART.purple },
        { value: ops.projects.overdue_tasks, label: 'Overdue', color: CHART.orange },
        { value: ops.projects.pending_milestones, label: 'Milestones', color: CHART.gold },
      ],
    },
  ]
})

const caseSparklineSeries = computed(() => [
  {
    name: 'Cases',
    data: (charts.value?.case_activity_trend ?? []).map((p) => p.count),
  },
])

const filingBarSeries = computed(() => [
  {
    name: 'Filings',
    data: (() => {
      const ops = data.value?.legal_ops.filings
      if (!ops) return [0, 0, 0]
      return [ops.pending_court, ops.corrections, ops.completed]
    })(),
  },
])

const motionGaugePct = computed(() => {
  const ops = data.value?.legal_ops.motions
  if (!ops || ops.total === 0) return 0
  return Math.round((ops.filing_ready / ops.total) * 100)
})

const projectGaugePct = computed(() => {
  const ops = data.value?.legal_ops.projects
  if (!ops || ops.open_tasks === 0) return 100
  return Math.round(((ops.open_tasks - ops.overdue_tasks) / ops.open_tasks) * 100)
})

const caseActivitySeries = computed(() => [
  {
    name: 'New matters',
    data: (charts.value?.case_activity_trend ?? []).map((p) => p.count),
  },
])

const caseActivityCategories = computed(() =>
  (charts.value?.case_activity_trend ?? []).map((p) => p.label),
)

const caseActivityTrend = computed(() => monthOverMonthTrend(charts.value?.case_activity_trend ?? []))

const statusDonutGroups = computed(() =>
  aggregateCaseStatusGroups(charts.value?.cases_by_status ?? []),
)

const statusDonutSeries = computed(() => statusDonutGroups.value.map((g) => g.count))

const statusDonutOptions = computed<ApexOptions>(() =>
  donutChartOptions(
    statusDonutGroups.value.map((g) => g.label),
    statusDonutGroups.value.map((g) => g.color),
  ),
)

const filingPipelineSeries = computed(() => {
  const ops = data.value?.legal_ops.filings
  if (!ops) return []
  return [ops.pending_court, ops.corrections, ops.completed]
})

const filingPipelineOptions = computed<ApexOptions>(() =>
  semiGaugeOptions(['At court', 'Corrections', 'Completed'], [CHART.gold, CHART.orange, CHART.green]),
)

const motionDonutSeries = computed(() => {
  const rows = charts.value?.motions_by_status ?? []
  return rows.map((r) => r.count)
})

const motionDonutOptions = computed<ApexOptions>(() => {
  const rows = charts.value?.motions_by_status ?? []
  const colors = [CHART.purpleLight, CHART.purple, CHART.teal, CHART.green, CHART.orange]
  return donutChartOptions(
    rows.map((r) => humanize(r.status)),
    rows.map((_, i) => colors[i % colors.length] ?? CHART.teal),
  )
})

const taskWorkloadSeries = computed(() => [
  {
    name: 'Tasks',
    data: (charts.value?.task_workload ?? []).map((r) => r.count),
  },
])

const taskWorkloadOptions = computed<ApexOptions>(() => {
  const rows = charts.value?.task_workload ?? []
  const colors = [CHART.teal, CHART.gold, CHART.orange, CHART.purple, CHART.green]
  return {
    ...miniBarOptions(rows.map((_, i) => colors[i % colors.length] ?? CHART.teal), 120),
    plotOptions: {
      bar: {
        borderRadius: 6,
        columnWidth: '45%',
        distributed: true,
        horizontal: false,
      },
    },
    xaxis: {
      categories: rows.map((r) => humanize(r.status)),
      labels: { style: { colors: '#6B7280', fontSize: '11px' } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
  }
})

const caseActivityOptions = computed<ApexOptions>(() => baseAreaChartOptions(caseActivityCategories.value))

const deadlineDaysUntil = computed(() => {
  if (!nextDeadline.value) return null
  const diff = new Date(nextDeadline.value.starts_at).getTime() - Date.now()
  return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
})

const upcomingTasks = computed<DashboardTask[]>(() =>
  (data.value?.my_tasks ?? [])
    .filter((task) => task.due_at)
    .slice()
    .sort(
      (a, b) =>
        new Date(a.due_at as string).getTime() - new Date(b.due_at as string).getTime(),
    )
    .slice(0, 4),
)

function segmentedWidth(value: number, total: number): string {
  if (total <= 0) return '0%'
  return `${Math.max(4, (value / total) * 100)}%`
}

async function loadNextDeadline() {
  isLoadingDeadlines.value = true
  try {
    const from = new Date()
    const to = new Date()
    to.setDate(to.getDate() + 90)
    const hub = await calendarHubApi.list({
      category: 'deadlines',
      from: from.toISOString().slice(0, 10),
      to: to.toISOString().slice(0, 10),
    })
    nextDeadline.value = hub.meta.deadline_board[0] ?? hub.data[0] ?? null
  } catch {
    nextDeadline.value = null
  } finally {
    isLoadingDeadlines.value = false
  }
}

onMounted(async () => {
  if (!auth.user) {
    isLoading.value = false
    isLoadingDeadlines.value = false
    return
  }
  try {
    const [dashboardRes] = await Promise.all([
      api.get<DashboardData>('/dashboard'),
      loadNextDeadline(),
    ])
    data.value = dashboardRes.data
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
})

function formatDue(iso: string | null) {
  if (!iso) return 'No due date'
  return new Date(iso).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDeadlineDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      :title="`Welcome back, ${firstName}`"
      :subtitle="dashboardSubtitle"
    >
      <template #actions>
        <RouterLink to="/filings" class="bw-btn bw-btn-outline">
          New filing
        </RouterLink>
        <RouterLink to="/cases/new" class="bw-btn bw-btn-action">
          New case
        </RouterLink>
      </template>
    </PageHeader>

    <p
      v-if="error"
      class="rounded-xl border border-status-danger-border bg-status-danger px-4 py-3 text-sm text-destructive"
      role="alert"
    >
      {{ error }}
    </p>

    <!-- Colorful metric cards -->
    <Skeleton v-if="isLoading" variant="stats" :count="4" />
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <RouterLink
        v-for="card in legalOpsCards"
        :key="card.key"
        :to="card.to"
        class="group overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition-all hover:border-border-strong hover:shadow-md"
      >
        <div class="p-5" :class="card.tint">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-foreground">{{ card.label }}</p>
              <div class="mt-2 flex flex-wrap items-end gap-2">
                <p class="text-3xl font-bold tabular-nums tracking-tight text-foreground">
                  {{ card.total }}
                </p>
                <span
                  class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="
                    card.trend.positive
                      ? 'bg-[#DCFCE7] text-[#15803D]'
                      : 'bg-[#FEE2E2] text-[#B91C1C]'
                  "
                >
                  <PhArrowUp v-if="card.trend.positive" class="h-3 w-3" weight="bold" />
                  <PhArrowDown v-else class="h-3 w-3" weight="bold" />
                  {{ card.trend.pct }}%
                </span>
              </div>
              <p class="mt-0.5 text-xs text-muted-foreground">{{ card.subtitle }}</p>
            </div>
            <span
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm"
              :class="[card.iconBg, card.iconColor]"
            >
              <component :is="card.icon" class="h-5 w-5" weight="fill" />
            </span>
          </div>

          <!-- Mini chart per card -->
          <div class="mt-3" :class="card.key === 'cases' || card.key === 'filings' ? 'h-12' : 'h-16 overflow-hidden'">
            <VueApexCharts
              v-if="card.key === 'cases' && caseSparklineSeries[0]?.data.length"
              type="area"
              height="48"
              width="100%"
              :options="sparklineOptions(CHART.teal)"
              :series="caseSparklineSeries"
            />
            <VueApexCharts
              v-else-if="card.key === 'filings' && filingBarSeries[0]?.data.some((v) => v > 0)"
              type="bar"
              height="48"
              width="100%"
              :options="miniBarOptions([CHART.gold, CHART.orange, CHART.green])"
              :series="filingBarSeries"
            />
            <VueApexCharts
              v-else-if="card.key === 'motions'"
              type="radialBar"
              height="64"
              width="100%"
              :options="radialGaugeOptions(CHART.green, 64)"
              :series="[motionGaugePct]"
            />
            <VueApexCharts
              v-else-if="card.key === 'projects'"
              type="radialBar"
              height="64"
              width="100%"
              :options="radialGaugeOptions(CHART.purple, 64)"
              :series="[projectGaugePct]"
            />
          </div>
        </div>

        <!-- Segmented breakdown -->
        <div class="border-t border-border/60 bg-surface px-4 py-3">
          <div class="mb-2 flex h-2 overflow-hidden rounded-full bg-neutral-200">
            <span
              v-for="seg in card.breakdown"
              :key="seg.label"
              class="h-full transition-all"
              :style="{
                width: segmentedWidth(seg.value, card.total || 1),
                backgroundColor: seg.color,
              }"
            />
          </div>
          <div class="flex divide-x divide-border">
            <div
              v-for="item in card.breakdown"
              :key="item.label"
              class="min-w-0 flex-1 px-2 first:pl-0 last:pr-0"
            >
              <p class="text-sm font-semibold tabular-nums text-foreground">{{ item.value }}</p>
              <p class="text-[11px] text-muted-foreground">{{ item.label }}</p>
            </div>
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- Hero charts row -->
    <Skeleton v-if="isLoading" class="h-80 rounded-2xl" />
    <div v-else class="grid gap-6 lg:grid-cols-3">
      <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm lg:col-span-2">
        <div class="mb-2 flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                <PhChartLineUp class="h-5 w-5" weight="fill" />
              </span>
              <div>
                <h2 class="font-semibold text-foreground">Case activity</h2>
                <p class="text-sm text-muted-foreground">New matters opened, last 6 months</p>
              </div>
            </div>
          </div>
          <span
            v-if="caseActivityTrend.pct > 0"
            class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold"
            :class="
              caseActivityTrend.positive
                ? 'bg-[#DCFCE7] text-[#15803D]'
                : 'bg-[#FEE2E2] text-[#B91C1C]'
            "
          >
            <PhTrendUp class="h-3.5 w-3.5" weight="bold" />
            {{ caseActivityTrend.positive ? '+' : '-' }}{{ caseActivityTrend.pct }}% vs last month
          </span>
        </div>
        <VueApexCharts
          v-if="caseActivitySeries[0]?.data.length"
          type="area"
          height="280"
          :options="caseActivityOptions"
          :series="caseActivitySeries"
        />
        <EmptyState
          v-else
          :icon="PhBriefcase"
          title="No case activity yet"
          message="New matters will populate this chart."
        />
      </section>

      <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="mb-2 flex items-center gap-2">
          <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent-100 text-accent-700">
            <PhBriefcase class="h-5 w-5" weight="fill" />
          </span>
          <div>
            <h2 class="font-semibold text-foreground">Status breakdown</h2>
            <p class="text-sm text-muted-foreground">Open, pending & closed</p>
          </div>
        </div>
        <VueApexCharts
          v-if="statusDonutSeries.length && statusDonutSeries.some((v) => v > 0)"
          type="donut"
          height="300"
          :options="statusDonutOptions"
          :series="statusDonutSeries"
        />
        <EmptyState
          v-else
          :icon="PhBriefcase"
          title="No status data"
          message="Create matters to see distribution."
        />
      </section>
    </div>

    <!-- Secondary analytics row -->
    <div v-if="!isLoading" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="mb-2 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-100 text-accent-700">
              <PhFileText class="h-4 w-4" weight="fill" />
            </span>
            <h2 class="font-semibold text-foreground">Filing pipeline</h2>
          </div>
          <RouterLink to="/filings" class="text-xs font-medium text-primary-700 hover:underline">
            View all
          </RouterLink>
        </div>
        <VueApexCharts
          v-if="filingPipelineSeries.some((v) => v > 0)"
          type="donut"
          height="220"
          :options="filingPipelineOptions"
          :series="filingPipelineSeries"
        />
        <EmptyState
          v-else
          :icon="PhFileText"
          title="No filings"
          message="Court filings will appear here."
        />
      </section>

      <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm">
        <div class="mb-2 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#EDE9FE] text-[#6D28D9]">
              <PhScales class="h-4 w-4" weight="fill" />
            </span>
            <h2 class="font-semibold text-foreground">Motion workflow</h2>
          </div>
          <RouterLink to="/motions" class="text-xs font-medium text-primary-700 hover:underline">
            View all
          </RouterLink>
        </div>
        <VueApexCharts
          v-if="motionDonutSeries.length && motionDonutSeries.some((v) => v > 0)"
          type="donut"
          height="220"
          :options="motionDonutOptions"
          :series="motionDonutSeries"
        />
        <EmptyState
          v-else
          :icon="PhScales"
          title="No motions"
          message="Draft motions to track workflow."
        />
      </section>

      <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm md:col-span-2 lg:col-span-1">
        <div class="mb-2 flex items-center gap-2">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#ECFDF5] text-[#047857]">
            <PhCheckCircle class="h-4 w-4" weight="fill" />
          </span>
          <h2 class="font-semibold text-foreground">Task workload</h2>
        </div>
        <VueApexCharts
          v-if="taskWorkloadSeries[0]?.data.length"
          type="bar"
          height="220"
          :options="taskWorkloadOptions"
          :series="taskWorkloadSeries"
        />
        <EmptyState
          v-else
          :icon="PhCheckCircle"
          title="No open tasks"
          message="Task workload will show here."
        />
      </section>
    </div>

    <!-- Recent matters + sidebar -->
    <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-3">
      <section class="rounded-2xl border border-border bg-surface shadow-sm lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
          <div>
            <h2 class="font-semibold text-foreground">Your recent matters</h2>
            <p class="text-sm text-muted-foreground">Latest cases across the practice</p>
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
          class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3"
        >
          <RouterLink
            v-for="c in data.recent_cases.slice(0, 3)"
            :key="c.id"
            :to="`/cases/${c.id}`"
            class="overflow-hidden rounded-xl border border-border bg-surface transition-all hover:border-primary-300 hover:shadow-md"
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
            </div>
            <div class="grid grid-cols-2 gap-px border-t border-border bg-border text-xs text-muted-foreground">
              <div class="flex items-center gap-1.5 bg-surface px-4 py-2.5">
                <PhFolderOpen class="h-3.5 w-3.5 shrink-0 text-primary-600" />
                <span class="truncate">Matter</span>
              </div>
              <div class="flex items-center gap-1.5 bg-surface px-4 py-2.5">
                <PhUser class="h-3.5 w-3.5 shrink-0 text-accent-600" />
                <span class="truncate">{{ c.client?.name ?? 'Client' }}</span>
              </div>
              <div class="flex items-center gap-1.5 bg-surface px-4 py-2.5">
                <PhHash class="h-3.5 w-3.5 shrink-0" />
                <span class="truncate">#{{ c.id }}</span>
              </div>
              <div class="flex items-center gap-1.5 bg-surface px-4 py-2.5">
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

      <div class="space-y-4">
        <!-- Next deadline -->
        <section class="rounded-2xl border border-border bg-gradient-to-br from-accent-50 to-surface p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-accent-700">Next deadline</p>
          <template v-if="isLoadingDeadlines">
            <p class="mt-2 text-lg font-semibold text-foreground">…</p>
          </template>
          <template v-else-if="nextDeadline">
            <p class="mt-2 line-clamp-2 text-sm font-semibold text-foreground">
              {{ nextDeadline.title }}
            </p>
            <p class="mt-1 text-xs tabular-nums text-muted-foreground">
              {{ formatDeadlineDate(nextDeadline.starts_at) }}
              · {{ deadlineDaysUntil === 0 ? 'Today' : `${deadlineDaysUntil}d` }}
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
        </section>

        <!-- My tasks -->
        <section class="rounded-2xl border border-border bg-surface shadow-sm">
          <div class="border-b border-border px-5 py-3">
            <h2 class="font-semibold text-foreground">My tasks</h2>
          </div>
          <div v-if="data?.my_tasks?.length" class="divide-y divide-border">
            <RouterLink
              v-for="task in data.my_tasks.slice(0, 4)"
              :key="task.id"
              :to="task.case ? `/cases/${task.case.id}/tasks` : '/dashboard'"
              class="flex items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-surface-muted"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-foreground">{{ task.title }}</p>
                <p v-if="task.case" class="truncate text-xs text-muted-foreground">
                  {{ task.case.title }}
                </p>
              </div>
              <StatusBadge v-if="task.is_overdue" status="overdue" label="Overdue" />
            </RouterLink>
          </div>
          <EmptyState
            v-else
            :icon="PhCheckCircle"
            title="All caught up"
            message="No open tasks assigned."
            class="border-0 py-6 shadow-none"
          />
        </section>

        <!-- Notifications -->
        <section class="rounded-2xl border border-border bg-surface shadow-sm">
          <div class="flex items-center justify-between border-b border-border px-5 py-3">
            <h2 class="font-semibold text-foreground">Notifications</h2>
            <RouterLink to="/notifications" class="text-xs font-medium text-primary-700 hover:underline">
              View all
            </RouterLink>
          </div>
          <div class="flex items-center gap-3 px-5 py-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
              <PhBell class="h-5 w-5" weight="fill" />
            </span>
            <p class="text-sm text-foreground">
              <span class="font-bold tabular-nums">{{ notifications.unread }}</span>
              unread
            </p>
          </div>
        </section>

        <!-- Upcoming due dates -->
        <section v-if="upcomingTasks.length" class="rounded-2xl border border-border bg-surface shadow-sm">
          <div class="border-b border-border px-5 py-3">
            <h2 class="font-semibold text-foreground">Upcoming due dates</h2>
          </div>
          <div class="divide-y divide-border">
            <div
              v-for="task in upcomingTasks"
              :key="task.id"
              class="flex items-center gap-3 px-5 py-3"
            >
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent-100 text-accent-700">
                <PhCalendarBlank class="h-4 w-4" weight="fill" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-foreground">{{ task.title }}</p>
                <p class="text-xs tabular-nums text-muted-foreground">{{ formatDue(task.due_at) }}</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Role-specific alerts -->
        <section
          v-if="data?.documents_attention?.length"
          class="rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] p-5 shadow-sm"
        >
          <div class="flex items-center gap-2 text-[#B45309]">
            <PhWarningCircle class="h-5 w-5" weight="fill" />
            <h2 class="font-semibold">Documents need attention</h2>
          </div>
          <ul class="mt-3 space-y-2">
            <li v-for="doc in data.documents_attention.slice(0, 3)" :key="doc.id">
              <RouterLink
                :to="doc.case ? `/cases/${doc.case.id}/documents` : '/evidence'"
                class="text-sm text-foreground hover:underline"
              >
                {{ doc.name }}
              </RouterLink>
            </li>
          </ul>
        </section>

        <section
          v-if="data?.pending_approvals?.length"
          class="rounded-2xl border border-border bg-surface p-5 shadow-sm"
        >
          <h2 class="font-semibold text-foreground">Pending approvals</h2>
          <ul class="mt-3 space-y-2">
            <li
              v-for="item in data.pending_approvals.slice(0, 3)"
              :key="item.id"
              class="text-sm text-muted-foreground"
            >
              {{ humanize(item.subject_type) }} · {{ item.submitter?.name ?? 'Submitted' }}
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>
