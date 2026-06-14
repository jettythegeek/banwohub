<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { PhSignature } from '@phosphor-icons/vue'
import { signaturesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { usePermissions } from '@/composables/usePermissions'
import type { SignatureRequest } from '@/types'

const props = defineProps<{
  documentId: number
  caseId: number
  documentName?: string
}>()

const emit = defineEmits<{
  sent: []
}>()

const { can } = usePermissions()

const request = ref<SignatureRequest | null>(null)
const isLoading = ref(true)
const isSending = ref(false)
const error = ref<string | null>(null)
const message = ref('')
const showForm = ref(false)

const canSend = computed(() => can('signatures.send'))
const isPending = computed(() => request.value?.status === 'pending')
const isSigned = computed(() => request.value?.status === 'signed')
const isDeclined = computed(() => request.value?.status === 'declined')

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const { requests } = await signaturesApi.list({
      document_id: props.documentId,
      legal_matter_id: props.caseId,
      per_page: 1,
    })
    request.value = requests[0] ?? null
  } catch (err) {
    error.value = formatApiError(err, 'Signature status is unavailable.')
  } finally {
    isLoading.value = false
  }
}

async function sendForSignature() {
  isSending.value = true
  error.value = null
  try {
    request.value = await signaturesApi.send({
      document_id: props.documentId,
      message: message.value || undefined,
    })
    message.value = ''
    showForm.value = false
    emit('sent')
  } catch (err) {
    error.value = formatApiError(err, 'We could not send this document for signature.')
  } finally {
    isSending.value = false
  }
}

watch(
  () => [props.documentId, props.caseId] as const,
  () => load(),
  { immediate: true },
)
</script>

<template>
  <section v-if="canSend" class="rounded-lg border border-border p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <PhSignature class="h-4 w-4 text-primary-700" weight="fill" />
        <h3 class="text-sm font-semibold text-foreground">E-signature</h3>
      </div>
      <span
        v-if="request"
        class="bw-badge"
        :class="{
          'bw-badge-warning': isPending,
          'bw-badge-success': isSigned,
          'bw-badge-danger': isDeclined,
        }"
      >
        {{ request.status }}
      </span>
    </div>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <p v-if="isLoading" class="text-sm text-muted-foreground">Loading signature status…</p>

    <template v-else-if="request">
      <p class="text-sm text-muted-foreground">
        <template v-if="isPending">
          Waiting for client to sign{{ documentName ? ` "${documentName}"` : '' }}.
        </template>
        <template v-else-if="isSigned">
          Signed {{ request.signed_at ? new Date(request.signed_at).toLocaleString() : '' }}.
          <span v-if="request.signed_document">
            Copy saved as {{ request.signed_document.name }}.
          </span>
        </template>
        <template v-else-if="isDeclined">
          Client declined to sign this document.
        </template>
      </p>
      <button
        v-if="isDeclined"
        type="button"
        class="bw-btn bw-btn-outline bw-btn-sm"
        @click="showForm = true"
      >
        Send again
      </button>
    </template>

    <template v-else-if="!showForm">
      <p class="text-sm text-muted-foreground">
        Send this document to the client for electronic signature via the portal.
      </p>
      <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="showForm = true">
        Send for signature
      </button>
    </template>

    <form v-else class="space-y-3" @submit.prevent="sendForSignature">
      <div>
        <label class="bw-label" for="sig-message">Message to client (optional)</label>
        <textarea
          id="sig-message"
          v-model="message"
          class="bw-input min-h-[72px]"
          placeholder="Please review and sign this document."
        />
      </div>
      <p class="text-xs text-muted-foreground">
        Default fields: signature, date, and full legal name. Document will be shared with the client.
      </p>
      <div class="flex flex-wrap gap-2">
        <button type="submit" class="bw-btn bw-btn-primary bw-btn-sm" :disabled="isSending">
          {{ isSending ? 'Sending…' : 'Send for signature' }}
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-ghost bw-btn-sm"
          @click="showForm = false"
        >
          Cancel
        </button>
      </div>
    </form>
  </section>
</template>
