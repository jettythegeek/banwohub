<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhArrowLeft, PhChatCircle, PhPaperPlaneTilt, PhPlus, PhUser } from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { messageThreadsApi, getStoredToken } from '@/lib/api'
import { portalMessagesApi, getStoredPortalToken } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import { createEcho, isEchoConfigured, type EchoInstance } from '@/lib/echo'
import { usePermissions } from '@/composables/usePermissions'
import type { Message, MessageThread } from '@/types'

const props = withDefaults(
  defineProps<{
    mode?: 'staff' | 'portal'
    caseId?: number | null
    clientId?: number | null
    initialThreadId?: number | null
    compact?: boolean
  }>(),
  {
    mode: 'staff',
    caseId: null,
    clientId: null,
    initialThreadId: null,
    compact: false,
  },
)

const { can } = usePermissions()
const canCreate = computed(() =>
  props.mode === 'portal' ? true : can('messages.create'),
)

const threads = ref<MessageThread[]>([])
const activeThread = ref<MessageThread | null>(null)
const isLoading = ref(true)
const isSending = ref(false)
const isCreating = ref(false)
const error = ref<string | null>(null)
const replyBody = ref('')
const showNewModal = ref(false)
const newSubject = ref('')
const newBody = ref('')
const mobilePane = ref<'inbox' | 'thread' | 'profile'>('inbox')

let pollTimer: ReturnType<typeof setInterval> | undefined
let echo: EchoInstance | null = null
let subscribedThreadId: number | null = null

const profileClient = computed(() => {
  if (activeThread.value?.client) return activeThread.value.client
  if (props.clientId && props.mode === 'staff') {
    return { id: props.clientId, name: 'Client', email: null }
  }
  return null
})

const echoEnabled = isEchoConfigured()

const api = computed(() => (props.mode === 'portal' ? portalMessagesApi : messageThreadsApi))

function formatWhen(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

async function loadThreads(selectId?: number | null) {
  error.value = null
  try {
    const filters =
      props.mode === 'staff'
        ? {
            legal_matter_id: props.caseId || undefined,
            client_id: props.clientId || undefined,
          }
        : props.caseId || undefined

    const list =
      props.mode === 'portal'
        ? await portalMessagesApi.list(props.caseId || undefined)
        : await messageThreadsApi.list(filters)

    threads.value = list

    const targetId = selectId ?? activeThread.value?.id ?? props.initialThreadId
    if (targetId) {
      const existing = list.find((t) => t.id === targetId)
      if (existing) {
        await selectThread(existing.id)
        return
      }
    }
    if (!activeThread.value && list.length && !props.compact) {
      await selectThread(list[0].id)
    }
  } catch (err) {
    error.value = formatApiError(err, 'Messages are not available yet.')
  }
}

async function selectThread(id: number) {
  try {
    const thread = await api.value.get(id)
    activeThread.value = thread
    if (!props.compact) mobilePane.value = 'thread'
    setupEcho(id)
    await api.value.markRead(id)
    threads.value = threads.value.map((t) =>
      t.id === id ? { ...t, unread_count: 0 } : t,
    )
  } catch (err) {
    error.value = formatApiError(err, 'Could not load this conversation.')
  }
}

async function sendReply() {
  if (!activeThread.value || !replyBody.value.trim()) return
  isSending.value = true
  error.value = null
  try {
    await api.value.sendMessage(activeThread.value.id, replyBody.value.trim())
    replyBody.value = ''
    await loadThreads(activeThread.value.id)
  } catch (err) {
    error.value = formatApiError(err, 'Could not send message.')
  } finally {
    isSending.value = false
  }
}

async function createThread() {
  if (!newSubject.value.trim() || !newBody.value.trim()) return
  isCreating.value = true
  error.value = null
  try {
    const payload =
      props.mode === 'portal'
        ? {
            legal_matter_id: props.caseId || null,
            subject: newSubject.value.trim(),
            body: newBody.value.trim(),
          }
        : {
            client_id: props.clientId!,
            legal_matter_id: props.caseId || null,
            subject: newSubject.value.trim(),
            body: newBody.value.trim(),
          }

    const thread =
      props.mode === 'portal'
        ? await portalMessagesApi.create(payload as { legal_matter_id?: number | null; subject: string; body: string })
        : await messageThreadsApi.create(payload as { client_id: number; legal_matter_id?: number | null; subject: string; body: string })

    newSubject.value = ''
    newBody.value = ''
    showNewModal.value = false
    await loadThreads(thread.id)
  } catch (err) {
    error.value = formatApiError(err, 'Could not start conversation.')
  } finally {
    isCreating.value = false
  }
}

function isOwnMessage(message: Message) {
  if (props.mode === 'portal') {
    return message.sender?.is_client === true
  }
  return message.sender?.is_client !== true
}

async function refresh() {
  await loadThreads(activeThread.value?.id ?? props.initialThreadId)
}

function mergeIncomingMessage(message: Message) {
  if (!activeThread.value || message.message_thread_id !== activeThread.value.id) {
    void loadThreads(activeThread.value?.id ?? props.initialThreadId)
    return
  }

  const existing = activeThread.value.messages ?? []
  if (existing.some((entry) => entry.id === message.id)) {
    return
  }

  activeThread.value = {
    ...activeThread.value,
    messages: [...existing, message],
    last_message_at: message.created_at ?? activeThread.value.last_message_at,
    latest_message: message,
  }

  threads.value = threads.value.map((thread) =>
    thread.id === activeThread.value?.id
      ? {
          ...thread,
          last_message_at: message.created_at ?? thread.last_message_at,
          latest_message: message,
          unread_count:
            message.sender?.is_client === (props.mode === 'portal')
              ? thread.unread_count
              : (thread.unread_count ?? 0) + 1,
        }
      : thread,
  )
}

function teardownEcho() {
  if (echo && subscribedThreadId !== null) {
    echo.leave(`message-thread.${subscribedThreadId}`)
  }
  echo?.disconnect()
  echo = null
  subscribedThreadId = null
}

function setupEcho(threadId: number) {
  if (!echoEnabled || subscribedThreadId === threadId) {
    return
  }

  teardownEcho()

  const token =
    props.mode === 'portal' ? getStoredPortalToken() : getStoredToken()
  echo = createEcho(token)
  if (!echo) {
    return
  }

  subscribedThreadId = threadId
  echo
    .private(`message-thread.${threadId}`)
    .listen('.message.sent', (payload: { message?: Message }) => {
      if (payload.message) {
        mergeIncomingMessage(payload.message)
      } else {
        void refresh()
      }
    })
}

onMounted(async () => {
  isLoading.value = true
  await loadThreads(props.initialThreadId)
  isLoading.value = false
  if (!echoEnabled) {
    pollTimer = setInterval(refresh, 15000)
  }
})

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer)
  teardownEcho()
})

watch(
  () => [props.caseId, props.clientId, props.initialThreadId],
  async () => {
    isLoading.value = true
    activeThread.value = null
    mobilePane.value = 'inbox'
    teardownEcho()
    await loadThreads(props.initialThreadId)
    isLoading.value = false
  },
)
</script>

<template>
  <div>
    <p v-if="error" class="px-4 pt-4 text-sm text-destructive" role="alert">{{ error }}</p>

    <div
      class="grid divide-border"
      :class="
        compact
          ? 'grid-cols-1'
          : 'min-h-[480px] lg:grid-cols-[280px_minmax(0,1fr)_280px] lg:divide-x'
      "
    >
      <aside
        v-if="!compact"
        class="border-b border-border lg:border-b-0"
        :class="mobilePane === 'inbox' ? 'block' : 'hidden lg:block'"
      >
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Inbox
          </h3>
          <button
            v-if="canCreate && (mode === 'portal' || clientId)"
            type="button"
            class="bw-btn bw-btn-accent-icon"
            aria-label="New conversation"
            @click="showNewModal = true"
          >
            <PhPlus class="h-4 w-4" weight="bold" />
          </button>
        </div>

        <div v-if="isLoading" class="space-y-2 p-4">
          <Skeleton v-for="n in 3" :key="n" class="h-14 rounded-md" />
        </div>
        <ul v-else-if="threads.length" class="max-h-[520px] divide-y divide-border overflow-y-auto lg:max-h-none">
          <li v-for="thread in threads" :key="thread.id">
            <button
              type="button"
              class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-muted"
              :class="activeThread?.id === thread.id ? 'bw-row-selected' : ''"
              @click="selectThread(thread.id)"
            >
              <AppAvatar
                :name="thread.client?.name || thread.subject"
                size="sm"
                tone="accent"
                class="shrink-0"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <span class="line-clamp-1 text-sm font-medium text-foreground">
                    {{ thread.subject }}
                  </span>
                  <span
                    v-if="thread.unread_count"
                    class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-destructive px-1.5 text-[10px] font-semibold text-white"
                  >
                    {{ thread.unread_count }}
                  </span>
                </div>
                <p class="line-clamp-1 text-xs text-muted-foreground">
                  {{ thread.latest_message?.body || 'No messages yet' }}
                </p>
                <p class="text-xs text-muted-foreground">
                  {{ formatWhen(thread.last_message_at) }}
                  <span v-if="thread.case"> · {{ thread.case.title }}</span>
                </p>
              </div>
            </button>
          </li>
        </ul>
        <EmptyState
          v-else
          :icon="PhChatCircle"
          title="No conversations"
          message="Start a secure message thread with your legal team."
          class="p-4"
        />
      </aside>

      <section
        class="flex min-h-[320px] flex-col border-b border-border lg:border-b-0"
        :class="compact || mobilePane === 'thread' ? 'flex' : 'hidden lg:flex'"
      >
        <div
          v-if="compact"
          class="space-y-4 border-b border-border p-4"
        >
          <div v-if="canCreate" class="flex justify-end">
            <button
              type="button"
              class="bw-btn bw-btn-accent bw-btn-sm"
              @click="showNewModal = true"
            >
              <PhPlus class="h-4 w-4" weight="bold" />
              New message
            </button>
          </div>
          <label v-if="threads.length" class="block text-sm">
            <span class="mb-1 block text-muted-foreground">Conversation</span>
            <select
              class="bw-input w-full"
              :value="activeThread?.id ?? ''"
              @change="selectThread(Number(($event.target as HTMLSelectElement).value))"
            >
              <option v-for="thread in threads" :key="thread.id" :value="thread.id">
                {{ thread.subject }}{{ thread.unread_count ? ` (${thread.unread_count})` : '' }}
              </option>
            </select>
          </label>
        </div>

        <div v-if="isLoading && !activeThread" class="flex flex-1 items-center justify-center p-6">
          <Skeleton class="h-32 w-full rounded-md" />
        </div>
        <template v-else-if="activeThread">
          <div class="flex items-center gap-3 border-b border-border bg-surface px-4 py-3">
            <button
              v-if="!compact"
              type="button"
              class="bw-btn bw-btn-ghost bw-btn-icon lg:hidden"
              aria-label="Back to inbox"
              @click="mobilePane = 'inbox'"
            >
              <PhArrowLeft class="h-4 w-4" />
            </button>
            <AppAvatar
              :name="profileClient?.name || activeThread.subject"
              size="sm"
              tone="accent"
              class="shrink-0"
            />
            <div class="min-w-0 flex-1">
              <h3 class="truncate font-semibold text-foreground">{{ activeThread.subject }}</h3>
              <p v-if="activeThread.case" class="truncate text-sm text-muted-foreground">
                {{ activeThread.case.title }}
              </p>
            </div>
            <button
              v-if="!compact"
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm lg:hidden"
              @click="mobilePane = 'profile'"
            >
              <PhUser class="h-4 w-4" />
              Profile
            </button>
          </div>

          <div class="flex-1 space-y-3 overflow-y-auto bg-[var(--background)] p-4">
            <div
              v-for="message in activeThread.messages || []"
              :key="message.id"
              class="flex"
              :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
            >
              <div
                class="max-w-[85%] rounded-lg border border-border px-3 py-2 text-sm shadow-sm"
                :class="isOwnMessage(message) ? 'bg-[var(--selection-teal)]' : 'bg-surface'"
              >
                <p class="whitespace-pre-wrap text-foreground">{{ message.body }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                  {{ message.sender?.name || 'Unknown' }} · {{ formatWhen(message.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <form
            v-if="canCreate"
            class="flex gap-2 border-t border-border bg-surface p-4"
            @submit.prevent="sendReply"
          >
            <textarea
              v-model="replyBody"
              class="bw-input min-h-[44px] flex-1 resize-y"
              placeholder="Write a message…"
              rows="2"
              required
            />
            <button
              type="submit"
              class="bw-btn bw-btn-action inline-flex shrink-0 items-center gap-1 self-end"
              :disabled="isSending || !replyBody.trim()"
            >
              <PhPaperPlaneTilt class="h-4 w-4" />
              Send
            </button>
          </form>
        </template>
        <EmptyState
          v-else-if="!compact"
          :icon="PhChatCircle"
          title="Select a conversation"
          message="Choose a thread from the list or start a new one."
          class="hidden flex-1 p-6 lg:flex"
        />
      </section>

      <aside
        v-if="!compact"
        class="border-t border-border bg-[var(--background)] lg:border-t-0"
        :class="mobilePane === 'profile' ? 'block' : 'hidden lg:block'"
      >
        <div class="flex items-center justify-between border-b border-border bg-surface px-4 py-3">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Profile
          </h3>
          <button
            type="button"
            class="bw-btn bw-btn-ghost bw-btn-sm lg:hidden"
            @click="mobilePane = 'thread'"
          >
            <PhArrowLeft class="h-4 w-4" />
            Back
          </button>
        </div>
        <div v-if="activeThread && profileClient" class="space-y-3 p-4">
          <div class="bw-card p-4">
            <div class="flex items-center gap-3">
              <AppAvatar :name="profileClient.name" size="md" tone="accent" />
              <div class="min-w-0">
                <p class="truncate font-semibold text-foreground">{{ profileClient.name }}</p>
                <p v-if="profileClient.email" class="truncate text-xs text-muted-foreground">
                  {{ profileClient.email }}
                </p>
              </div>
            </div>
          </div>
          <div v-if="activeThread.case" class="bw-card space-y-3 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Linked case
            </p>
            <dl class="space-y-2 text-sm">
              <div>
                <dt class="text-xs text-muted-foreground">Title</dt>
                <dd class="mt-0.5 font-medium text-foreground">{{ activeThread.case.title }}</dd>
              </div>
              <div v-if="activeThread.case.matter_number">
                <dt class="text-xs text-muted-foreground">Matter #</dt>
                <dd class="mt-0.5 text-foreground">{{ activeThread.case.matter_number }}</dd>
              </div>
            </dl>
          </div>
          <RouterLink
            v-if="mode === 'staff' && profileClient.id"
            :to="`/clients/${profileClient.id}`"
            class="bw-btn bw-btn-outline bw-btn-sm w-full"
          >
            View client
          </RouterLink>
        </div>
        <p v-else class="p-4 text-sm text-muted-foreground">
          Select a conversation to view client details.
        </p>
      </aside>
    </div>

    <BwModal
      :open="showNewModal"
      title="New conversation"
      size="md"
      @close="showNewModal = false"
    >
      <form id="new-thread-form" class="space-y-4" @submit.prevent="createThread">
        <div>
          <label class="bw-label" for="thread-subject">Subject</label>
          <input
            id="thread-subject"
            v-model="newSubject"
            type="text"
            class="bw-input"
            placeholder="Subject"
            maxlength="255"
            required
          />
        </div>
        <div>
          <label class="bw-label" for="thread-body">Message</label>
          <textarea
            id="thread-body"
            v-model="newBody"
            class="bw-textarea min-h-[100px]"
            placeholder="Your message…"
            required
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showNewModal = false">
          Cancel
        </button>
        <button
          type="submit"
          form="new-thread-form"
          class="bw-btn bw-btn-action"
          :disabled="isCreating || !clientId && mode === 'staff'"
        >
          {{ isCreating ? 'Sending…' : 'Start conversation' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
