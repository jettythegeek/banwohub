<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { PhCalendarBlank } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { portalAppointmentsApi, portalCasesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { Appointment, AvailableSlot, LegalMatter, PortalLawyer } from '@/types'

const lawyers = ref<PortalLawyer[]>([])
const cases = ref<LegalMatter[]>([])
const appointments = ref<Appointment[]>([])
const slots = ref<AvailableSlot[]>([])
const isLoading = ref(true)
const isBooking = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const selectedLawyerId = ref<number | null>(null)
const selectedDate = ref('')
const selectedSlot = ref<AvailableSlot | null>(null)
const selectedCaseId = ref<number | null>(null)
const consultationType = ref('free_consultation')
const notes = ref('')

const minDate = computed(() => new Date().toISOString().slice(0, 10))

const upcomingAppointments = computed(() =>
  [...appointments.value].sort(
    (a, b) => new Date(b.starts_at).getTime() - new Date(a.starts_at).getTime(),
  ),
)

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString()
}

function slotLabel(slot: AvailableSlot) {
  const start = new Date(slot.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  const end = new Date(slot.ends_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  return `${start} – ${end}`
}

async function loadSlots() {
  if (!selectedLawyerId.value || !selectedDate.value) {
    slots.value = []
    return
  }
  slots.value = await portalAppointmentsApi.availableSlots(selectedLawyerId.value, selectedDate.value)
  selectedSlot.value = null
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [lawyerList, caseList, apptList] = await Promise.all([
      portalAppointmentsApi.lawyers(),
      portalCasesApi.list().catch(() => []),
      portalAppointmentsApi.list(),
    ])
    lawyers.value = lawyerList
    cases.value = caseList
    appointments.value = apptList
    if (!selectedLawyerId.value && lawyerList[0]) {
      selectedLawyerId.value = lawyerList[0].id
    }
    if (!selectedDate.value) {
      selectedDate.value = minDate.value
    }
    await loadSlots()
  } catch (err) {
    error.value = formatApiError(err, 'Appointments are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function book() {
  if (!selectedLawyerId.value || !selectedSlot.value) return
  isBooking.value = true
  error.value = null
  success.value = null
  try {
    const booked = await portalAppointmentsApi.book({
      user_id: selectedLawyerId.value,
      legal_matter_id: selectedCaseId.value,
      consultation_type: consultationType.value,
      starts_at: selectedSlot.value.starts_at,
      ends_at: selectedSlot.value.ends_at,
      location: selectedSlot.value.location,
      online_meeting: selectedSlot.value.online_meeting,
      fee: selectedSlot.value.fee,
      notes: notes.value || null,
    })
    success.value =
      booked.status === 'pending'
        ? 'Booking requested. Payment or firm confirmation may be required.'
        : 'Appointment confirmed. You will receive a reminder before the meeting.'
    notes.value = ''
    selectedSlot.value = null
    appointments.value = await portalAppointmentsApi.list()
    await loadSlots()
  } catch (err) {
    error.value = formatApiError(err, 'Could not book appointment.')
  } finally {
    isBooking.value = false
  }
}

async function cancel(id: number) {
  try {
    await portalAppointmentsApi.cancel(id)
    appointments.value = await portalAppointmentsApi.list()
  } catch (err) {
    error.value = formatApiError(err, 'Could not cancel appointment.')
  }
}

watch([selectedLawyerId, selectedDate], loadSlots)

onMounted(load)
</script>

<template>
  <div class="space-y-8">
    <PageHeader
      title="Book an appointment"
      subtitle="Choose a lawyer, pick a time, and confirm your consultation."
    />

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    <p v-if="success" class="text-sm text-primary">{{ success }}</p>

    <Skeleton v-if="isLoading" class="h-48 w-full" />

    <template v-else>
      <section class="bw-card p-5">
        <h2 class="mb-4 text-sm font-semibold">New booking</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="text-sm">
            Lawyer
            <select v-model.number="selectedLawyerId" class="bw-input mt-1 w-full">
              <option v-for="lawyer in lawyers" :key="lawyer.id" :value="lawyer.id">
                {{ lawyer.name }}
              </option>
            </select>
          </label>
          <label class="text-sm">
            Date
            <input
              v-model="selectedDate"
              type="date"
              class="bw-input mt-1 w-full"
              :min="minDate"
            />
          </label>
          <label class="text-sm">
            Consultation type
            <select v-model="consultationType" class="bw-input mt-1 w-full">
              <option value="free_consultation">Free consultation</option>
              <option value="paid_consultation">Paid consultation</option>
              <option value="case_review">Case review</option>
              <option value="client_meeting">Client meeting</option>
            </select>
          </label>
          <label class="text-sm">
            Related case (optional)
            <select v-model.number="selectedCaseId" class="bw-input mt-1 w-full">
              <option :value="null">None</option>
              <option v-for="c in cases" :key="c.id" :value="c.id">
                {{ c.title }}
              </option>
            </select>
          </label>
        </div>

        <div class="mt-4">
          <p class="mb-2 text-sm font-medium">Available times</p>
          <div v-if="slots.length" class="flex flex-wrap gap-2">
            <button
              v-for="(slot, index) in slots"
              :key="index"
              type="button"
              class="bw-btn bw-btn-sm"
              :class="selectedSlot === slot ? 'bw-btn-primary' : 'bw-btn-outline'"
              @click="selectedSlot = slot"
            >
              {{ slotLabel(slot) }}
            </button>
          </div>
          <p v-else class="text-sm text-muted-foreground">No open slots for this date.</p>
        </div>

        <label class="mt-4 block text-sm">
          Notes
          <textarea v-model="notes" rows="2" class="bw-input mt-1 w-full" placeholder="Brief reason for the meeting" />
        </label>

        <button
          type="button"
          class="bw-btn bw-btn-primary mt-4"
          :disabled="!selectedSlot || isBooking"
          @click="book"
        >
          Confirm booking
        </button>
      </section>

      <section class="bw-card overflow-hidden">
        <h2 class="border-b border-border px-5 py-4 text-sm font-semibold">Your appointments</h2>
        <div v-if="upcomingAppointments.length" class="divide-y divide-border">
          <article
            v-for="item in upcomingAppointments"
            :key="item.id"
            class="flex flex-wrap items-start justify-between gap-3 p-4"
          >
            <div>
              <p class="font-medium">{{ humanize(item.consultation_type) }}</p>
              <p class="text-sm text-muted-foreground">
                {{ formatDateTime(item.starts_at) }}
                <span v-if="item.lawyer"> with {{ item.lawyer.name }}</span>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <StatusBadge :status="item.status" />
              <button
                v-if="['pending', 'confirmed'].includes(item.status)"
                type="button"
                class="bw-btn bw-btn-outline bw-btn-sm"
                @click="cancel(item.id)"
              >
                Cancel
              </button>
            </div>
          </article>
        </div>
        <EmptyState
          v-else
          :icon="PhCalendarBlank"
          title="No appointments yet"
          description="Book a consultation with your firm using the form above."
        />
      </section>
    </template>
  </div>
</template>
