<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  PhChatCircle,
  PhEnvelope,
  PhPaperPlaneTilt,
  PhPhone,
  PhRobot,
  PhUser,
} from '@phosphor-icons/vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { publicChatApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { AiChatMessage } from '@/types'

const PUBLIC_DISCLAIMER =
  'This AI assistant provides general information only — not legal advice. For advice about your situation, please contact Banwolaw to schedule a consultation with a qualified attorney.'

const FAQ_PROMPTS = [
  'What services does Banwolaw offer?',
  'How do I schedule a consultation?',
  'What is the intake process for new clients?',
  'How can I contact your office?',
]

const messages = ref<AiChatMessage[]>([])
const draft = ref('')
const name = ref('')
const email = ref('')
const phone = ref('')
const sessionId = ref<string | null>(null)
const isSending = ref(false)
const error = ref<string | null>(null)
const leadSaved = ref(false)

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

function loadSession() {
  if (typeof window === 'undefined') return
  sessionId.value = localStorage.getItem('banwohub_public_chat_session')
}

function persistSession(id: string) {
  sessionId.value = id
  if (typeof window !== 'undefined') {
    localStorage.setItem('banwohub_public_chat_session', id)
  }
}

function hasLeadInfo() {
  return name.value.trim() || email.value.trim() || phone.value.trim()
}

async function sendMessage(text?: string) {
  const message = (text ?? draft.value).trim()
  if (!message || isSending.value) return

  error.value = null
  isSending.value = true
  draft.value = ''

  const userMessage: AiChatMessage = {
    id: newId(),
    role: 'user',
    content: message,
    created_at: new Date().toISOString(),
  }
  messages.value.push(userMessage)

  try {
    const response = await publicChatApi.chat({
      message,
      session_id: sessionId.value ?? undefined,
      name: name.value.trim() || undefined,
      email: email.value.trim() || undefined,
      phone: phone.value.trim() || undefined,
    })

    if (response.session_id) {
      persistSession(response.session_id)
    }
    if (response.lead_captured) {
      leadSaved.value = true
    }

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
    error.value = formatApiError(err, 'We could not get a response. Please try again.')
    messages.value = messages.value.filter((m) => m.id !== userMessage.id)
    draft.value = message
  } finally {
    isSending.value = false
  }
}

function usePrompt(prompt: string) {
  draft.value = prompt
  void sendMessage(prompt)
}

onMounted(loadSession)
</script>

<template>
  <div class="flex min-h-screen flex-col bg-background">
    <header class="border-b border-border bg-surface">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <RouterLink to="/support" class="text-lg font-semibold tracking-tight text-primary">
          Banwolaw Hub
        </RouterLink>
        <nav class="flex items-center gap-3 text-sm">
          <RouterLink
            to="/login"
            class="text-muted-foreground transition-colors hover:text-foreground"
          >
            Staff sign in
          </RouterLink>
          <RouterLink
            to="/portal/login"
            class="bw-btn bw-btn-primary text-sm"
          >
            Client portal
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:flex-row lg:py-10">
      <section class="flex min-h-[520px] flex-1 flex-col overflow-hidden rounded-lg border border-border bg-surface">
        <div class="border-b border-border px-4 py-4 sm:px-6">
          <div class="flex items-center gap-3">
            <span
              class="flex h-10 w-10 items-center justify-center rounded-md bg-primary-muted text-primary"
            >
              <PhRobot class="h-5 w-5" weight="fill" />
            </span>
            <div>
              <h1 class="text-base font-semibold text-foreground">Support assistant</h1>
              <p class="text-sm text-muted-foreground">
                General questions about Banwolaw services and consultations
              </p>
            </div>
          </div>
        </div>

        <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
          <AiDisclaimerBanner :disclaimer="PUBLIC_DISCLAIMER" compact />

          <div
            class="flex min-h-[300px] flex-1 flex-col gap-3 overflow-y-auto rounded-lg border border-border bg-surface p-4"
          >
            <EmptyState
              v-if="!messages.length"
              title="How can we help?"
              message="Ask about our services, intake process, or booking a consultation."
              :icon="PhChatCircle"
            />
            <article
              v-for="message in messages"
              :key="message.id"
              class="flex flex-col gap-2"
              :class="message.role === 'user' ? 'items-end' : 'items-start'"
            >
              <div
                class="max-w-[90%] rounded-lg px-3 py-2 text-sm sm:max-w-[85%]"
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
                class="max-w-[90%] space-y-2 sm:max-w-[85%]"
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

          <div v-if="!messages.length" class="flex flex-wrap gap-2">
            <button
              v-for="prompt in FAQ_PROMPTS"
              :key="prompt"
              type="button"
              class="rounded-md border border-border bg-surface px-3 py-1.5 text-xs text-foreground transition-colors hover:bg-surface-muted"
              @click="usePrompt(prompt)"
            >
              {{ prompt }}
            </button>
          </div>

          <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

          <form class="flex gap-2" @submit.prevent="sendMessage()">
            <label class="sr-only" for="public-chat-input">Message</label>
            <input
              id="public-chat-input"
              v-model="draft"
              type="text"
              class="bw-input flex-1"
              placeholder="Ask a general question about our services…"
              :disabled="isSending"
              maxlength="4000"
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
        </div>
      </section>

      <aside class="w-full shrink-0 lg:w-80">
        <div class="rounded-lg border border-border bg-surface p-5">
          <h2 class="text-sm font-semibold text-foreground">Request a callback</h2>
          <p class="mt-1 text-xs text-muted-foreground">
            Optional — share contact details and we will follow up. Do not include confidential case
            details.
          </p>

          <form class="mt-4 space-y-3" @submit.prevent="sendMessage()">
            <div class="space-y-1">
              <label for="lead-name" class="text-xs font-medium text-foreground">Name</label>
              <div class="relative">
                <PhUser
                  class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                  id="lead-name"
                  v-model="name"
                  type="text"
                  class="bw-input w-full pl-9"
                  placeholder="Your name"
                  maxlength="120"
                />
              </div>
            </div>
            <div class="space-y-1">
              <label for="lead-email" class="text-xs font-medium text-foreground">Email</label>
              <div class="relative">
                <PhEnvelope
                  class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                  id="lead-email"
                  v-model="email"
                  type="email"
                  class="bw-input w-full pl-9"
                  placeholder="you@email.com"
                  maxlength="255"
                />
              </div>
            </div>
            <div class="space-y-1">
              <label for="lead-phone" class="text-xs font-medium text-foreground">Phone</label>
              <div class="relative">
                <PhPhone
                  class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                  id="lead-phone"
                  v-model="phone"
                  type="tel"
                  class="bw-input w-full pl-9"
                  placeholder="(555) 555-5555"
                  maxlength="40"
                />
              </div>
            </div>
            <p
              v-if="leadSaved && hasLeadInfo()"
              class="text-xs text-status-success-foreground"
              role="status"
            >
              Thank you — your contact details were saved with your message.
            </p>
            <p class="text-[11px] text-muted-foreground">
              Submitting contact info with a chat message helps our team reach you. This does not
              create an attorney-client relationship.
            </p>
          </form>
        </div>

        <div class="mt-4 rounded-lg border border-border bg-primary px-5 py-4 text-primary-foreground">
          <p class="text-sm font-semibold">Need to speak with someone?</p>
          <p class="mt-1 text-xs text-primary-foreground/85">
            For urgent matters or specific legal advice, contact our office directly or schedule a
            consultation with an attorney.
          </p>
        </div>
      </aside>
    </main>

    <footer class="border-t border-border py-4 text-center text-xs text-muted-foreground">
      © {{ new Date().getFullYear() }} Banwolaw Hub — general information only, not legal advice.
    </footer>
  </div>
</template>
