<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ConflictReportModal from '@/components/conflict/ConflictReportModal.vue'
import { PhMagnifyingGlass, PhScales } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { conflictChecksApi } from '@/lib/api'
import { conflictStatusDotVar } from '@/lib/status'
import { formatApiError } from '@/lib/api-error'
import type { ConflictCheck } from '@/types'

const route = useRoute()
const checks = ref<ConflictCheck[]>([])
const reportCheck = ref<ConflictCheck | null>(null)
const statusFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const updatingId = ref<number | null>(null)
const error = ref<string | null>(null)

const statusSummary = [
  { key: 'not_started', label: 'Not started' },
  { key: 'in_review', label: 'In review' },
  { key: 'potential_conflict_found', label: 'Potential conflict' },
  { key: 'cleared', label: 'Cleared' },
  { key: 'rejected', label: 'Rejected' },
] as const

const filteredChecks = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return checks.value
  return checks.value.filter((check) =>
    [termsLabel(check), check.case?.title, check.status, check.decision]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const statusCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const check of checks.value) {
    const key = check.status ?? 'not_started'
    counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
})

function summaryDotStyle(status: string) {
  return { background: `var(${conflictStatusDotVar(status)})` }
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    checks.value = await conflictChecksApi.list(undefined, statusFilter.value)
  } catch (err) {
    error.value = formatApiError(err, 'Conflict checks are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function reviewCheck(check: ConflictCheck, status: string) {
  updatingId.value = check.id
  error.value = null
  try {
    const updated = await conflictChecksApi.update(check.id, {
      status,
      decision: status,
    })
    checks.value = checks.value.map((item) => (item.id === updated.id ? updated : item))
  } catch (err) {
    error.value = formatApiError(err, 'We could not update this conflict check.')
  } finally {
    updatingId.value = null
  }
}

function termsLabel(check: ConflictCheck) {
  return check.search_terms.join(', ')
}

function matchCount(check: ConflictCheck) {
  return Object.values(check.matches ?? {}).reduce((count, group) => {
    return count + (Array.isArray(group) ? group.length : 0)
  }, 0)
}

function formatDate(iso?: string | null) {
  if (!iso) return 'Not reviewed'
  return new Date(iso).toLocaleString()
}

function openReport(check: ConflictCheck) {
  reportCheck.value = check
}

async function exportReport(format: 'csv' | 'html') {
  if (!reportCheck.value) return
  try {
    await conflictChecksApi.export(reportCheck.value.id, format)
  } catch (err) {
    error.value = formatApiError(err, 'We could not export this report.')
  }
}

watch(statusFilter, load)
onMounted(async () => {
  await load()
  const checkId = Number(route.query.check)
  if (checkId) {
    const found = checks.value.find((item) => item.id === checkId)
    if (found) reportCheck.value = found
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Conflict checks"
      subtitle="Review conflict search results across your practice."
    />

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Review status</p>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5">
        <button
          v-for="stage in statusSummary"
          :key="stage.key"
          type="button"
          class="flex items-center gap-3 rounded-lg text-left transition-colors hover:bg-surface-muted"
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
          />
          <input
            v-model="search"
            type="search"
            placeholder="Search names, cases, status, or decision…"
            class="bw-input pl-9"
            aria-label="Search conflict checks"
          />
        </div>
        <select v-model="statusFilter" class="bw-select w-auto" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option value="not_started">Not started</option>
          <option value="in_review">In review</option>
          <option value="potential_conflict_found">Potential conflict</option>
          <option value="cleared">Cleared</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-else-if="isLoading" variant="panel" :rows="4" />

      <div v-else-if="filteredChecks.length" class="divide-y divide-border">
        <article v-for="check in filteredChecks" :key="check.id" class="space-y-3 px-6 py-4 text-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="font-medium text-foreground">{{ termsLabel(check) }}</h2>
              <p class="text-muted-foreground">
                {{ check.case?.title || 'General check' }} ·
                <span class="tabular-nums">{{ matchCount(check) }}</span> matches
              </p>
            </div>
            <StatusBadge :status="check.status" />
          </div>
          <p v-if="check.notes" class="whitespace-pre-wrap text-foreground">{{ check.notes }}</p>
          <p class="text-xs text-muted-foreground">
            Reviewed {{ formatDate(check.reviewed_at) }}
            <span v-if="check.reviewer"> by {{ check.reviewer.name }}</span>
          </p>
          <div class="flex flex-wrap gap-2">
            <RouterLink
              v-if="check.case"
              :to="`/cases/${check.case.id}/conflicts`"
              class="bw-btn bw-btn-outline bw-btn-sm"
            >
              Open case
            </RouterLink>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              @click="openReport(check)"
            >
              View report
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :disabled="updatingId === check.id"
              @click="reviewCheck(check, 'cleared')"
            >
              Clear
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :disabled="updatingId === check.id"
              @click="reviewCheck(check, 'in_review')"
            >
              In review
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-danger bw-btn-sm"
              :disabled="updatingId === check.id"
              @click="reviewCheck(check, 'rejected')"
            >
              Reject
            </button>
          </div>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="PhScales"
        title="No conflict checks found"
        :message="search || statusFilter ? 'Try adjusting your filters.' : 'Conflict checks run from a case will appear here.'"
      />
    </section>

    <ConflictReportModal
      :check="reportCheck"
      @close="reportCheck = null"
      @export-csv="exportReport('csv')"
      @export-html="exportReport('html')"
    />
  </div>
</template>
