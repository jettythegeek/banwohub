<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { PhDownloadSimple } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { reportsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import type { ReportSummary } from '@/types'

const summary = ref<ReportSummary | null>(null)
const fromDate = ref('')
const toDate = ref('')
const isLoading = ref(true)
const isExporting = ref(false)
const error = ref<string | null>(null)

function formatHours(hours: number) {
  return `${hours.toFixed(1)} h`
}

const maxCaseCount = computed(() => {
  const rows = summary.value?.cases.by_status ?? []
  return Math.max(...rows.map((row) => row.count), 1)
})

const maxLawyerMinutes = computed(() => {
  const rows = summary.value?.time_by_lawyer ?? []
  return Math.max(...rows.map((row) => row.billable_minutes), 1)
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const filters: Record<string, string> = {}
    if (fromDate.value) filters.from_date = fromDate.value
    if (toDate.value) filters.to_date = toDate.value
    summary.value = await reportsApi.summary(filters)
  } catch (err) {
    error.value = formatApiError(err, 'Reports are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function exportCsv() {
  isExporting.value = true
  error.value = null
  try {
    const filters: Record<string, string> = {}
    if (fromDate.value) filters.from_date = fromDate.value
    if (toDate.value) filters.to_date = toDate.value
    await reportsApi.exportCsv(filters)
  } catch (err) {
    error.value = formatApiError(err, 'Could not export report.')
  } finally {
    isExporting.value = false
  }
}

watch([fromDate, toDate], () => {
  void load()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Reports" subtitle="Firm-wide cases, revenue, and billable time.">
      <template #actions>
        <button
          type="button"
          class="bw-btn bw-btn-outline"
          :disabled="isExporting || isLoading"
          @click="exportCsv"
        >
          <PhDownloadSimple class="h-4 w-4" />
          {{ isExporting ? 'Exporting…' : 'Export CSV' }}
        </button>
      </template>
    </PageHeader>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-end gap-4 border-b border-border p-4">
        <label class="space-y-1 text-sm">
          <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">From</span>
          <input v-model="fromDate" type="date" class="bw-input" aria-label="From date" />
        </label>
        <label class="space-y-1 text-sm">
          <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">To</span>
          <input v-model="toDate" type="date" class="bw-input" aria-label="To date" />
        </label>
        <button
          v-if="fromDate || toDate"
          type="button"
          class="bw-btn bw-btn-ghost text-sm"
          @click="fromDate = ''; toDate = ''"
        >
          Clear dates
        </button>
      </div>
      <p v-if="error" class="p-4 text-sm text-destructive">{{ error }}</p>
    </section>

    <template v-if="isLoading">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-24" />
      </div>
      <Skeleton class="h-64" />
    </template>

    <template v-else-if="summary">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bw-card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total cases</p>
          <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ summary.cases.total }}</p>
        </div>
        <div class="bw-card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Revenue collected</p>
          <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
            {{ formatCurrency(summary.revenue.total_paid) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            {{ summary.revenue.paid_invoice_count }} paid invoice(s)
          </p>
        </div>
        <div class="bw-card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Unpaid balance</p>
          <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
            {{ formatCurrency(summary.revenue.unpaid_total) }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            {{ summary.revenue.unpaid_invoice_count }} outstanding
          </p>
        </div>
        <div class="bw-card p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total billed</p>
          <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
            {{ formatCurrency(summary.revenue.total_billed) }}
          </p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="bw-card p-5">
          <h2 class="font-semibold text-foreground">Cases by status</h2>
          <p class="mt-1 text-sm text-muted-foreground">Distribution across all matters in range.</p>
          <div v-if="summary.cases.by_status.length" class="mt-4 space-y-3">
            <div
              v-for="row in summary.cases.by_status"
              :key="row.status"
              class="space-y-1"
            >
              <div class="flex items-center justify-between gap-3 text-sm">
                <StatusBadge :status="row.status" />
                <span class="tabular-nums text-muted-foreground">{{ row.count }}</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-surface-muted">
                <div
                  class="h-full rounded-full bg-[var(--action-teal)]"
                  :style="{ width: `${(row.count / maxCaseCount) * 100}%` }"
                />
              </div>
            </div>
          </div>
          <EmptyState v-else class="mt-6" title="No cases" message="No cases match the selected date range." />
        </section>

        <section class="bw-card p-5">
          <h2 class="font-semibold text-foreground">Billable time by lawyer</h2>
          <p class="mt-1 text-sm text-muted-foreground">Approved and draft billable entries in range.</p>
          <div v-if="summary.time_by_lawyer.length" class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr>
                  <th class="text-left">Lawyer</th>
                  <th class="text-right">Hours</th>
                  <th class="text-right">Amount</th>
                  <th class="w-32" />
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in summary.time_by_lawyer" :key="row.user_id">
                  <td class="font-medium text-foreground">{{ row.name }}</td>
                  <td class="text-right tabular-nums">{{ formatHours(row.billable_hours) }}</td>
                  <td class="text-right tabular-nums">{{ formatCurrency(row.billable_amount) }}</td>
                  <td>
                    <div class="h-2 overflow-hidden rounded-full bg-surface-muted">
                      <div
                        class="h-full rounded-full bg-[var(--action-teal)]"
                        :style="{ width: `${(row.billable_minutes / maxLawyerMinutes) * 100}%` }"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyState
            v-else
            class="mt-6"
            title="No billable time"
            message="No billable time entries match the selected date range."
          />
        </section>
      </div>
    </template>
  </div>
</template>
