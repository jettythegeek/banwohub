import { computed, onMounted, ref } from 'vue'
import type { Component } from 'vue'
import {
  PhBriefcase,
  PhFileText,
  PhKanban,
  PhScales,
} from '@phosphor-icons/vue'
import { usePermissions } from '@/composables/usePermissions'
import { api, calendarHubApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { invoiceStatusDotVar } from '@/lib/status'
import { useAuthStore } from '@/stores/auth'
import type { CalendarHubItem, DashboardData, DashboardTask } from '@/types'

export type LegalOpsCard = {
  label: string
  total: number
  totalLabel: string
  icon: Component
  accent: string
  to: string
  breakdown: { value: number; label: string }[]
}

export function useDashboard() {
  const auth = useAuthStore()
  const { primaryRole } = usePermissions()

  const data = ref<DashboardData | null>(null)
  const nextDeadline = ref<CalendarHubItem | null>(null)
  const isLoading = ref(true)
  const isLoadingDeadlines = ref(true)
  const error = ref<string | null>(null)

  const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? 'there')

  const dashboardType = computed(
    () => data.value?.dashboard_type ?? inferDashboardType(primaryRole.value),
  )

  const dashboardSubtitle = computed(() => {
    if (dashboardType.value === 'admin') {
      return 'Cases, filings, motions, and project workload at a glance.'
    }
    if (dashboardType.value === 'lawyer') {
      return 'Your assigned matters, tasks, and client communication.'
    }
    return 'Your tasks, filings, and documents — focused on what needs you.'
  })

  const legalOpsCards = computed<LegalOpsCard[]>(() => {
    const ops = data.value?.legal_ops
    if (!ops) return []

    return [
      {
        label: 'Cases',
        total: ops.cases.total,
        totalLabel: dashboardType.value === 'admin' ? 'TOTAL' : 'ASSIGNED',
        icon: PhBriefcase,
        accent: 'var(--action-teal)',
        to: '/cases',
        breakdown: [
          { value: ops.cases.active, label: 'Active' },
          { value: ops.cases.new, label: 'New' },
          { value: ops.cases.assigned, label: 'Mine' },
        ],
      },
      {
        label: 'Filings',
        total: ops.filings.total,
        totalLabel: 'TOTAL',
        icon: PhFileText,
        accent: 'var(--accent-gold)',
        to: '/filings',
        breakdown: [
          { value: ops.filings.pending_court, label: 'At court' },
          { value: ops.filings.corrections, label: 'Corrections' },
          { value: ops.filings.completed, label: 'Done' },
        ],
      },
      {
        label: 'Motions',
        total: ops.motions.total,
        totalLabel: 'TOTAL',
        icon: PhScales,
        accent: 'var(--action-teal)',
        to: '/motions',
        breakdown: [
          { value: ops.motions.draft, label: 'Draft' },
          { value: ops.motions.review, label: 'In review' },
          { value: ops.motions.filing_ready, label: 'Ready' },
        ],
      },
      {
        label: 'Projects',
        total: ops.projects.open_matters,
        totalLabel: dashboardType.value === 'admin' ? 'OPEN MATTERS' : 'OPEN TASKS',
        icon: PhKanban,
        accent: 'var(--accent-gold)',
        to: '/legal-projects',
        breakdown: [
          { value: ops.projects.open_tasks, label: 'Tasks' },
          { value: ops.projects.overdue_tasks, label: 'Overdue' },
          { value: ops.projects.pending_milestones, label: 'Milestones' },
        ],
      },
    ]
  })

  const deadlineDaysUntil = computed(() => {
    if (!nextDeadline.value) return null
    const diff = new Date(nextDeadline.value.starts_at).getTime() - Date.now()
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
  })

  const deadlineRingProgress = computed(() => {
    const days = deadlineDaysUntil.value
    if (days === null) return 0
    const horizon = 90
    return Math.min(100, Math.round(((horizon - days) / horizon) * 100))
  })

  const upcomingTasks = computed<DashboardTask[]>(() =>
    (data.value?.my_tasks ?? [])
      .filter((task) => task.due_at)
      .slice()
      .sort(
        (a, b) =>
          new Date(a.due_at as string).getTime() -
          new Date(b.due_at as string).getTime(),
      )
      .slice(0, 5),
  )

  const charts = computed(() => data.value?.charts)

  const maxCaseStatusCount = computed(() => {
    const rows = charts.value?.cases_by_status ?? []
    return Math.max(...rows.map((row) => row.count), 1)
  })

  const maxFilingStatusCount = computed(() => {
    const rows = charts.value?.filings_by_status ?? []
    return Math.max(...rows.map((row) => row.count), 1)
  })

  const maxMotionStatusCount = computed(() => {
    const rows = charts.value?.motions_by_status ?? []
    return Math.max(...rows.map((row) => row.count), 1)
  })

  const maxTaskWorkloadCount = computed(() => {
    const rows = charts.value?.task_workload ?? []
    return Math.max(...rows.map((row) => row.count), 1)
  })

  const invoiceDonutTotal = computed(() => {
    const rows = charts.value?.invoices_by_status ?? []
    return rows.reduce((sum, row) => sum + row.count, 0)
  })

  const invoiceDonutGradient = computed(() => {
    const rows = charts.value?.invoices_by_status ?? []
    const total = invoiceDonutTotal.value || 1
    let offset = 0
    const stops = rows
      .filter((row) => row.count > 0)
      .map((row) => {
        const pct = (row.count / total) * 100
        const start = offset
        offset += pct
        return `var(${invoiceStatusDotVar(row.status)}) ${start}% ${offset}%`
      })
    return stops.length ? `conic-gradient(${stops.join(', ')})` : 'conic-gradient(var(--muted) 0% 100%)'
  })

  const revenueTrendMax = computed(() => {
    const rows = charts.value?.revenue_trend ?? []
    return Math.max(...rows.map((row) => row.amount), 1)
  })

  const revenueTrendPoints = computed(() => {
    const rows = charts.value?.revenue_trend ?? []
    if (!rows.length) return ''
    const width = 280
    const height = 72
    const step = rows.length > 1 ? width / (rows.length - 1) : 0
    return rows
      .map((row, index) => {
        const x = rows.length > 1 ? index * step : width / 2
        const y = height - (row.amount / revenueTrendMax.value) * (height - 8) - 4
        return `${x},${y}`
      })
      .join(' ')
  })

  const revenueTrendArea = computed(() => {
    const rows = charts.value?.revenue_trend ?? []
    if (!rows.length) return ''
    const width = 280
    const height = 72
    const step = rows.length > 1 ? width / (rows.length - 1) : 0
    const line = rows
      .map((row, index) => {
        const x = rows.length > 1 ? index * step : width / 2
        const y = height - (row.amount / revenueTrendMax.value) * (height - 8) - 4
        return `${x},${y}`
      })
      .join(' ')
    const lastX = rows.length > 1 ? (rows.length - 1) * step : width / 2
    return `0,${height} ${line} ${lastX},${height}`
  })

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

  async function loadDashboard() {
    if (!auth.user) {
      isLoading.value = false
      isLoadingDeadlines.value = false
      return
    }

    isLoading.value = true
    error.value = null

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
  }

  onMounted(loadDashboard)

  return {
    data,
    nextDeadline,
    isLoading,
    isLoadingDeadlines,
    error,
    firstName,
    dashboardType,
    dashboardSubtitle,
    legalOpsCards,
    deadlineDaysUntil,
    deadlineRingProgress,
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
    loadDashboard,
  }
}

function inferDashboardType(role: string): 'admin' | 'lawyer' | 'paralegal' {
  if (role === 'Firm Admin' || role === 'System Admin' || role === 'Partner') {
    return 'admin'
  }
  if (role === 'Paralegal') {
    return 'paralegal'
  }
  return 'lawyer'
}

export function formatDue(iso: string | null) {
  if (!iso) return 'No due date'
  return new Date(iso).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatDeadlineDate(iso: string) {
  return new Date(iso).toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
}
