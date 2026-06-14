<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhFileText, PhMagnifyingGlass } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { courtFilingsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { filingStatusDotVar, humanize } from '@/lib/status'
import type { CourtFiling } from '@/types'

const filings = ref<CourtFiling[]>([])
const statuses = ref<string[]>([])
const statusFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const statusSummary = [
  { key: 'draft', label: 'Draft' },
  { key: 'under_review', label: 'In review' },
  { key: 'ready_to_file', label: 'Ready to file' },
  { key: 'filed', label: 'Filed' },
  { key: 'correction_required', label: 'Corrections' },
  { key: 'completed', label: 'Completed' },
] as const

const filteredFilings = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return filings.value
  return filings.value.filter((filing) =>
    [filing.title, filing.court, filing.status, filing.legal_matter?.title, filing.court_reference_number]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const statusCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const filing of filings.value) {
    const key = filing.status ?? 'draft'
    counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
})

const pendingCourtCount = computed(
  () =>
    filings.value.filter((filing) =>
      ['filed', 'resubmitted', 'hearing_date_assigned'].includes(filing.status),
    ).length,
)

const correctionsCount = computed(
  () => filings.value.filter((filing) => filing.status === 'correction_required').length,
)

function summaryDotStyle(status: string) {
  return { background: `var(${filingStatusDotVar(status)})` }
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await courtFilingsApi.list(statusFilter.value ? { status: statusFilter.value } : {})
    filings.value = result.filings
    statuses.value = result.statuses
  } catch (err) {
    error.value = formatApiError(err, 'Court filings are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

watch(statusFilter, load)
onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Court filings"
      subtitle="Track filing lifecycle from draft through court response."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total filings</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : filings.length }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Awaiting court</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : pendingCourtCount }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Corrections needed</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : correctionsCount }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Completed</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : statusCounts.completed ?? 0 }}
        </p>
      </div>
    </div>

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Filing pipeline</p>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <button
          v-for="stage in statusSummary"
          :key="stage.key"
          type="button"
          class="flex items-center gap-3 rounded-lg p-1 text-left transition-colors hover:bg-surface-muted"
          :class="{ 'bw-row-selected ring-1 ring-[var(--accent-gold)]': statusFilter === stage.key }"
          @click="statusFilter = statusFilter === stage.key ? '' : stage.key"
        >
          <span
            class="h-9 w-1.5 shrink-0 rounded-full"
            :style="summaryDotStyle(stage.key)"
            aria-hidden="true"
          />
          <div>
            <p class="text-2xl font-semibold tabular-nums text-foreground">
              {{ isLoading ? '—' : statusCounts[stage.key] ?? 0 }}
            </p>
            <p class="text-xs text-muted-foreground">{{ stage.label }}</p>
          </div>
        </button>
      </div>
    </div>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
        <div class="relative min-w-[220px] flex-1">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
            aria-hidden="true"
          />
          <input
            v-model="search"
            type="search"
            class="bw-input pl-9"
            placeholder="Search filings, courts, cases…"
            aria-label="Search court filings"
          />
        </div>
        <select v-model="statusFilter" class="bw-select w-auto" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option v-for="status in statuses" :key="status" :value="status">
            {{ humanize(status) }}
          </option>
        </select>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-else-if="isLoading" variant="panel" :rows="4" />

      <div v-else-if="filteredFilings.length" class="divide-y divide-border">
        <article
          v-for="filing in filteredFilings"
          :key="filing.id"
          class="flex flex-wrap items-center justify-between gap-3 px-6 py-4"
        >
          <div class="min-w-0 flex-1">
            <p class="font-medium text-foreground">{{ filing.title }}</p>
            <p class="text-sm text-muted-foreground">
              <RouterLink
                v-if="filing.legal_matter"
                :to="`/cases/${filing.legal_matter_id}/filings`"
                class="text-primary hover:underline"
              >
                {{ filing.legal_matter.title }}
              </RouterLink>
              <span v-else>—</span>
              · {{ filing.court }}
            </p>
            <p class="text-xs text-muted-foreground">
              Filed {{ formatDate(filing.filing_date) }}
              <span v-if="filing.court_reference_number">
                · Ref {{ filing.court_reference_number }}
              </span>
              <span v-if="filing.filed_by_user?.name"> · {{ filing.filed_by_user.name }}</span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="bw-badge bw-badge-neutral">
              {{ humanize(filing.filing_method) }}
            </span>
            <StatusBadge :status="filing.status" />
          </div>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="PhFileText"
        title="No filings found"
        :message="search || statusFilter ? 'Try adjusting your filters.' : 'Create court forms from a case workspace, then track manual or e-filing status here.'"
      />

      <div
        v-if="filteredFilings.length"
        class="border-t border-border bg-surface px-6 py-3 text-sm text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ filteredFilings.length }}</span>
        filing{{ filteredFilings.length === 1 ? '' : 's' }}
        <span v-if="statusFilter"> · filtered by {{ humanize(statusFilter) }}</span>
      </div>
    </section>
  </div>
</template>
