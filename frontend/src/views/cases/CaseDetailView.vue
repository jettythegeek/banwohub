<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  PhCalendarBlank,
  PhCaretRight,
  PhChartLineUp,
  PhCurrencyDollar,
  PhScales,
  PhVault,
} from '@phosphor-icons/vue'
import CaseCalendarPanel from '@/components/cases/CaseCalendarPanel.vue'
import CaseConflictChecksPanel from '@/components/cases/CaseConflictChecksPanel.vue'
import CaseResearchPanel from '@/components/cases/CaseResearchPanel.vue'
import CaseFilingsPanel from '@/components/cases/CaseFilingsPanel.vue'
import CaseEvidencePanel from '@/components/cases/CaseEvidencePanel.vue'
import CaseBriefsPanel from '@/components/cases/CaseBriefsPanel.vue'
import CaseMotionsPanel from '@/components/cases/CaseMotionsPanel.vue'
import CaseEdiscoveryPanel from '@/components/cases/CaseEdiscoveryPanel.vue'
import CaseKnowledgePanel from '@/components/cases/CaseKnowledgePanel.vue'
import CaseProjectPanel from '@/components/cases/CaseProjectPanel.vue'
import CaseDocumentsPanel from '@/components/cases/CaseDocumentsPanel.vue'
import CaseNotesPanel from '@/components/cases/CaseNotesPanel.vue'
import CaseTasksPanel from '@/components/cases/CaseTasksPanel.vue'
import CaseInvoicesPanel from '@/components/cases/CaseInvoicesPanel.vue'
import CaseMessagesPanel from '@/components/cases/CaseMessagesPanel.vue'
import CaseExpensesPanel from '@/components/cases/CaseExpensesPanel.vue'
import CaseTrustLedgerPanel from '@/components/cases/CaseTrustLedgerPanel.vue'
import CaseTimePanel from '@/components/cases/CaseTimePanel.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { humanize } from '@/lib/status'
import { api, caseActivityApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import type { CaseActivity, CaseOverviewMetrics, LegalMatter } from '@/types'

const route = useRoute()
const router = useRouter()
const workspaceTabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'parties', label: 'Parties' },
  { key: 'activity', label: 'Activity' },
  { key: 'notes', label: 'Notes' },
  { key: 'tasks', label: 'Tasks' },
  { key: 'time', label: 'Time' },
  { key: 'expenses', label: 'Expenses' },
  { key: 'invoices', label: 'Invoices' },
  { key: 'messages', label: 'Messages' },
  { key: 'calendar', label: 'Calendar' },
  { key: 'documents', label: 'Documents' },
  { key: 'research', label: 'Research' },
  { key: 'knowledge', label: 'Knowledge' },
  { key: 'conflicts', label: 'Conflicts' },
  { key: 'filings', label: 'Filings' },
  { key: 'evidence', label: 'Evidence' },
  { key: 'briefs', label: 'Briefs' },
  { key: 'motions', label: 'Motions' },
  { key: 'e-discovery', label: 'E-discovery' },
  { key: 'project', label: 'Project' },
] as const
type WorkspaceTab = (typeof workspaceTabs)[number]['key']

const matter = ref<LegalMatter | null>(null)
const overviewMetrics = ref<CaseOverviewMetrics | null>(null)
const isLoadingOverview = ref(false)
const activity = ref<CaseActivity[]>([])
const tab = ref<WorkspaceTab>(currentRouteTab())
const isLoading = ref(true)
const isLoadingActivity = ref(false)
const error = ref<string | null>(null)

function isWorkspaceTab(value: unknown): value is WorkspaceTab {
  return (
    typeof value === 'string' && workspaceTabs.some((item) => item.key === value)
  )
}

function currentRouteTab(): WorkspaceTab {
  if (isWorkspaceTab(route.params.workspaceTab)) return route.params.workspaceTab
  if (isWorkspaceTab(route.query.tab)) return route.query.tab
  return 'overview'
}

function setTab(nextTab: WorkspaceTab) {
  tab.value = nextTab
  const caseId = matter.value?.id ?? route.params.id
  const routedTabs: WorkspaceTab[] = [
    'notes',
    'tasks',
    'time',
    'expenses',
    'invoices',
    'messages',
    'calendar',
    'documents',
    'research',
    'conflicts',
    'filings',
    'evidence',
    'briefs',
    'motions',
    'e-discovery',
  ]
  if (routedTabs.includes(nextTab)) {
    void router.replace(`/cases/${caseId}/${nextTab}`)
    return
  }
  void router.replace({
    path: `/cases/${caseId}`,
    query: nextTab === 'overview' ? {} : { tab: nextTab },
  })
}

onMounted(async () => {
  try {
    const { data } = await api.get<LegalMatter>(`/cases/${route.params.id}`)
    matter.value = data
    void loadOverviewMetrics(data.id)
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
})

async function loadOverviewMetrics(caseId: number) {
  isLoadingOverview.value = true
  try {
    const { data } = await api.get<CaseOverviewMetrics>(`/cases/${caseId}/overview-metrics`)
    overviewMetrics.value = data
  } catch {
    overviewMetrics.value = null
  } finally {
    isLoadingOverview.value = false
  }
}

const billingTrendMax = computed(() => {
  const rows = overviewMetrics.value?.billing_trend ?? []
  return Math.max(...rows.map((row) => row.amount), 1)
})

const billingTrendPoints = computed(() => {
  const rows = overviewMetrics.value?.billing_trend ?? []
  if (!rows.length) return ''
  const width = 240
  const height = 64
  const step = rows.length > 1 ? width / (rows.length - 1) : 0
  return rows
    .map((row, index) => {
      const x = rows.length > 1 ? index * step : width / 2
      const y = height - (row.amount / billingTrendMax.value) * (height - 8) - 4
      return `${x},${y}`
    })
    .join(' ')
})

const deadlineProgress = computed(() => {
  const count = overviewMetrics.value?.deadlines_count ?? 0
  if (!count) return 0
  return Math.min(100, Math.round((count / 10) * 100))
})

async function loadActivity(caseId: number) {
  isLoadingActivity.value = true
  try {
    activity.value = await caseActivityApi.list(caseId)
  } catch {
    activity.value = matter.value?.timeline ?? []
  } finally {
    isLoadingActivity.value = false
  }
}

watch(
  () => [route.params.workspaceTab, route.query.tab],
  () => {
    tab.value = currentRouteTab()
    if (tab.value === 'activity' && matter.value?.id) {
      void loadActivity(matter.value.id)
    }
  },
)

watch(tab, (nextTab) => {
  if (nextTab === 'activity' && matter.value?.id) {
    void loadActivity(matter.value.id)
  }
})

function clientName(m: LegalMatter) {
  if (m.client && 'name' in m.client) return m.client.name
  return '—'
}

function tabLabel(label: string) {
  return label.toUpperCase()
}

function formatDate(iso?: string) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}
</script>

<template>
  <div class="space-y-6">
    <Skeleton v-if="isLoading" variant="detail" />
    <p v-else-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <template v-else-if="matter">
      <nav class="flex items-center gap-1 text-sm text-muted-foreground">
        <RouterLink to="/cases" class="hover:text-foreground">Cases</RouterLink>
        <PhCaretRight class="h-3.5 w-3.5" />
        <span class="truncate text-foreground">{{ matter.title }}</span>
      </nav>

      <PageHeader :title="matter.title" :subtitle="clientName(matter)">
        <template #actions>
          <StatusBadge :status="matter.status" />
          <StatusBadge v-if="matter.stage" :status="matter.stage" />
          <RouterLink :to="`/cases/${matter.id}/edit`" class="bw-btn bw-btn-outline">
            Edit
          </RouterLink>
        </template>
      </PageHeader>

      <p v-if="matter.matter_number || matter.matter_stage" class="-mt-4 text-sm text-muted-foreground">
        <span v-if="matter.matter_number" class="tabular-nums">{{ matter.matter_number }}</span>
        <span v-if="matter.matter_stage">
          <span v-if="matter.matter_number"> · </span>{{ matter.matter_stage.replace(/_/g, ' ') }}
        </span>
      </p>

      <section class="bw-card overflow-hidden">
        <div class="overflow-x-auto px-6 pt-2">
          <nav class="bw-tabs min-w-max">
            <button
              v-for="t in workspaceTabs"
              :key="t.key"
              type="button"
              class="bw-tab"
              :class="{ 'bw-tab-active': tab === t.key }"
              @click="setTab(t.key)"
            >
              {{ tabLabel(t.label) }}
            </button>
          </nav>
        </div>

        <div :class="tab === 'tasks' ? 'p-0' : 'p-6'">
      <div v-if="tab === 'overview'" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="bw-card p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Unbilled revenue
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">
                  {{
                    isLoadingOverview
                      ? '…'
                      : formatCurrency(overviewMetrics?.unbilled_revenue ?? 0)
                  }}
                </p>
              </div>
              <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700"
              >
                <PhCurrencyDollar class="h-5 w-5" weight="fill" />
              </span>
            </div>
          </div>

          <div class="bw-card p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Trust balance
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">
                  {{
                    overviewMetrics?.trust_balance != null
                      ? formatCurrency(overviewMetrics.trust_balance)
                      : '—'
                  }}
                </p>
                <span
                  v-if="overviewMetrics?.trust_status === 'active'"
                  class="mt-2 inline-flex bw-badge bw-badge-success"
                >
                  Active
                </span>
                <span
                  v-else-if="overviewMetrics?.trust_status === 'empty'"
                  class="mt-2 inline-flex bw-badge bw-badge-warning"
                >
                  No transactions
                </span>
                <span
                  v-else-if="overviewMetrics?.trust_status === 'not_applicable' && !isLoadingOverview"
                  class="mt-2 inline-flex bw-badge bw-badge-neutral"
                >
                  Not a retainer matter
                </span>
              </div>
              <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent-50 text-accent-700"
              >
                <PhVault class="h-5 w-5" weight="fill" />
              </span>
            </div>
          </div>

          <div class="bw-card p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Upcoming deadlines
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">
                  {{ isLoadingOverview ? '…' : (overviewMetrics?.deadlines_count ?? 0) }}
                </p>
                <p
                  v-if="overviewMetrics?.next_deadline"
                  class="mt-1 text-xs text-muted-foreground"
                >
                  Next: {{ overviewMetrics.next_deadline.title }}
                </p>
              </div>
              <div
                class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-accent-700"
                :style="{
                  background: `conic-gradient(var(--color-accent-700) ${deadlineProgress}%, var(--color-muted) 0)`,
                }"
              >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-surface">
                  <PhCalendarBlank class="h-4 w-4 text-accent-700" weight="fill" />
                </span>
              </div>
            </div>
          </div>

          <div class="bw-card p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Case value
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-foreground">
                  {{
                    isLoadingOverview ? '…' : formatCurrency(overviewMetrics?.case_value ?? 0)
                  }}
                </p>
                <p class="mt-1 text-xs capitalize text-muted-foreground">
                  {{ overviewMetrics?.case_value_source?.replace(/_/g, ' ') ?? 'estimated' }}
                </p>
              </div>
              <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-status-success text-status-success-fg"
              >
                <PhScales class="h-5 w-5" weight="fill" />
              </span>
            </div>
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
          <div class="bw-card p-6 lg:col-span-2">
            <div class="mb-4 flex items-center gap-2">
              <PhChartLineUp class="h-5 w-5 text-primary-700" weight="fill" />
              <div>
                <h2 class="font-semibold text-foreground">Billing trend</h2>
                <p class="text-sm text-muted-foreground">Invoiced amounts over the last six months.</p>
              </div>
            </div>
            <div v-if="isLoadingOverview" class="text-sm text-muted-foreground">Loading trend…</div>
            <div v-else-if="overviewMetrics?.billing_trend?.length" class="space-y-3">
              <svg viewBox="0 0 240 64" class="h-16 w-full text-primary-700" aria-hidden="true">
                <polyline
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                  stroke-linecap="round"
                  :points="billingTrendPoints"
                />
              </svg>
              <div class="grid grid-cols-3 gap-2 text-xs text-muted-foreground sm:grid-cols-6">
                <span
                  v-for="row in overviewMetrics.billing_trend"
                  :key="row.month"
                  class="text-center tabular-nums"
                >
                  {{ row.label.split(' ')[0] }}
                </span>
              </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">No billed invoices in this period.</p>
          </div>

          <div class="bw-card p-6">
            <h2 class="mb-4 font-semibold text-foreground">Quick links</h2>
            <div class="space-y-2 text-sm">
              <button type="button" class="bw-btn bw-btn-outline w-full" @click="setTab('invoices')">
                View billing
              </button>
              <button type="button" class="bw-btn bw-btn-outline w-full" @click="setTab('calendar')">
                Open calendar
              </button>
              <button type="button" class="bw-btn bw-btn-outline w-full" @click="setTab('documents')">
                Documents
              </button>
            </div>
          </div>
        </div>

        <CaseTrustLedgerPanel
          :case-id="matter.id"
          :billing-type="matter.billing_type"
          @updated="loadOverviewMetrics(matter.id)"
        />

        <div class="grid gap-6 lg:grid-cols-3">
        <div class="bw-card p-6 lg:col-span-2">
          <h2 class="mb-4 font-semibold text-foreground">Matter details</h2>
          <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Pipeline stage
              </dt>
              <dd class="mt-1">
                <StatusBadge :status="matter.stage ?? 'lead'" />
              </dd>
            </div>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Matter stage
              </dt>
              <dd class="mt-1 capitalize text-foreground">
                {{ (matter.matter_stage ?? 'intake').replace(/_/g, ' ') }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Status
              </dt>
              <dd class="mt-1">
                <StatusBadge :status="matter.status" />
              </dd>
            </div>
            <div>
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Priority
              </dt>
              <dd class="mt-1">
                <StatusBadge :status="matter.priority" />
              </dd>
            </div>
            <div v-if="matter.lead_lawyer">
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Lead lawyer
              </dt>
              <dd class="mt-1 text-foreground">{{ matter.lead_lawyer.name }}</dd>
            </div>
            <div v-if="matter.matter_number">
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Matter number
              </dt>
              <dd class="mt-1 tabular-nums text-foreground">{{ matter.matter_number }}</dd>
            </div>
            <div v-if="matter.practice_area">
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Practice area
              </dt>
              <dd class="mt-1 text-foreground">{{ humanize(matter.practice_area) }}</dd>
            </div>
            <div v-if="matter.opened_at">
              <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Opened
              </dt>
              <dd class="mt-1 text-foreground">{{ matter.opened_at }}</dd>
            </div>
          </dl>
          <div v-if="matter.description" class="mt-6 border-t border-border pt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Description
            </p>
            <p class="mt-2 whitespace-pre-wrap text-sm text-foreground">
              {{ matter.description }}
            </p>
          </div>
        </div>

        <div class="bw-card p-6">
          <h2 class="mb-4 font-semibold text-foreground">Client</h2>
          <div v-if="matter.client && 'id' in matter.client">
            <RouterLink
              :to="`/clients/${matter.client.id}`"
              class="font-medium text-primary-700 hover:underline"
            >
              {{ clientName(matter) }}
            </RouterLink>
          </div>
          <p v-else class="text-sm text-muted-foreground">No client linked.</p>

          <div v-if="matter.tags?.length" class="mt-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Tags
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
              <span v-for="t in matter.tags" :key="t" class="bw-badge bw-badge-accent">
                {{ t }}
              </span>
            </div>
          </div>
        </div>
      </div>
      </div>

      <div v-else-if="tab === 'parties'" class="bw-card divide-y divide-border">
        <template v-if="matter.parties?.length">
          <div
            v-for="p in matter.parties"
            :key="p.id"
            class="flex items-center justify-between px-6 py-3.5 text-sm"
          >
            <span class="font-medium text-foreground">{{ p.name }}</span>
            <span class="bw-badge bw-badge-neutral">{{ humanize(p.party_type) }}</span>
          </div>
        </template>
        <p v-else class="px-6 py-12 text-center text-sm text-muted-foreground">
          No parties recorded.
        </p>
      </div>

      <div v-else-if="tab === 'activity'" class="bw-card divide-y divide-border">
        <p v-if="isLoadingActivity" class="px-6 py-12 text-center text-sm text-muted-foreground">
          Loading activity…
        </p>
        <template v-else-if="activity.length">
          <div v-for="ev in activity" :key="ev.id" class="px-6 py-3.5 text-sm">
            <p class="font-medium text-foreground">{{ ev.description }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">
              {{ formatDate(ev.created_at) }}
              <span v-if="ev.actor"> · {{ ev.actor.name }}</span>
              <span v-if="ev.log_name"> · {{ humanize(ev.log_name) }}</span>
            </p>
          </div>
        </template>
        <p v-else class="px-6 py-12 text-center text-sm text-muted-foreground">
          No activity yet.
        </p>
      </div>

      <CaseNotesPanel v-else-if="tab === 'notes'" :case-id="matter.id" />
      <CaseTasksPanel v-else-if="tab === 'tasks'" :case-id="matter.id" />
      <CaseTimePanel v-else-if="tab === 'time'" :case-id="matter.id" />
      <CaseExpensesPanel v-else-if="tab === 'expenses'" :case-id="matter.id" />
      <CaseInvoicesPanel
        v-else-if="tab === 'invoices'"
        :case-id="matter.id"
        :client-id="matter.client && 'id' in matter.client ? matter.client.id : null"
      />
      <CaseMessagesPanel
        v-else-if="tab === 'messages'"
        :case-id="matter.id"
        :client-id="matter.client && 'id' in matter.client ? matter.client.id : null"
      />
      <CaseCalendarPanel v-else-if="tab === 'calendar'" :case-id="matter.id" />
      <CaseDocumentsPanel v-else-if="tab === 'documents'" :case-id="matter.id" />
      <CaseResearchPanel v-else-if="tab === 'research'" :case-id="matter.id" />
      <CaseKnowledgePanel v-else-if="tab === 'knowledge'" :case-id="matter.id" />
      <CaseConflictChecksPanel v-else-if="tab === 'conflicts'" :case-id="matter.id" />
      <CaseFilingsPanel v-else-if="tab === 'filings'" :case-id="matter.id" />
      <CaseEvidencePanel v-else-if="tab === 'evidence'" :case-id="matter.id" />
      <CaseBriefsPanel v-else-if="tab === 'briefs'" :case-id="matter.id" />
      <CaseMotionsPanel v-else-if="tab === 'motions'" :case-id="matter.id" />
      <CaseEdiscoveryPanel v-else-if="tab === 'e-discovery'" :case-id="matter.id" />
      <CaseProjectPanel v-else-if="tab === 'project'" :case-id="matter.id" />
        </div>
      </section>
    </template>
  </div>
</template>
