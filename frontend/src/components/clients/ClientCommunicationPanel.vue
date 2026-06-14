<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  PhChatCircle,
  PhEnvelopeSimple,
  PhNote,
  PhPhone,
  PhPlus,
  PhUsersThree,
} from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { communicationLogsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { CommunicationLog, CommunicationLogChannel } from '@/types'

const props = defineProps<{
  clientId: number
  embedded?: boolean
}>()

const logs = ref<CommunicationLog[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const isSaving = ref(false)
const showModal = ref(false)
const editingId = ref<number | null>(null)

const channelOptions: { value: CommunicationLogChannel; label: string }[] = [
  { value: 'phone', label: 'Phone call' },
  { value: 'email', label: 'Email' },
  { value: 'meeting', label: 'Meeting' },
  { value: 'note', label: 'Note' },
]

const form = ref({
  channel: 'phone' as CommunicationLogChannel,
  subject: '',
  body: '',
  client_feedback: '',
  satisfaction_score: null as number | null,
})

const channelMeta: Record<
  CommunicationLogChannel,
  { label: string; icon: typeof PhPhone }
> = {
  in_app: { label: 'In-app message', icon: PhChatCircle },
  email: { label: 'Email', icon: PhEnvelopeSimple },
  phone: { label: 'Phone call', icon: PhPhone },
  meeting: { label: 'Meeting', icon: PhUsersThree },
  note: { label: 'Note', icon: PhNote },
}

const sortedLogs = computed(() =>
  [...logs.value].sort((a, b) => {
    const aTime = a.occurred_at ? new Date(a.occurred_at).getTime() : 0
    const bTime = b.occurred_at ? new Date(b.occurred_at).getTime() : 0
    return bTime - aTime
  }),
)

function formatDateTime(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

function resetForm() {
  form.value = {
    channel: 'phone',
    subject: '',
    body: '',
    client_feedback: '',
    satisfaction_score: null,
  }
  editingId.value = null
}

function openCreate() {
  resetForm()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  resetForm()
}

async function loadLogs() {
  isLoading.value = true
  error.value = null
  try {
    logs.value = await communicationLogsApi.list({ client_id: props.clientId })
  } catch (err) {
    error.value = formatApiError(err, 'Communication history is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function saveLog() {
  isSaving.value = true
  error.value = null
  try {
    if (editingId.value) {
      await communicationLogsApi.update(editingId.value, {
        client_feedback: form.value.client_feedback || null,
        satisfaction_score: form.value.satisfaction_score,
      })
    } else {
      await communicationLogsApi.create({
        client_id: props.clientId,
        channel: form.value.channel,
        subject: form.value.subject || null,
        body: form.value.body || null,
        client_feedback: form.value.client_feedback || null,
        satisfaction_score: form.value.satisfaction_score,
      })
    }
    closeModal()
    await loadLogs()
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isSaving.value = false
  }
}

function startFeedbackEdit(log: CommunicationLog) {
  editingId.value = log.id
  form.value = {
    channel: log.channel,
    subject: log.subject ?? '',
    body: log.body ?? '',
    client_feedback: log.client_feedback ?? '',
    satisfaction_score: log.satisfaction_score ?? null,
  }
  showModal.value = true
}

onMounted(loadLogs)
</script>

<template>
  <section :class="embedded ? '' : 'bw-card overflow-hidden'">
    <div :class="embedded ? 'mb-4 flex items-start justify-between gap-4' : 'bw-card-header'">
      <div>
        <h2 class="font-semibold text-foreground">Communication history</h2>
        <p class="text-sm text-muted-foreground">
          Calls, emails, meetings, in-app messages, and client feedback.
        </p>
      </div>
      <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="openCreate">
        <PhPlus class="h-4 w-4" weight="bold" />
        Log communication
      </button>
    </div>

    <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

    <Skeleton v-if="isLoading" variant="panel" :rows="4" />

    <div
      v-else-if="sortedLogs.length"
      :class="embedded ? 'bw-card divide-y divide-border overflow-hidden' : 'divide-y divide-border'"
    >
      <article
        v-for="log in sortedLogs"
        :key="log.id"
        class="px-5 py-4"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <component
              :is="channelMeta[log.channel]?.icon ?? PhNote"
              class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
            />
            <div class="min-w-0">
              <p class="font-medium text-foreground">
                {{ log.subject || channelMeta[log.channel]?.label || log.channel }}
              </p>
              <p class="text-xs text-muted-foreground">
                {{ channelMeta[log.channel]?.label || log.channel }}
                <span v-if="log.logged_by?.name"> · {{ log.logged_by.name }}</span>
                <span v-if="log.case?.title"> · {{ log.case.title }}</span>
              </p>
            </div>
          </div>
          <time class="text-xs tabular-nums text-muted-foreground">
            {{ formatDateTime(log.occurred_at) }}
          </time>
        </div>

        <p v-if="log.body" class="mt-2 whitespace-pre-wrap text-sm text-foreground">
          {{ log.body }}
        </p>

        <div
          v-if="log.client_feedback || log.satisfaction_score"
          class="mt-3 rounded-lg border border-border bg-surface px-3 py-2 text-sm"
        >
          <p v-if="log.satisfaction_score" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Satisfaction: {{ log.satisfaction_score }}/5
          </p>
          <p v-if="log.client_feedback" class="mt-1 whitespace-pre-wrap text-foreground">
            {{ log.client_feedback }}
          </p>
        </div>

        <button
          v-if="!log.message_thread_id"
          type="button"
          class="mt-2 text-xs text-primary hover:underline"
          @click="startFeedbackEdit(log)"
        >
          {{ log.client_feedback || log.satisfaction_score ? 'Edit feedback' : 'Add feedback' }}
        </button>
      </article>
    </div>

    <EmptyState
      v-else
      :class="embedded ? 'bw-card py-12' : 'py-12'"
      title="No communication logged"
      description="Log calls, emails, and meetings, or send in-app messages to build history."
      :icon="PhChatCircle"
    />

    <BwModal
      :open="showModal"
      :title="editingId ? 'Update client feedback' : 'Log communication'"
      @close="closeModal"
    >
      <form class="space-y-4" @submit.prevent="saveLog">
        <template v-if="!editingId">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="bw-label">Channel</label>
              <select v-model="form.channel" class="bw-select">
                <option v-for="opt in channelOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="bw-label">Subject</label>
              <input v-model="form.subject" type="text" class="bw-input" />
            </div>
          </div>
          <div>
            <label class="bw-label">Details</label>
            <textarea v-model="form.body" rows="3" class="bw-textarea" />
          </div>
        </template>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label">Client feedback</label>
            <textarea
              v-model="form.client_feedback"
              rows="2"
              class="bw-textarea"
              placeholder="Service feedback or satisfaction note"
            />
          </div>
          <div>
            <label class="bw-label">Satisfaction (1–5)</label>
            <select v-model="form.satisfaction_score" class="bw-select">
              <option :value="null">Not rated</option>
              <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-border pt-4">
          <button type="button" class="bw-btn bw-btn-outline" @click="closeModal">Cancel</button>
          <button type="submit" class="bw-btn bw-btn-action" :disabled="isSaving">
            {{ isSaving ? 'Saving…' : editingId ? 'Save feedback' : 'Add log entry' }}
          </button>
        </div>
      </form>
    </BwModal>
  </section>
</template>
