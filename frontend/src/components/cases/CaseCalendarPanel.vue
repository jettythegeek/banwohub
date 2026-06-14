<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { PhCalendarBlank, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import {
  HEARING_STATUSES,
  HEARING_TYPES,
  DEADLINE_SUBTYPES,
  calendarCategoryBadge,
  categoryFromEventType,
  humanizeEnum,
} from '@/lib/enums'
import { humanize } from '@/lib/status'
import {
  caseCalendarApi,
  usersApi,
  type CaseCalendarEventPayload,
} from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { useAuthStore } from '@/stores/auth'
import type { CaseCalendarEvent, User } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const events = ref<CaseCalendarEvent[]>([])
const users = ref<User[]>([])
const auth = useAuthStore()
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showAddModal = ref(false)
const form = ref<CaseCalendarEventPayload>({
  title: '',
  description: '',
  user_id: null,
  event_type: 'filing_deadline',
  hearing_type: null,
  hearing_status: 'scheduled',
  deadline_subtype: 'deadline',
  starts_at: '',
  ends_at: '',
  location: '',
  court_name: '',
  court_room: '',
  judge_name: '',
  reminder_at: '',
  reminder_days_before: 7,
})

const isHearing = computed(() => form.value.event_type === 'court_hearing')
const isDeadline = computed(() =>
  ['filing_deadline', 'document_review_deadline', 'payment_due_date', 'limitation_deadline', 'follow_up_reminder'].includes(
    form.value.event_type,
  ),
)

const userOptions = computed(() => {
  const currentUser = auth.user
  if (!currentUser) return users.value
  if (users.value.some((user) => user.id === currentUser.id)) return users.value
  return [currentUser, ...users.value]
})

const orderedEvents = computed(() =>
  [...events.value].sort(
    (a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime(),
  ),
)

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [eventList, userList] = await Promise.all([
      caseCalendarApi.list(props.caseId),
      usersApi.listActive().catch(() => []),
    ])
    events.value = eventList
    users.value = userList
    if (!form.value.user_id && auth.user?.id) {
      form.value.user_id = auth.user.id
    }
  } catch (err) {
    error.value = formatApiError(err, 'Calendar events are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  form.value = {
    title: '',
    description: '',
    user_id: auth.user?.id ?? null,
    event_type: 'filing_deadline',
    hearing_type: null,
    hearing_status: 'scheduled',
    deadline_subtype: 'deadline',
    starts_at: '',
    ends_at: '',
    location: '',
    court_name: '',
    court_room: '',
    judge_name: '',
    reminder_at: '',
    reminder_days_before: 7,
  }
}

watch(
  () => form.value.event_type,
  (type) => {
    if (type === 'court_hearing' && !form.value.hearing_type) {
      form.value.hearing_type = 'motion'
      form.value.hearing_status = 'scheduled'
    }
  },
)

async function createEvent() {
  if (!form.value.user_id) {
    error.value = 'Choose who owns this calendar event before saving.'
    return
  }
  isSaving.value = true
  error.value = null
  try {
    const created = await caseCalendarApi.create(props.caseId, {
      ...form.value,
      user_id: Number(form.value.user_id),
      description: form.value.description || null,
      ends_at: form.value.ends_at || null,
      location: form.value.location || null,
      court_name: form.value.court_name || null,
      court_room: form.value.court_room || null,
      judge_name: form.value.judge_name || null,
      hearing_type: isHearing.value ? form.value.hearing_type : null,
      hearing_status: isHearing.value ? form.value.hearing_status : null,
      deadline_subtype: isDeadline.value ? form.value.deadline_subtype : null,
      reminder_at: form.value.reminder_at || null,
      reminder_days_before: form.value.reminder_days_before ?? null,
    })
    events.value = [...events.value, created]
    resetForm()
    showAddModal.value = false
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this event.')
  } finally {
    isSaving.value = false
  }
}

function formatDate(iso?: string | null) {
  if (!iso) return 'Not set'
  return new Date(iso).toLocaleString()
}

function reminderLabel(event: CaseCalendarEvent) {
  if (event.reminder_at) return formatDate(event.reminder_at)
  return 'No reminder'
}

onMounted(load)
</script>

<template>
  <section class="bw-card overflow-hidden">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">Case calendar</h2>
        <p class="text-sm text-muted-foreground">
          Track hearings, deadlines, meetings, and the reminders around them.
        </p>
      </div>
      <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="showAddModal = true">
        <PhPlus class="h-4 w-4" weight="bold" />
        Add event
      </button>
    </div>

      <Skeleton v-if="isLoading" variant="panel" :rows="4" />
      <p v-else-if="error" class="p-6 text-sm text-destructive" role="alert">
        {{ error }}
      </p>
      <div v-else-if="orderedEvents.length" class="divide-y divide-border">
        <article
          v-for="event in orderedEvents"
          :key="event.id"
          class="grid gap-4 px-6 py-4 md:grid-cols-[180px_minmax(0,1fr)]"
        >
          <div class="text-sm">
            <p class="font-medium tabular-nums text-foreground">
              {{ formatDate(event.starts_at) }}
            </p>
            <span class="mt-1 inline-flex gap-1">
              <span class="bw-badge" :class="calendarCategoryBadge(event.category ?? categoryFromEventType(event.event_type))">
                {{ humanize(event.event_type) }}
              </span>
              <span v-if="event.hearing_type" class="bw-badge bw-badge-neutral">
                {{ humanizeEnum(event.hearing_type) }}
              </span>
            </span>
            <p v-if="event.user" class="mt-1 text-xs text-muted-foreground">
              {{ event.user.name }}
            </p>
          </div>
          <div class="space-y-2">
            <div>
              <h3 class="font-medium text-foreground">{{ event.title }}</h3>
              <p v-if="event.description" class="text-sm text-muted-foreground">
                {{ event.description }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
              <span v-if="event.ends_at" class="bw-badge bw-badge-neutral tabular-nums">
                Ends {{ formatDate(event.ends_at) }}
              </span>
              <span v-if="event.location" class="bw-badge bw-badge-neutral">
                {{ event.location }}
              </span>
              <span v-if="event.court_name" class="bw-badge bw-badge-neutral">
                {{ event.court_name }}
              </span>
              <span class="bw-badge bw-badge-neutral">
                Reminder: {{ reminderLabel(event) }}
                <template v-if="event.reminder_days_before">
                  ({{ event.reminder_days_before }}d before)
                </template>
              </span>
            </div>
          </div>
        </article>
      </div>
      <EmptyState
        v-else
        :icon="PhCalendarBlank"
        title="No events yet"
        message="Add the next important date for this case."
      />
  </section>

    <BwModal :open="showAddModal" title="New event" size="lg" @close="showAddModal = false">
      <form id="event-form" class="max-h-[60vh] space-y-4 overflow-y-auto pr-1" @submit.prevent="createEvent">
        <div>
          <label class="bw-label" for="event-title">Title</label>
          <input
            id="event-title"
            v-model="form.title"
            required
            class="bw-input"
            placeholder="Court hearing"
          />
        </div>
        <div>
          <label class="bw-label" for="event-type">Type</label>
          <select id="event-type" v-model="form.event_type" class="bw-select">
            <option value="court_hearing">Court hearing</option>
            <option value="filing_deadline">Filing deadline</option>
            <option value="client_meeting">Client meeting</option>
            <option value="internal_meeting">Internal meeting</option>
            <option value="document_review_deadline">Document review</option>
            <option value="payment_due_date">Payment due</option>
            <option value="limitation_deadline">Limitation deadline</option>
            <option value="follow_up_reminder">Follow-up reminder</option>
          </select>
        </div>
        <div v-if="isHearing">
          <label class="bw-label" for="hearing-type">Hearing type</label>
          <select id="hearing-type" v-model="form.hearing_type" class="bw-select">
            <option v-for="t in HEARING_TYPES" :key="t" :value="t">{{ humanizeEnum(t) }}</option>
          </select>
        </div>
        <div v-if="isHearing">
          <label class="bw-label" for="hearing-status">Status</label>
          <select id="hearing-status" v-model="form.hearing_status" class="bw-select">
            <option v-for="s in HEARING_STATUSES" :key="s" :value="s">{{ humanizeEnum(s) }}</option>
          </select>
        </div>
        <div v-if="isDeadline">
          <label class="bw-label" for="deadline-subtype">Deadline category</label>
          <select id="deadline-subtype" v-model="form.deadline_subtype" class="bw-select">
            <option v-for="d in DEADLINE_SUBTYPES" :key="d" :value="d">{{ humanizeEnum(d) }}</option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="event-owner">Owner</label>
          <select id="event-owner" v-model="form.user_id" class="bw-select" required>
            <option :value="null">Choose owner</option>
            <option v-for="user in userOptions" :key="user.id" :value="user.id">
              {{ user.name }}
            </option>
          </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="event-starts">Starts</label>
            <input
              id="event-starts"
              v-model="form.starts_at"
              required
              type="datetime-local"
              class="bw-input"
            />
          </div>
          <div>
            <label class="bw-label" for="event-ends">Ends</label>
            <input id="event-ends" v-model="form.ends_at" type="datetime-local" class="bw-input" />
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="reminder-days">Remind days before</label>
            <input
              id="reminder-days"
              v-model.number="form.reminder_days_before"
              type="number"
              min="0"
              max="365"
              class="bw-input"
            />
          </div>
          <div>
            <label class="bw-label" for="event-reminder">Or fixed reminder time</label>
            <input id="event-reminder" v-model="form.reminder_at" type="datetime-local" class="bw-input" />
          </div>
        </div>
        <template v-if="isHearing">
          <div>
            <label class="bw-label" for="court-name">Court</label>
            <input id="court-name" v-model="form.court_name" class="bw-input" placeholder="Superior Court" />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="bw-label" for="court-room">Room</label>
              <input id="court-room" v-model="form.court_room" class="bw-input" />
            </div>
            <div>
              <label class="bw-label" for="judge-name">Judge</label>
              <input id="judge-name" v-model="form.judge_name" class="bw-input" />
            </div>
          </div>
        </template>
        <div>
          <label class="bw-label" for="event-location">Location</label>
          <input
            id="event-location"
            v-model="form.location"
            class="bw-input"
            placeholder="Court, office, video link"
          />
        </div>
        <div>
          <label class="bw-label" for="event-details">Details</label>
          <textarea
            id="event-details"
            v-model="form.description"
            rows="3"
            class="bw-textarea"
            placeholder="Venue, filing notes, or who should attend."
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showAddModal = false">
          Cancel
        </button>
        <button type="submit" form="event-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Add event' }}
        </button>
      </template>
    </BwModal>
</template>
