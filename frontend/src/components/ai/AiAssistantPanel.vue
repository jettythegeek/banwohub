<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { PhPaperPlaneTilt, PhRobot } from '@phosphor-icons/vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { aiApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { AiChatMessage, AiGovernanceSettings } from '@/types'

const props = defineProps<{
  initialPrompt?: string
}>()

const settings = ref<AiGovernanceSettings | null>(null)
const messages = ref<AiChatMessage[]>([])
const draft = ref('')
const isLoading = ref(true)
const isSending = ref(false)
const error = ref<string | null>(null)
const stubMode = ref(false)

function formatWhen(iso: string) {
  return new Date(iso).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function newId() {
  return `msg-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
}

async function loadSettings() {
  isLoading.value = true
  error.value = null
  try {
    const [govSettings, health] = await Promise.all([
      aiApi.governanceSettings(),
      aiApi.health(),
    ])
    settings.value = govSettings
    stubMode.value = health.stub_mode
  } catch (err) {
    error.value = formatApiError(err, 'AI assistant is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function sendMessage() {
  const text = draft.value.trim()
  if (!text || isSending.value) return

  error.value = null
  isSending.value = true
  draft.value = ''

  const userMessage: AiChatMessage = {
    id: newId(),
    role: 'user',
    content: text,
    created_at: new Date().toISOString(),
  }
  messages.value.push(userMessage)

  try {
    const response = await aiApi.chat({ message: text, context: 'staff' })
    messages.value.push({
      id: newId(),
      role: 'assistant',
      content: response.content,
      label: response.label,
      disclaimer: response.disclaimer,
      requires_review: response.requires_review,
      output_id: response.output_id,
      created_at: new Date().toISOString(),
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not get an AI response.')
    messages.value = messages.value.filter((m) => m.id !== userMessage.id)
    draft.value = text
  } finally {
    isSending.value = false
  }
}

watch(
  () => props.initialPrompt,
  (value) => {
    if (value && !draft.value) {
      draft.value = value
    }
  },
  { immediate: true },
)

onMounted(loadSettings)
</script>

<template>
  <div class="bw-card flex min-h-[480px] flex-col overflow-hidden">
    <div class="border-b border-border px-4 py-3">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <span
            class="flex h-8 w-8 items-center justify-center rounded-md bg-primary-muted text-primary"
          >
            <PhRobot class="h-4 w-4" weight="fill" />
          </span>
          <div>
            <h2 class="text-sm font-semibold text-foreground">Staff AI assistant</h2>
            <p class="text-xs text-muted-foreground">
              Policies, procedures, and practice support
            </p>
          </div>
        </div>
        <span
          v-if="stubMode"
          class="bw-badge bw-badge-warning"
        >
          AI unavailable
        </span>
      </div>
    </div>

    <div class="flex flex-1 flex-col gap-4 p-4">
      <Skeleton v-if="isLoading" variant="form" :rows="3" />
      <template v-else>
        <AiDisclaimerBanner
          v-if="settings?.disclaimer"
          :disclaimer="settings.disclaimer"
          compact
        />

        <div
          class="flex min-h-[280px] flex-1 flex-col gap-3 overflow-y-auto rounded-lg border border-border bg-surface p-4"
        >
          <EmptyState
            v-if="!messages.length"
            title="Ask a question"
            message="Get help with firm policies, workflows, and general practice guidance."
            :icon="PhRobot"
          />
          <article
            v-for="message in messages"
            :key="message.id"
            class="flex flex-col gap-2"
            :class="message.role === 'user' ? 'items-end' : 'items-start'"
          >
            <div
              class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
              :class="
                message.role === 'user'
                  ? 'bg-primary text-primary-foreground'
                  : 'border border-border bg-surface text-foreground'
              "
            >
              <p class="whitespace-pre-wrap">{{ message.content }}</p>
            </div>
            <div
              v-if="message.role === 'assistant'"
              class="max-w-[85%] space-y-2"
            >
              <AiOutputBadges
                :label="message.label"
                :requires-review="message.requires_review"
              />
              <p
                v-if="message.disclaimer"
                class="text-[11px] text-muted-foreground"
              >
                {{ message.disclaimer }}
              </p>
            </div>
            <time class="text-[11px] text-muted-foreground">
              {{ formatWhen(message.created_at) }}
            </time>
          </article>
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <form class="flex gap-2" @submit.prevent="sendMessage">
          <label class="sr-only" for="ai-chat-input">Message</label>
          <input
            id="ai-chat-input"
            v-model="draft"
            type="text"
            class="bw-input flex-1"
            placeholder="Ask about policies, workflows, or practice procedures…"
            :disabled="isSending"
            maxlength="8000"
          />
          <button
            type="submit"
            class="bw-btn bw-btn-primary shrink-0"
            :disabled="isSending || !draft.trim()"
            aria-label="Send message"
          >
            <PhPaperPlaneTilt class="h-4 w-4" weight="fill" />
          </button>
        </form>
      </template>
    </div>
  </div>
</template>
