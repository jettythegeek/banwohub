<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhChartLineUp, PhLightbulb } from '@phosphor-icons/vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { legalAnalyticsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { LegalAnalyticsDashboard, LegalAnalyticsHint } from '@/types'

const dashboard = ref<LegalAnalyticsDashboard | null>(null)
const hints = ref<LegalAnalyticsHint[]>([])
const disclaimer = ref('')
const fromDate = ref('')
const toDate = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const maxStatusCount = computed(() => {
  const rows = dashboard.value?.outcomes.by_status ?? []
  return Math.max(...rows.map((row) => row.count), 1)
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const filters: { from_date?: string; to_date?: string } = {}
    if (fromDate.value) filters.from_date = fromDate.value
    if (toDate.value) filters.to_date = toDate.value
    const [dash, hintRes] = await Promise.all([
      legalAnalyticsApi.dashboard(filters),
      legalAnalyticsApi.hints(),
    ])
    dashboard.value = dash
    hints.value = hintRes.hints
    disclaimer.value = hintRes.disclaimer || dash.disclaimer
  } catch (err) {
    error.value = formatApiError(err, 'Analytics are not available yet.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Legal analytics" subtitle="Case duration, outcomes, workload, and AI planning hints.">
      <template #actions>
        <input v-model="fromDate" type="date" class="bw-input" @change="load" />
        <input v-model="toDate" type="date" class="bw-input" @change="load" />
      </template>
    </PageHeader>

    <AiDisclaimerBanner
      v-if="disclaimer"
      :message="disclaimer"
      label="Analytics disclaimer"
    />

    <p v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
      {{ error }}
    </p>

    <div v-if="isLoading" class="grid gap-4 lg:grid-cols-2">
      <Skeleton class="h-48 rounded-xl" />
      <Skeleton class="h-48 rounded-xl" />
    </div>

    <template v-else-if="dashboard">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Avg case duration</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-foreground">
            {{ dashboard.case_duration.average_days }}d
          </p>
          <p class="mt-1 text-xs text-muted-foreground">{{ dashboard.case_duration.sample_size }} matters</p>
        </div>
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Open matters</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-foreground">{{ dashboard.outcomes.open_count }}</p>
        </div>
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Closed matters</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-foreground">
            {{ dashboard.outcomes.closed_count }}
          </p>
        </div>
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Overdue tasks</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-destructive">
            {{ dashboard.workload.overdue_tasks }}
          </p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <div class="bw-card p-6">
          <div class="mb-4 flex items-center gap-2">
            <PhChartLineUp class="h-5 w-5 text-primary-700" />
            <h2 class="font-semibold text-foreground">Outcomes by status</h2>
          </div>
          <div v-if="dashboard.outcomes.by_status.length" class="space-y-3">
            <div v-for="row in dashboard.outcomes.by_status" :key="row.status">
              <div class="mb-1 flex justify-between text-sm">
                <span class="text-foreground">{{ humanize(row.status) }}</span>
                <span class="tabular-nums text-muted-foreground">{{ row.count }}</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-primary-600"
                  :style="{ width: `${(row.count / maxStatusCount) * 100}%` }"
                />
              </div>
            </div>
          </div>
          <EmptyState v-else title="No case data" description="Create matters to populate analytics." />
        </div>

        <div class="bw-card p-6">
          <div class="mb-4 flex items-center gap-2">
            <PhLightbulb class="h-5 w-5 text-accent-600" />
            <h2 class="font-semibold text-foreground">AI planning hints</h2>
          </div>
          <div v-if="hints.length" class="space-y-3">
            <div
              v-for="(hint, index) in hints"
              :key="index"
              class="rounded-lg border border-border bg-surface p-4"
            >
              <p class="font-medium text-foreground">{{ hint.title }}</p>
              <p class="mt-1 text-sm text-muted-foreground">{{ hint.message }}</p>
            </div>
          </div>
          <EmptyState v-else title="No hints" description="Hints appear when firm metrics suggest attention." />
        </div>
      </div>

      <div class="bw-card p-6">
        <h2 class="mb-4 font-semibold text-foreground">Case type performance</h2>
        <div v-if="dashboard.case_type_performance.length" class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th class="px-3 py-2">Case type</th>
                <th class="px-3 py-2">Total</th>
                <th class="px-3 py-2">Closed</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in dashboard.case_type_performance"
                :key="row.case_type"
                class="border-b border-border/60"
              >
                <td class="px-3 py-2 text-foreground">{{ humanize(row.case_type) }}</td>
                <td class="px-3 py-2 tabular-nums">{{ row.count }}</td>
                <td class="px-3 py-2 tabular-nums">{{ row.closed_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState v-else title="No case types" description="Tag matters with case types for breakdowns." />
      </div>
    </template>
  </div>
</template>
