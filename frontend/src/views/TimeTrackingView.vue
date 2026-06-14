<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhClock, PhMagnifyingGlass, PhPause, PhPlay, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { api, timeEntriesApi, type TimeEntryFilters, type TimeEntryPayload } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { CURRENCY_SYMBOL, formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/composables/usePermissions'
import type {
  LegalMatter,
  PaginatedResponse,
  TimeEntry,
  TimeEntrySummary,
} from '@/types'

const { can } = usePermissions()
const canViewAll = computed(() => can('time-entries.view-all'))
const canApprove = computed(() => can('time-entries.approve'))

const entries = ref<TimeEntry[]>([])
const summary = ref<TimeEntrySummary | null>(null)
const cases = ref<LegalMatter[]>([])
const running = ref<TimeEntry | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | undefined

const statusFilter = ref('')
const billableFilter = ref('')
const search = ref('')

const form = ref<TimeEntryPayload>({
  legal_matter_id: null,
  description: '',
  billable: true,
  rate: null,
})
const durationHours = ref<number | null>(null)
const durationMinutes = ref<number | null>(null)
const showAddModal = ref(false)

const summaryAccents = ['#0A4F5E', '#4A7FD4', '#7C5CBF', '#4CAF7D'] as const

function formatMinutes(minutes: number): string {
  const total = Math.max(0, Math.round(minutes))
  const h = Math.floor(total / 60)
  const m = total % 60
  if (h && m) return `${h}h ${m}m`
  if (h) return `${h}h`
  return `${m}m`
}

const filteredEntries = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return entries.value
  return entries.value.filter((entry) =>
    [entry.description, entry.case?.title, entry.user?.name, entry.status]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const runningElapsed = computed(() => {
  if (!running.value?.started_at) return '0m'
  const started = new Date(running.value.started_at).getTime()
  const minutes = Math.max(0, Math.floor((now.value - started) / 60000))
  return formatMinutes(minutes)
})

function currentFilters(): TimeEntryFilters {
  const filters: TimeEntryFilters = {}
  if (statusFilter.value) filters.status = statusFilter.value
  if (billableFilter.value) filters.billable = billableFilter.value === 'billable'
  return filters
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [{ entries: list, summary: totals }, runningEntry] = await Promise.all([
      timeEntriesApi.list(currentFilters()),
      timeEntriesApi.running().catch(() => null),
    ])
    entries.value = list
    summary.value = totals
    running.value = runningEntry
  } catch (err) {
    error.value = formatApiError(err, 'Time entries are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function loadCases() {
  try {
    const { data } = await api.get<PaginatedResponse<LegalMatter>>('/cases', {
      params: { per_page: 100 },
    })
    cases.value = data.data
  } catch {
    cases.value = []
  }
}

async function startTimer() {
  error.value = null
  try {
    running.value = await timeEntriesApi.startTimer({
      legal_matter_id: form.value.legal_matter_id || null,
      description: form.value.description || null,
      billable: form.value.billable,
      rate: form.value.rate ? Number(form.value.rate) : null,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not start the timer.')
  }
}

async function stopTimer() {
  if (!running.value) return
  error.value = null
  try {
    await timeEntriesApi.stopTimer(running.value.id)
    running.value = null
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not stop the timer.')
  }
}

function resetForm() {
  form.value = { legal_matter_id: null, description: '', billable: true, rate: null }
  durationHours.value = null
  durationMinutes.value = null
}

async function createEntry() {
  const minutes = (Number(durationHours.value) || 0) * 60 + (Number(durationMinutes.value) || 0)
  if (minutes <= 0) {
    error.value = 'Enter a duration greater than zero.'
    return
  }
  isSaving.value = true
  error.value = null
  try {
    const created = await timeEntriesApi.create({
      legal_matter_id: form.value.legal_matter_id || null,
      description: form.value.description || null,
      duration_minutes: minutes,
      billable: form.value.billable,
      rate: form.value.rate ? Number(form.value.rate) : null,
    })
    entries.value = [created, ...entries.value]
    resetForm()
    showAddModal.value = false
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this time entry.')
  } finally {
    isSaving.value = false
  }
}

async function approve(entry: TimeEntry) {
  error.value = null
  try {
    const updated = await timeEntriesApi.approve(entry.id)
    entries.value = entries.value.map((item) => (item.id === updated.id ? updated : item))
  } catch (err) {
    error.value = formatApiError(err, 'We could not approve this entry.')
  }
}

async function remove(entry: TimeEntry) {
  error.value = null
  try {
    await timeEntriesApi.delete(entry.id)
    entries.value = entries.value.filter((item) => item.id !== entry.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this entry.')
  }
}

watch([statusFilter, billableFilter], load)

onMounted(() => {
  load()
  loadCases()
  ticker = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  if (ticker) clearInterval(ticker)
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Time tracking"
      subtitle="Track billable and non-billable work across your matters."
    >
      <template #actions>
        <button type="button" class="bw-btn bw-btn-accent" @click="showAddModal = true">
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          Add entry
        </button>
      </template>
    </PageHeader>

    <div v-if="summary" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div
        v-for="(card, index) in [
          { label: 'Total', value: formatMinutes(summary.total_minutes) },
          { label: 'Billable', value: formatMinutes(summary.billable_minutes) },
          { label: 'Non-billable', value: formatMinutes(summary.non_billable_minutes) },
          { label: 'Billable value', value: formatCurrency(summary.billable_amount) },
        ]"
        :key="card.label"
        class="bw-card overflow-hidden"
      >
        <div class="h-1" :style="{ background: summaryAccents[index] }" aria-hidden="true" />
        <div class="p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {{ card.label }}
          </p>
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">{{ card.value }}</p>
        </div>
      </div>
    </div>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-5 py-4">
        <div>
          <h2 class="font-semibold text-foreground">Timer</h2>
          <p class="text-sm text-muted-foreground">Track work as it happens.</p>
        </div>
        <div v-if="running" class="flex flex-wrap items-center gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Running</p>
            <p class="text-2xl font-semibold tabular-nums text-foreground">{{ runningElapsed }}</p>
            <p v-if="running.case" class="truncate text-sm text-muted-foreground">
              {{ running.case.title }}
            </p>
          </div>
          <button type="button" class="bw-btn bw-btn-action" @click="stopTimer">
            <PhPause class="h-4 w-4" weight="fill" />
            Stop &amp; save
          </button>
        </div>
        <button v-else type="button" class="bw-btn bw-btn-action" @click="startTimer">
          <PhPlay class="h-4 w-4" weight="fill" />
          Start timer
        </button>
      </div>
    </section>

    <section class="bw-card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
          <div class="relative min-w-[220px] flex-1">
            <PhMagnifyingGlass
              class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
            />
            <input
              v-model="search"
              type="search"
              placeholder="Search description, case, or lawyer…"
              class="bw-input pl-9"
              aria-label="Search time entries"
            />
          </div>
          <select v-model="statusFilter" class="bw-select w-auto" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
          </select>
          <select v-model="billableFilter" class="bw-select w-auto" aria-label="Filter by billable">
            <option value="">All time</option>
            <option value="billable">Billable</option>
            <option value="non_billable">Non-billable</option>
          </select>
          <span v-if="canViewAll" class="ml-auto text-xs text-muted-foreground">
            Showing all team time entries
          </span>
        </div>

        <Skeleton v-if="isLoading" variant="panel" :rows="5" />
        <p v-else-if="error" class="p-6 text-sm text-destructive" role="alert">{{ error }}</p>

        <div v-else-if="filteredEntries.length" class="divide-y divide-border">
          <article
            v-for="entry in filteredEntries"
            :key="entry.id"
            class="flex flex-wrap items-start justify-between gap-4 px-6 py-4"
          >
            <div class="min-w-[220px] flex-1 space-y-2">
              <p class="font-medium text-foreground">{{ entry.description || 'Untitled entry' }}</p>
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <StatusBadge :status="entry.status" />
                <span
                  class="bw-badge"
                  :class="entry.billable ? 'bw-badge-accent' : 'bw-badge-neutral'"
                >
                  {{ entry.billable ? 'Billable' : 'Non-billable' }}
                </span>
                <RouterLink
                  v-if="entry.case"
                  :to="`/cases/${entry.case.id}/time`"
                  class="bw-badge bw-badge-neutral hover:underline"
                >
                  {{ entry.case.title }}
                </RouterLink>
                <span v-if="canViewAll && entry.user" class="bw-badge bw-badge-neutral">
                  {{ entry.user.name }}
                </span>
                <span v-if="entry.is_running" class="bw-badge bw-badge-danger">Running</span>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <div class="text-right">
                <p class="font-semibold tabular-nums text-foreground">
                  {{ formatMinutes(entry.duration_minutes) }}
                </p>
                <p class="text-xs tabular-nums text-muted-foreground">{{ formatCurrency(entry.amount) }}</p>
              </div>
              <button
                v-if="canApprove && entry.status !== 'approved' && !entry.is_running"
                type="button"
                class="bw-btn bw-btn-outline bw-btn-sm"
                @click="approve(entry)"
              >
                Approve
              </button>
              <button
                v-if="entry.status !== 'approved'"
                type="button"
                class="bw-btn bw-btn-danger bw-btn-sm"
                @click="remove(entry)"
              >
                Delete
              </button>
            </div>
          </article>
        </div>
        <EmptyState
          v-else
          :icon="PhClock"
          :title="search || statusFilter || billableFilter ? 'No matching entries' : 'No time logged yet'"
          :message="search || statusFilter || billableFilter ? 'Try adjusting your filters.' : 'Start the timer or add a manual entry to begin tracking your work.'"
        />
    </section>

    <BwModal
      :open="showAddModal"
      title="Manual time entry"
      size="md"
      @close="showAddModal = false"
    >
      <form id="time-entry-form" class="space-y-4" @submit.prevent="createEntry">
        <p class="text-sm text-muted-foreground">Log time you have already worked.</p>

        <div>
          <label class="bw-label" for="entry-case">Case (optional)</label>
          <select id="entry-case" v-model="form.legal_matter_id" class="bw-select">
            <option :value="null">No case</option>
            <option v-for="matter in cases" :key="matter.id" :value="matter.id">
              {{ matter.title }}
            </option>
          </select>
        </div>

        <div>
          <label class="bw-label" for="entry-description">Description</label>
          <textarea
            id="entry-description"
            v-model="form.description"
            rows="2"
            class="bw-textarea"
            placeholder="Client call and follow-up notes"
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="bw-label" for="entry-hours">Hours</label>
            <input id="entry-hours" v-model="durationHours" type="number" min="0" class="bw-input" placeholder="1" />
          </div>
          <div>
            <label class="bw-label" for="entry-minutes">Minutes</label>
            <input
              id="entry-minutes"
              v-model="durationMinutes"
              type="number"
              min="0"
              max="59"
              class="bw-input"
              placeholder="30"
            />
          </div>
        </div>

        <div>
          <label class="bw-label" for="entry-rate">Hourly rate ({{ CURRENCY_SYMBOL }}, optional)</label>
          <input
            id="entry-rate"
            v-model="form.rate"
            type="number"
            min="0"
            step="0.01"
            class="bw-input"
            placeholder="1500"
          />
        </div>

        <label class="flex items-center gap-2 text-sm text-foreground">
          <input
            v-model="form.billable"
            type="checkbox"
            class="bw-focus-ring h-4 w-4 rounded border-border-strong"
          />
          Billable
        </label>
      </form>

      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showAddModal = false">
          Cancel
        </button>
        <button type="submit" form="time-entry-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Add entry' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
