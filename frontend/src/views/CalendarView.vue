<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { PhCalendarBlank, PhCaretLeft, PhCaretRight, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import {
  appointmentsApi,
  calendarHubApi,
  lawyerAvailabilityApi,
  usersApi,
  type CreateAppointmentPayload,
} from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import {
  addMonths,
  addWeeks,
  buildMonthGrid,
  buildWeekDays,
  endOfMonth,
  endOfWeek,
  startOfMonth,
  startOfWeek,
  toDateKey,
  toIsoDate,
} from '@/lib/calendar-grid'
import {
  CALENDAR_HUB_CATEGORIES,
  calendarCategoryBadge,
  humanizeEnum,
  type CalendarHubCategory,
} from '@/lib/enums'
import { humanize } from '@/lib/status'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import type {
  Appointment,
  CalendarHubItem,
  LawyerAvailabilitySlot,
  User,
} from '@/types'

const auth = useAuthStore()
const { can } = usePermissions()

const users = ref<User[]>([])
const hubItems = ref<CalendarHubItem[]>([])
const deadlineBoard = ref<CalendarHubItem[]>([])
const appointments = ref<Appointment[]>([])
const availability = ref<LawyerAvailabilitySlot[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const activeTab = ref<'hub' | 'appointments' | 'availability'>('hub')
const viewMode = ref<'month' | 'week'>('month')
const categoryFilter = ref<CalendarHubCategory>('all')
const anchorDate = ref(new Date())

const selectedLawyerId = ref<number | null>(null)
const showAppointmentModal = ref(false)
const showAvailabilityModal = ref(false)

const canManageAvailability = computed(() => can('appointments.manage-availability'))
const canCreateAppointment = computed(() => can('appointments.create'))

const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const rangeFrom = computed(() =>
  viewMode.value === 'month' ? startOfMonth(anchorDate.value) : startOfWeek(anchorDate.value),
)
const rangeTo = computed(() =>
  viewMode.value === 'month' ? endOfMonth(anchorDate.value) : endOfWeek(anchorDate.value),
)

const periodLabel = computed(() => {
  if (viewMode.value === 'month') {
    return anchorDate.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
  }
  const start = startOfWeek(anchorDate.value)
  const end = endOfWeek(anchorDate.value)
  const sameMonth = start.getMonth() === end.getMonth()
  const startFmt = start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  const endFmt = end.toLocaleDateString(undefined, {
    month: sameMonth ? undefined : 'short',
    day: 'numeric',
    year: 'numeric',
  })
  return `${startFmt} – ${endFmt}`
})

const gridCells = computed(() =>
  viewMode.value === 'month' ? buildMonthGrid(anchorDate.value) : buildWeekDays(anchorDate.value),
)

const itemsByDay = computed(() => {
  const map = new Map<string, CalendarHubItem[]>()
  for (const item of hubItems.value) {
    const key = toDateKey(new Date(item.starts_at))
    const list = map.get(key) ?? []
    list.push(item)
    map.set(key, list)
  }
  for (const [, list] of map) {
    list.sort((a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime())
  }
  return map
})

const availabilityForm = ref({
  day_of_week: 1,
  start_time: '09:00',
  end_time: '17:00',
  slot_duration_minutes: 30,
  consultation_types: ['free_consultation', 'client_meeting'],
  consultation_fee: null as number | null,
  location: '',
  online_meeting: false,
})

const appointmentForm = ref<CreateAppointmentPayload>({
  user_id: 0,
  client_id: null,
  consultation_type: 'client_meeting',
  starts_at: '',
  ends_at: '',
  location: '',
  notes: '',
})

const orderedAppointments = computed(() =>
  [...appointments.value].sort(
    (a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime(),
  ),
)

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString()
}

function formatShortTime(iso: string) {
  return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
}

function daysUntil(iso: string): number {
  const start = new Date(iso)
  start.setHours(0, 0, 0, 0)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return Math.ceil((start.getTime() - today.getTime()) / 86400000)
}

function deadlineUrgencyClass(days: number): string {
  if (days <= 3) return 'bw-badge-danger'
  if (days <= 14) return 'bw-badge-warning'
  return 'bw-badge-neutral'
}

async function loadHub() {
  const response = await calendarHubApi.list({
    from: toIsoDate(rangeFrom.value),
    to: toIsoDate(rangeTo.value),
    user_id: selectedLawyerId.value ?? undefined,
    category: categoryFilter.value,
  })
  hubItems.value = response.data ?? []
  deadlineBoard.value = response.meta?.deadline_board ?? []
}

async function exportIcs() {
  error.value = null
  try {
    await calendarHubApi.exportIcs({
      from: toIsoDate(rangeFrom.value),
      to: toIsoDate(rangeTo.value),
      user_id: selectedLawyerId.value ?? undefined,
      category: categoryFilter.value,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not export the calendar.')
  }
}

async function loadAppointments() {
  appointments.value = await appointmentsApi.list({
    user_id: selectedLawyerId.value ?? undefined,
    from: toIsoDate(rangeFrom.value),
    to: toIsoDate(rangeTo.value),
  })
}

async function loadAvailability() {
  if (!selectedLawyerId.value) return
  availability.value = await lawyerAvailabilityApi.list(selectedLawyerId.value)
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    users.value = await usersApi.listActive().catch(() => [])
    if (!selectedLawyerId.value && auth.user?.id) {
      selectedLawyerId.value = auth.user.id
    }
    await Promise.all([
      loadHub(),
      activeTab.value === 'appointments' ? loadAppointments() : Promise.resolve(),
      activeTab.value === 'availability' ? loadAvailability() : Promise.resolve(),
    ])
  } catch (err) {
    error.value = formatApiError(err, 'Calendar is not available yet.')
  } finally {
    isLoading.value = false
  }
}

function shiftPeriod(direction: -1 | 1) {
  anchorDate.value =
    viewMode.value === 'month'
      ? addMonths(anchorDate.value, direction)
      : addWeeks(anchorDate.value, direction)
}

function goToday() {
  anchorDate.value = new Date()
}

async function saveAvailability() {
  if (!selectedLawyerId.value) return
  isSaving.value = true
  error.value = null
  try {
    const existing = availability.value.map((slot) => ({
      day_of_week: slot.day_of_week,
      start_time: slot.start_time,
      end_time: slot.end_time,
      slot_duration_minutes: slot.slot_duration_minutes,
      consultation_types: slot.consultation_types,
      consultation_fee: slot.consultation_fee,
      location: slot.location,
      online_meeting: slot.online_meeting,
      is_active: slot.is_active,
    }))
    existing.push({
      day_of_week: availabilityForm.value.day_of_week,
      start_time: availabilityForm.value.start_time,
      end_time: availabilityForm.value.end_time,
      slot_duration_minutes: availabilityForm.value.slot_duration_minutes,
      consultation_types: availabilityForm.value.consultation_types,
      consultation_fee: availabilityForm.value.consultation_fee,
      location: availabilityForm.value.location || null,
      online_meeting: availabilityForm.value.online_meeting,
      is_active: true,
    })
    availability.value = await lawyerAvailabilityApi.update({
      user_id: selectedLawyerId.value,
      slots: existing,
    })
    showAvailabilityModal.value = false
    availabilityForm.value = {
      day_of_week: 1,
      start_time: '09:00',
      end_time: '17:00',
      slot_duration_minutes: 30,
      consultation_types: ['free_consultation', 'client_meeting'],
      consultation_fee: null,
      location: '',
      online_meeting: false,
    }
  } catch (err) {
    error.value = formatApiError(err, 'Could not save availability.')
  } finally {
    isSaving.value = false
  }
}

async function createAppointment() {
  if (!selectedLawyerId.value) return
  isSaving.value = true
  error.value = null
  try {
    await appointmentsApi.create({
      ...appointmentForm.value,
      user_id: selectedLawyerId.value,
      status: 'confirmed',
    })
    appointmentForm.value = {
      user_id: selectedLawyerId.value,
      client_id: null,
      consultation_type: 'client_meeting',
      starts_at: '',
      ends_at: '',
      location: '',
      notes: '',
    }
    showAppointmentModal.value = false
    await Promise.all([loadAppointments(), loadHub()])
  } catch (err) {
    error.value = formatApiError(err, 'Could not create appointment.')
  } finally {
    isSaving.value = false
  }
}

async function cancelAppointment(id: number) {
  try {
    await appointmentsApi.cancel(id)
    await Promise.all([loadAppointments(), loadHub()])
  } catch (err) {
    error.value = formatApiError(err, 'Could not cancel appointment.')
  }
}

watch([selectedLawyerId, categoryFilter, viewMode, anchorDate], () => {
  if (activeTab.value === 'hub') {
    loadHub()
  } else if (activeTab.value === 'appointments') {
    loadAppointments()
  }
})

watch(activeTab, (tab) => {
  if (tab === 'availability') loadAvailability()
  if (tab === 'appointments') loadAppointments()
  if (tab === 'hub') loadHub()
})

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Calendar hub"
      subtitle="Firm-wide hearings, deadlines, appointments, and lawyer availability."
    >
      <template #actions>
        <button
          v-if="canCreateAppointment && activeTab === 'appointments'"
          type="button"
          class="bw-btn bw-btn-accent"
          @click="showAppointmentModal = true"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          New appointment
        </button>
        <button
          v-if="canManageAvailability && activeTab === 'availability'"
          type="button"
          class="bw-btn bw-btn-accent"
          :disabled="!selectedLawyerId"
          @click="showAvailabilityModal = true"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          Add slot
        </button>
      </template>
    </PageHeader>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <div class="flex flex-wrap items-center gap-3">
      <button
        v-if="activeTab === 'hub'"
        type="button"
        class="bw-btn bw-btn-outline"
        @click="exportIcs"
      >
        Export iCal
      </button>

      <label class="text-sm text-muted-foreground">
        Lawyer
        <select v-model.number="selectedLawyerId" class="bw-select ml-2">
          <option :value="null">All lawyers</option>
          <option v-for="user in users" :key="user.id" :value="user.id">
            {{ user.name }}
          </option>
        </select>
      </label>
    </div>

    <nav class="bw-tabs">
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeTab === 'hub' }"
        @click="activeTab = 'hub'"
      >
        Schedule
      </button>
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeTab === 'appointments' }"
        @click="activeTab = 'appointments'"
      >
        Appointments
      </button>
      <button
        v-if="canManageAvailability"
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeTab === 'availability' }"
        @click="activeTab = 'availability'"
      >
        Availability
      </button>
    </nav>

    <template v-if="activeTab === 'hub'">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
          <button
            v-for="cat in CALENDAR_HUB_CATEGORIES"
            :key="cat"
            type="button"
            class="bw-btn bw-btn-sm capitalize"
            :class="categoryFilter === cat ? 'bw-btn-primary' : 'bw-btn-outline'"
            @click="categoryFilter = cat"
          >
            {{ cat === 'all' ? 'All events' : cat }}
          </button>
        </div>

        <div class="flex items-center gap-2">
          <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="viewMode = 'month'">
            Month
          </button>
          <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="viewMode = 'week'">
            Week
          </button>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <section class="bw-card overflow-hidden">
          <div class="bw-card-header flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="shiftPeriod(-1)">
                <PhCaretLeft class="h-4 w-4" />
              </button>
              <h2 class="min-w-[10rem] text-center font-semibold">{{ periodLabel }}</h2>
              <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="shiftPeriod(1)">
                <PhCaretRight class="h-4 w-4" />
              </button>
            </div>
            <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="goToday">
              Today
            </button>
          </div>

          <Skeleton v-if="isLoading" variant="panel" :rows="6" class="p-6" />

          <div v-else class="border-t border-border">
            <div
              class="grid grid-cols-7 border-b border-border bg-surface text-center text-xs font-medium uppercase tracking-wide text-muted-foreground"
            >
              <div v-for="label in dayLabels" :key="label" class="px-2 py-2">{{ label }}</div>
            </div>

            <div
              class="grid grid-cols-7"
              :class="viewMode === 'week' ? 'min-h-[320px]' : 'auto-rows-fr'"
            >
              <div
                v-for="cell in gridCells"
                :key="cell.key"
                class="min-h-[88px] border-b border-r border-border p-1.5"
                :class="{
                  'bg-background text-muted-foreground': !cell.inMonth && viewMode === 'month',
                  'ring-1 ring-inset ring-primary/40': cell.isToday,
                }"
              >
                <div class="mb-1 text-xs font-medium tabular-nums">{{ cell.date.getDate() }}</div>
                <div class="space-y-0.5">
                  <div
                    v-for="item in (itemsByDay.get(cell.key) ?? []).slice(0, 3)"
                    :key="item.id"
                    class="truncate rounded px-1 py-0.5 text-[10px] leading-tight"
                    :class="calendarCategoryBadge(item.category)"
                    :title="item.title"
                  >
                    {{ formatShortTime(item.starts_at) }} {{ item.title }}
                  </div>
                  <p
                    v-if="(itemsByDay.get(cell.key) ?? []).length > 3"
                    class="text-[10px] text-muted-foreground"
                  >
                    +{{ (itemsByDay.get(cell.key) ?? []).length - 3 }} more
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <aside class="space-y-4">
          <section class="bw-card">
            <div class="bw-card-header">
              <h2 class="font-semibold">Upcoming deadlines</h2>
              <p class="text-sm text-muted-foreground">Next 90 days firm-wide</p>
            </div>
            <div v-if="deadlineBoard.length" class="divide-y divide-border">
              <article
                v-for="item in deadlineBoard"
                :key="item.id"
                class="space-y-1 px-4 py-3"
              >
                <div class="flex items-start justify-between gap-2">
                  <p class="text-sm font-medium leading-snug">{{ item.title }}</p>
                  <span
                    class="bw-badge shrink-0 tabular-nums"
                    :class="deadlineUrgencyClass(daysUntil(item.starts_at))"
                  >
                    {{ daysUntil(item.starts_at) }}d
                  </span>
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ formatDateTime(item.starts_at) }}
                </p>
                <p v-if="item.case" class="text-xs text-muted-foreground">
                  {{ item.case.matter_number ?? item.case.title }}
                </p>
              </article>
            </div>
            <EmptyState
              v-else
              :icon="PhCalendarBlank"
              title="No upcoming deadlines"
              message="Filing and review deadlines appear here."
              class="py-8"
            />
          </section>

          <section v-if="hubItems.length" class="bw-card">
            <div class="bw-card-header">
              <h2 class="font-semibold">Period list</h2>
            </div>
            <div class="max-h-80 divide-y divide-border overflow-y-auto">
              <article
                v-for="item in hubItems"
                :key="item.id"
                class="space-y-1 px-4 py-3"
              >
                <div class="flex flex-wrap items-center gap-2">
                  <span class="bw-badge" :class="calendarCategoryBadge(item.category)">
                    {{ humanizeEnum(item.category) }}
                  </span>
                  <span v-if="item.hearing_type" class="bw-badge bw-badge-neutral text-xs">
                    {{ humanizeEnum(item.hearing_type) }}
                  </span>
                </div>
                <p class="text-sm font-medium">{{ item.title }}</p>
                <p class="text-xs text-muted-foreground">{{ formatDateTime(item.starts_at) }}</p>
                <p v-if="item.court_name" class="text-xs text-muted-foreground">
                  {{ item.court_name }}
                  <span v-if="item.court_room"> · Room {{ item.court_room }}</span>
                </p>
              </article>
            </div>
          </section>
        </aside>
      </div>
    </template>

    <Skeleton v-else-if="isLoading" class="h-40 w-full" />

    <template v-else-if="activeTab === 'appointments'">
      <section class="bw-card overflow-hidden">
        <div v-if="orderedAppointments.length" class="divide-y divide-border">
          <article
            v-for="item in orderedAppointments"
            :key="item.id"
            class="flex flex-wrap items-start justify-between gap-3 p-4"
          >
            <div>
              <p class="font-medium">{{ humanize(item.consultation_type) }}</p>
              <p class="text-sm text-muted-foreground">
                {{ formatDateTime(item.starts_at) }}
                <span v-if="item.client"> — {{ item.client.name }}</span>
              </p>
              <p v-if="item.location" class="text-sm text-muted-foreground">{{ item.location }}</p>
            </div>
            <div class="flex items-center gap-2">
              <StatusBadge :status="item.status" />
              <button
                v-if="item.status !== 'cancelled' && can('appointments.update')"
                type="button"
                class="bw-btn bw-btn-outline bw-btn-sm"
                @click="cancelAppointment(item.id)"
              >
                Cancel
              </button>
            </div>
          </article>
        </div>
        <EmptyState
          v-else
          :icon="PhCalendarBlank"
          title="No appointments"
          message="Bookings from staff and the client portal will appear here."
        />
      </section>
    </template>

    <template v-else-if="activeTab === 'availability'">
      <section class="bw-card overflow-hidden">
        <div v-if="availability.length" class="divide-y divide-border">
          <article v-for="slot in availability" :key="slot.id" class="p-4 text-sm">
            <p class="font-medium text-foreground">
              {{ dayLabels[slot.day_of_week] }} {{ slot.start_time }}–{{ slot.end_time }}
            </p>
            <p class="text-muted-foreground">
              {{ slot.slot_duration_minutes }} min slots
              <span v-if="slot.location"> · {{ slot.location }}</span>
              <span v-if="slot.online_meeting"> · Online</span>
            </p>
          </article>
        </div>
        <EmptyState
          v-else
          :icon="PhCalendarBlank"
          title="No availability set"
          message="Add weekly time windows so clients can book via the portal."
        />
      </section>
    </template>

    <BwModal
      :open="showAppointmentModal"
      title="New appointment"
      size="md"
      @close="showAppointmentModal = false"
    >
      <form id="appointment-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="createAppointment">
        <div class="sm:col-span-2">
          <label class="bw-label" for="appt-type">Type</label>
          <select id="appt-type" v-model="appointmentForm.consultation_type" class="bw-select">
            <option value="free_consultation">Free consultation</option>
            <option value="paid_consultation">Paid consultation</option>
            <option value="case_review">Case review</option>
            <option value="client_meeting">Client meeting</option>
            <option value="court_preparation">Court preparation</option>
            <option value="internal_meeting">Internal meeting</option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="appt-starts">Starts</label>
          <input id="appt-starts" v-model="appointmentForm.starts_at" type="datetime-local" class="bw-input" required />
        </div>
        <div>
          <label class="bw-label" for="appt-ends">Ends</label>
          <input id="appt-ends" v-model="appointmentForm.ends_at" type="datetime-local" class="bw-input" required />
        </div>
        <div class="sm:col-span-2">
          <label class="bw-label" for="appt-location">Location</label>
          <input id="appt-location" v-model="appointmentForm.location" type="text" class="bw-input" />
        </div>
        <div class="sm:col-span-2">
          <label class="bw-label" for="appt-notes">Notes</label>
          <textarea id="appt-notes" v-model="appointmentForm.notes" rows="2" class="bw-textarea" />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showAppointmentModal = false">
          Cancel
        </button>
        <button type="submit" form="appointment-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Creating…' : 'Create appointment' }}
        </button>
      </template>
    </BwModal>

    <BwModal
      :open="showAvailabilityModal"
      title="Add weekly slot"
      size="md"
      @close="showAvailabilityModal = false"
    >
      <form id="availability-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveAvailability">
        <div>
          <label class="bw-label" for="avail-day">Day</label>
          <select id="avail-day" v-model.number="availabilityForm.day_of_week" class="bw-select">
            <option v-for="(label, index) in dayLabels" :key="index" :value="index">
              {{ label }}
            </option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="avail-duration">Duration (min)</label>
          <input
            id="avail-duration"
            v-model.number="availabilityForm.slot_duration_minutes"
            type="number"
            min="15"
            step="15"
            class="bw-input"
          />
        </div>
        <div>
          <label class="bw-label" for="avail-start">Start</label>
          <input id="avail-start" v-model="availabilityForm.start_time" type="time" class="bw-input" required />
        </div>
        <div>
          <label class="bw-label" for="avail-end">End</label>
          <input id="avail-end" v-model="availabilityForm.end_time" type="time" class="bw-input" required />
        </div>
        <div>
          <label class="bw-label" for="avail-fee">Fee (paid consult)</label>
          <input
            id="avail-fee"
            v-model.number="availabilityForm.consultation_fee"
            type="number"
            min="0"
            step="0.01"
            class="bw-input"
          />
        </div>
        <div>
          <label class="bw-label" for="avail-location">Location</label>
          <input id="avail-location" v-model="availabilityForm.location" type="text" class="bw-input" />
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2">
          <input v-model="availabilityForm.online_meeting" type="checkbox" class="bw-checkbox" />
          Online meeting available
        </label>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showAvailabilityModal = false">
          Cancel
        </button>
        <button
          type="submit"
          form="availability-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !selectedLawyerId"
        >
          {{ isSaving ? 'Saving…' : 'Save availability' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
