<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { PhClock, PhPause, PhPlay, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import {
  caseTasksApi,
  timeEntriesApi,
  type TimeEntryPayload,
} from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { CURRENCY_SYMBOL, formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/composables/usePermissions'
import type { CaseTask, TimeEntry, TimeEntrySummary } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()
const entries = ref<TimeEntry[]>([])
const summary = ref<TimeEntrySummary | null>(null)
const tasks = ref<CaseTask[]>([])
const running = ref<TimeEntry | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | undefined

const canApprove = computed(() => can('time-entries.approve'))

const form = ref<TimeEntryPayload>({
  description: '',
  legal_task_id: null,
  duration_minutes: 0,
  billable: true,
  rate: null,
})
const durationHours = ref<number | null>(null)
const durationMinutes = ref<number | null>(null)
const showAddModal = ref(false)

function formatMinutes(minutes: number): string {
  const total = Math.max(0, Math.round(minutes))
  const h = Math.floor(total / 60)
  const m = total % 60
  if (h && m) return `${h}h ${m}m`
  if (h) return `${h}h`
  return `${m}m`
}

const runningElapsed = computed(() => {
  if (!running.value?.started_at) return '0m'
  const started = new Date(running.value.started_at).getTime()
  const minutes = Math.max(0, Math.floor((now.value - started) / 60000))
  return formatMinutes(minutes)
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [{ entries: list, summary: totals }, taskList, runningEntry] = await Promise.all([
      timeEntriesApi.list({ legal_matter_id: props.caseId }),
      caseTasksApi.list(props.caseId).catch(() => []),
      timeEntriesApi.running().catch(() => null),
    ])
    entries.value = list
    summary.value = totals
    tasks.value = taskList
    running.value = runningEntry
  } catch (err) {
    error.value = formatApiError(err, 'Time entries are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function startTimer() {
  error.value = null
  try {
    running.value = await timeEntriesApi.startTimer({
      legal_matter_id: props.caseId,
      legal_task_id: form.value.legal_task_id || null,
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
  form.value = {
    description: '',
    legal_task_id: null,
    duration_minutes: 0,
    billable: true,
    rate: null,
  }
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
      legal_matter_id: props.caseId,
      legal_task_id: form.value.legal_task_id || null,
      description: form.value.description || null,
      duration_minutes: minutes,
      billable: form.value.billable,
      rate: form.value.rate ? Number(form.value.rate) : null,
    })
    entries.value = [created, ...entries.value]
    resetForm()
    showAddModal.value = false
    await refreshSummary()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this time entry.')
  } finally {
    isSaving.value = false
  }
}

async function refreshSummary() {
  try {
    const { summary: totals } = await timeEntriesApi.list({ legal_matter_id: props.caseId })
    summary.value = totals
  } catch {
    // non-fatal
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
    await refreshSummary()
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this entry.')
  }
}

onMounted(() => {
  load()
  ticker = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  if (ticker) clearInterval(ticker)
})
</script>

<template>
  <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="space-y-6">
      <div v-if="summary" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="bw-card p-4">
          <p class="text-xs uppercase tracking-wide text-muted-foreground">Total</p>
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ formatMinutes(summary.total_minutes) }}
          </p>
        </div>
        <div class="bw-card p-4">
          <p class="text-xs uppercase tracking-wide text-muted-foreground">Billable</p>
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ formatMinutes(summary.billable_minutes) }}
          </p>
        </div>
        <div class="bw-card p-4">
          <p class="text-xs uppercase tracking-wide text-muted-foreground">Non-billable</p>
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ formatMinutes(summary.non_billable_minutes) }}
          </p>
        </div>
        <div class="bw-card p-4">
          <p class="text-xs uppercase tracking-wide text-muted-foreground">Billable value</p>
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ formatCurrency(summary.billable_amount) }}
          </p>
        </div>
      </div>

      <section class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <div>
            <h2 class="font-semibold text-foreground">Time entries</h2>
            <p class="text-sm text-muted-foreground">Billable and non-billable work on this matter.</p>
          </div>
          <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="showAddModal = true">
            <PhPlus class="h-4 w-4" weight="bold" />
            Add entry
          </button>
        </div>

        <Skeleton v-if="isLoading" variant="panel" :rows="4" />
        <p v-else-if="error" class="p-6 text-sm text-destructive" role="alert">{{ error }}</p>

        <div v-else-if="entries.length" class="divide-y divide-border">
          <article
            v-for="entry in entries"
            :key="entry.id"
            class="flex flex-wrap items-start justify-between gap-4 px-6 py-4"
          >
            <div class="min-w-[220px] flex-1 space-y-2">
              <p class="font-medium text-foreground">
                {{ entry.description || 'Untitled entry' }}
              </p>
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <StatusBadge :status="entry.status" :dot="false" />
                <span
                  class="bw-badge"
                  :class="entry.billable ? 'bw-badge-accent' : 'bw-badge-neutral'"
                >
                  {{ entry.billable ? 'Billable' : 'Non-billable' }}
                </span>
                <span v-if="entry.task" class="bw-badge bw-badge-neutral">{{ entry.task.title }}</span>
                <span class="bw-badge bw-badge-neutral">{{ entry.user?.name || '—' }}</span>
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
          title="No time logged yet"
          message="Start the timer or add a manual entry to track work on this matter."
        />
      </section>
    </section>

    <div class="space-y-6">
      <section class="bw-card space-y-4 p-6">
        <div>
          <h2 class="font-semibold text-foreground">Timer</h2>
          <p class="text-sm text-muted-foreground">Track work as it happens.</p>
        </div>

        <div v-if="running" class="rounded-lg border border-border bg-surface p-4">
          <p class="text-xs uppercase tracking-wide text-muted-foreground">Running</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ runningElapsed }}</p>
          <p v-if="running.description" class="mt-1 truncate text-sm text-muted-foreground">
            {{ running.description }}
          </p>
          <button type="button" class="bw-btn bw-btn-action mt-3 w-full" @click="stopTimer">
            <PhPause class="h-4 w-4" weight="fill" />
            Stop &amp; save
          </button>
        </div>
        <button v-else type="button" class="bw-btn bw-btn-action w-full" @click="startTimer">
          <PhPlay class="h-4 w-4" weight="fill" />
          Start timer
        </button>
      </section>
    </div>

    <BwModal :open="showAddModal" title="Manual time entry" size="md" @close="showAddModal = false">
      <form id="time-form" class="space-y-4" @submit.prevent="createEntry">
        <div>
          <label class="bw-label" for="time-description">Description</label>
          <textarea
            id="time-description"
            v-model="form.description"
            rows="2"
            class="bw-textarea"
            placeholder="Drafting heads of argument"
          />
        </div>
        <div v-if="tasks.length">
          <label class="bw-label" for="time-task">Task (optional)</label>
          <select id="time-task" v-model="form.legal_task_id" class="bw-select">
            <option :value="null">No task</option>
            <option v-for="task in tasks" :key="task.id" :value="task.id">{{ task.title }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="bw-label" for="time-hours">Hours</label>
            <input
              id="time-hours"
              v-model="durationHours"
              type="number"
              min="0"
              class="bw-input"
              placeholder="1"
            />
          </div>
          <div>
            <label class="bw-label" for="time-minutes">Minutes</label>
            <input
              id="time-minutes"
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
          <label class="bw-label" for="time-rate">Hourly rate ({{ CURRENCY_SYMBOL }}, optional)</label>
          <input
            id="time-rate"
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
        <button type="submit" form="time-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Add entry' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
