<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhMagnifyingGlass, PhScales } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { motionsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize, motionStatusDotVar } from '@/lib/status'
import type { LegalMotion } from '@/types'

const motions = ref<LegalMotion[]>([])
const statuses = ref<string[]>([])
const statusFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const statusSummary = [
  { key: 'draft', label: 'Draft' },
  { key: 'review', label: 'In review' },
  { key: 'approved', label: 'Approved' },
  { key: 'filing_ready', label: 'Filing ready' },
] as const

const filteredMotions = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return motions.value
  return motions.value.filter((motion) =>
    [motion.title, motion.status, motion.motion_type, motion.legal_matter?.title]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const statusCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const motion of motions.value) {
    const key = motion.status ?? 'draft'
    counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
})

const linkedFilingsCount = computed(
  () => motions.value.filter((motion) => motion.court_filing_id).length,
)

function summaryDotStyle(status: string) {
  return { background: `var(${motionStatusDotVar(status)})` }
}

function motionTypeLabel(motion: LegalMotion) {
  return motion.template?.name ?? humanize(motion.motion_type ?? '')
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await motionsApi.list(statusFilter.value ? { status: statusFilter.value } : {})
    motions.value = result.motions
    statuses.value = result.statuses
  } catch (err) {
    error.value = formatApiError(err, 'Motions are not available yet.')
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
      title="Legal motions"
      subtitle="Draft, review, and approve motions before creating court filings."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total motions</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : motions.length }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">In review</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : statusCounts.review ?? 0 }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Approved</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : statusCounts.approved ?? 0 }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Linked to filings</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : linkedFilingsCount }}
        </p>
      </div>
    </div>

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Motion workflow</p>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
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
            placeholder="Search motions, cases…"
            aria-label="Search motions"
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

      <div v-else-if="filteredMotions.length" class="divide-y divide-border">
        <article
          v-for="motion in filteredMotions"
          :key="motion.id"
          class="flex flex-wrap items-center justify-between gap-3 px-6 py-4"
        >
          <div class="min-w-0 flex-1">
            <RouterLink :to="`/motions/${motion.id}`" class="font-medium text-foreground hover:text-primary">
              {{ motion.title }}
            </RouterLink>
            <p class="text-sm text-muted-foreground">
              <RouterLink
                v-if="motion.legal_matter"
                :to="`/cases/${motion.legal_matter_id}/motions`"
                class="text-primary hover:underline"
              >
                {{ motion.legal_matter.title }}
              </RouterLink>
              <span v-else>—</span>
              · {{ motionTypeLabel(motion) }}
            </p>
            <p class="text-xs text-muted-foreground">
              Updated {{ formatDate(motion.updated_at) }}
              <span v-if="motion.creator?.name"> · {{ motion.creator.name }}</span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span
              v-if="motion.court_filing_id"
              class="bw-badge bw-badge-accent"
            >
              Filing linked
            </span>
            <StatusBadge :status="motion.status" />
          </div>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="PhScales"
        title="No motions found"
        :message="search || statusFilter ? 'Try adjusting your filters.' : 'Create a motion from a case workspace to start drafting with templates and AI structure checks.'"
      />

      <div
        v-if="filteredMotions.length"
        class="border-t border-border bg-surface px-6 py-3 text-sm text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ filteredMotions.length }}</span>
        motion{{ filteredMotions.length === 1 ? '' : 's' }}
        <span v-if="statusFilter"> · filtered by {{ humanize(statusFilter) }}</span>
      </div>
    </section>
  </div>
</template>
