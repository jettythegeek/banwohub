<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhFolderOpen, PhMagnifyingGlass } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { evidenceApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { evidenceStatusDotVar, humanize } from '@/lib/status'
import type { EvidenceItem } from '@/types'

const items = ref<EvidenceItem[]>([])
const statuses = ref<string[]>([])
const statusFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const statusSummary = [
  { key: 'uploaded', label: 'Uploaded' },
  { key: 'under_review', label: 'Under review' },
  { key: 'approved', label: 'Approved' },
  { key: 'marked_as_exhibit', label: 'Exhibits' },
  { key: 'filed', label: 'Filed' },
] as const

const filteredItems = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return items.value
  return items.value.filter((item) =>
    [
      item.title,
      item.evidence_type,
      item.status,
      item.exhibit_number,
      item.source,
      item.legal_matter?.title,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const statusCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const item of items.value) {
    const key = item.status ?? 'uploaded'
    counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
})

const exhibitCount = computed(
  () => items.value.filter((item) => item.exhibit_number || item.status === 'marked_as_exhibit').length,
)

const caseCount = computed(() => {
  const ids = new Set(items.value.map((item) => item.legal_matter_id))
  return ids.size
})

function summaryDotStyle(status: string) {
  return { background: `var(${evidenceStatusDotVar(status)})` }
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await evidenceApi.list(statusFilter.value ? { status: statusFilter.value } : {})
    items.value = result.items
    statuses.value = result.statuses
  } catch (err) {
    error.value = formatApiError(err, 'Evidence is not available yet.')
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
      title="Evidence"
      subtitle="Manage case evidence, exhibit numbers, and chain of custody across matters."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total items</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : items.length }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Exhibits</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : exhibitCount }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Under review</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : statusCounts.under_review ?? 0 }}
        </p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cases covered</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ isLoading ? '—' : caseCount }}
        </p>
      </div>
    </div>

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Evidence status</p>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5">
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
            placeholder="Search evidence, exhibits, cases…"
            aria-label="Search evidence"
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

      <div v-else-if="filteredItems.length" class="divide-y divide-border">
        <article
          v-for="item in filteredItems"
          :key="item.id"
          class="flex flex-wrap items-center justify-between gap-3 px-6 py-4"
        >
          <div class="min-w-0 flex-1">
            <p class="font-medium text-foreground">{{ item.title }}</p>
            <p class="text-sm text-muted-foreground">
              <RouterLink
                v-if="item.legal_matter"
                :to="`/cases/${item.legal_matter_id}/evidence`"
                class="text-primary hover:underline"
              >
                {{ item.legal_matter.title }}
              </RouterLink>
              <span v-else>—</span>
              · {{ humanize(item.evidence_type) }}
              <span v-if="item.exhibit_number"> · Exhibit {{ item.exhibit_number }}</span>
            </p>
            <p class="text-xs text-muted-foreground">
              Obtained {{ formatDate(item.date_obtained) }}
              <span v-if="item.source"> · {{ item.source }}</span>
              <span v-if="item.uploader?.name"> · {{ item.uploader.name }}</span>
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span
              v-if="item.has_file"
              class="bw-badge bw-badge-neutral"
            >
              File attached
            </span>
            <StatusBadge :status="item.status" />
          </div>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="PhFolderOpen"
        title="No evidence found"
        :message="search || statusFilter ? 'Try adjusting your filters.' : 'Upload evidence from a case workspace Evidence tab.'"
      />

      <div
        v-if="filteredItems.length"
        class="border-t border-border bg-surface px-6 py-3 text-sm text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ filteredItems.length }}</span>
        item{{ filteredItems.length === 1 ? '' : 's' }}
        <span v-if="statusFilter"> · filtered by {{ humanize(statusFilter) }}</span>
      </div>
    </section>
  </div>
</template>
