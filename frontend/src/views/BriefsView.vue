<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhArticle, PhMagnifyingGlass } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { briefsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { briefStatusDotVar, humanize } from '@/lib/status'
import type { LegalBrief } from '@/types'

const briefs = ref<LegalBrief[]>([])
const statuses = ref<string[]>([])
const statusFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const statusSummary = [
  { key: 'draft', label: 'Draft' },
  { key: 'review', label: 'In review' },
  { key: 'final', label: 'Final' },
] as const

const filteredBriefs = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return briefs.value
  return briefs.value.filter((brief) =>
    [brief.title, brief.status, brief.brief_type, brief.legal_matter?.title]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const statusCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const brief of briefs.value) {
    const key = brief.status ?? 'draft'
    counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
})

const caseCount = computed(() => {
  const ids = new Set(briefs.value.map((brief) => brief.legal_matter_id))
  return ids.size
})

function summaryDotStyle(status: string) {
  return { background: `var(${briefStatusDotVar(status)})` }
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await briefsApi.list(statusFilter.value ? { status: statusFilter.value } : {})
    briefs.value = result.briefs
    statuses.value = result.statuses
  } catch (err) {
    error.value = formatApiError(err, 'Briefs are not available yet.')
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
      title="AI Brief Writer"
      subtitle="Intelligent litigation drafting — multi-type briefs, fact-to-brief automation, arguments, and export."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total briefs</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : briefs.length }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">In review</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : statusCounts.review ?? 0 }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Final</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : statusCounts.final ?? 0 }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Linked cases</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : caseCount }}
        </p>
      </div>
    </div>

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Brief workflow</p>
      <div class="mt-4 grid grid-cols-3 gap-4">
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
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <input
            v-model="search"
            type="search"
            class="bw-input pl-9"
            placeholder="Search briefs, cases…"
            aria-label="Search briefs"
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

      <div v-else-if="filteredBriefs.length" class="divide-y divide-border">
        <article
          v-for="brief in filteredBriefs"
          :key="brief.id"
          class="flex flex-wrap items-center justify-between gap-3 px-6 py-4"
        >
          <div class="min-w-0 flex-1">
            <RouterLink :to="`/briefs/${brief.id}`" class="font-medium text-foreground hover:text-primary">
              {{ brief.title }}
            </RouterLink>
            <p class="text-sm text-muted-foreground">
              <RouterLink
                v-if="brief.legal_matter"
                :to="`/cases/${brief.legal_matter_id}/briefs`"
                class="text-primary hover:underline"
              >
                {{ brief.legal_matter.title }}
              </RouterLink>
              <span v-else>—</span>
              <span v-if="brief.brief_type"> · {{ humanize(brief.brief_type) }}</span>
            </p>
            <p class="text-xs text-muted-foreground">
              Updated {{ formatDate(brief.updated_at) }}
            </p>
          </div>
          <StatusBadge :status="brief.status" />
        </article>
      </div>

      <EmptyState
        v-else
        :icon="PhArticle"
        title="No briefs found"
        :message="search || statusFilter ? 'Try adjusting your filters.' : 'Create a brief from a case workspace to start drafting with research and citations.'"
      />

      <div
        v-if="filteredBriefs.length"
        class="border-t border-border bg-surface px-6 py-3 text-sm text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ filteredBriefs.length }}</span>
        brief{{ filteredBriefs.length === 1 ? '' : 's' }}
        <span v-if="statusFilter"> · filtered by {{ humanize(statusFilter) }}</span>
      </div>
    </section>
  </div>
</template>
