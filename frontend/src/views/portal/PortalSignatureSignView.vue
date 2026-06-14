<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhFile, PhSignature } from '@phosphor-icons/vue'
import SignatureCanvas from '@/components/signatures/SignatureCanvas.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { portalSignaturesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import type { SignatureField, SignatureRequest } from '@/types'

const route = useRoute()
const router = useRouter()
const requestId = Number(route.params.id)

const request = ref<SignatureRequest | null>(null)
const fieldValues = ref<Record<string, string>>({})
const signatureMode = ref<'canvas' | 'typed'>('canvas')
const isLoading = ref(true)
const isSubmitting = ref(false)
const isDeclining = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const declineReason = ref('')
const showDeclineForm = ref(false)

const isPending = computed(() => request.value?.status === 'pending')
const documentHtml = computed(() => request.value?.document?.content_html ?? '')

function initFieldValues(fields: SignatureField[]) {
  const values: Record<string, string> = {}
  for (const field of fields) {
    if (field.type === 'date') {
      values[field.id] = new Date().toISOString().slice(0, 10)
    } else {
      values[field.id] = ''
    }
  }
  fieldValues.value = values
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const data = await portalSignaturesApi.get(requestId)
    request.value = data
    initFieldValues(data.fields ?? [])
  } catch (err) {
    error.value = formatApiError(err, 'This signature request is not available.')
  } finally {
    isLoading.value = false
  }
}

function validateFields(): boolean {
  if (!request.value) return false
  for (const field of request.value.fields) {
    if (field.required !== false && !fieldValues.value[field.id]?.trim()) {
      error.value = `${field.label} is required.`
      return false
    }
  }
  error.value = null
  return true
}

async function submitSignature() {
  if (!request.value || !validateFields()) return
  isSubmitting.value = true
  error.value = null
  try {
    const method = signatureMode.value
    const values = { ...fieldValues.value }
    for (const field of request.value.fields) {
      if (field.type === 'signature' && method === 'typed' && values[field.id]) {
        values[field.id] = `Typed: ${values[field.id]}`
      }
    }
    request.value = await portalSignaturesApi.sign(requestId, {
      field_values: values,
      method,
    })
    success.value = 'Thank you. Your signature has been recorded and your legal team has been notified.'
  } catch (err) {
    error.value = formatApiError(err, 'We could not submit your signature.')
  } finally {
    isSubmitting.value = false
  }
}

async function declineSignature() {
  isDeclining.value = true
  error.value = null
  try {
    request.value = await portalSignaturesApi.decline(requestId, declineReason.value || undefined)
    success.value = 'You declined to sign this document. Your legal team has been notified.'
    showDeclineForm.value = false
  } catch (err) {
    error.value = formatApiError(err, 'We could not record your response.')
  } finally {
    isDeclining.value = false
  }
}

function goToCase() {
  if (request.value?.legal_matter_id) {
    router.push(`/portal/cases/${request.value.legal_matter_id}`)
  } else {
    router.push('/portal/cases')
  }
}

onMounted(load)
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div v-if="isLoading">
      <Skeleton class="mb-4 h-8 w-64 rounded-md" />
      <Skeleton class="h-64 rounded-lg" />
    </div>

    <template v-else-if="request">
      <PageHeader
        :title="request.document?.name ?? 'Sign document'"
        :subtitle="request.legal_matter?.title ?? 'Electronic signature'"
      />

      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <p v-if="success" class="rounded-lg border border-border bg-surface p-4 text-sm text-primary">
        {{ success }}
      </p>

      <section v-if="request.message" class="bw-card p-5">
        <p class="text-sm text-muted-foreground">Message from your legal team</p>
        <p class="mt-1 text-foreground">{{ request.message }}</p>
      </section>

      <section v-if="documentHtml" class="bw-card p-5">
        <h2 class="mb-3 font-semibold">Document preview</h2>
        <div
          class="prose prose-sm max-w-none rounded-lg border border-border bg-white p-4 text-sm"
          v-html="documentHtml"
        />
      </section>
      <section v-else class="bw-card p-5 text-sm text-muted-foreground">
        <PhFile class="mb-2 h-6 w-6" />
        Document content is attached as a file. Contact your legal team if you need a preview.
      </section>

      <section v-if="isPending && !success" class="bw-card space-y-5 p-5">
        <div class="flex items-center gap-2">
          <PhSignature class="h-5 w-5 text-primary-700" weight="fill" />
          <h2 class="font-semibold">Complete your signature</h2>
        </div>

        <div
          v-for="field in request.fields"
          :key="field.id"
          class="space-y-2"
        >
          <label class="bw-label">
            {{ field.label }}
            <span v-if="field.required !== false" class="text-destructive">*</span>
          </label>

          <template v-if="field.type === 'signature'">
            <div class="flex flex-wrap gap-2 text-sm">
              <button
                type="button"
                class="bw-btn bw-btn-sm"
                :class="signatureMode === 'canvas' ? 'bw-btn-primary' : 'bw-btn-outline'"
                @click="signatureMode = 'canvas'"
              >
                Draw signature
              </button>
              <button
                type="button"
                class="bw-btn bw-btn-sm"
                :class="signatureMode === 'typed' ? 'bw-btn-primary' : 'bw-btn-outline'"
                @click="signatureMode = 'typed'"
              >
                Type signature
              </button>
            </div>
            <SignatureCanvas
              v-if="signatureMode === 'canvas'"
              v-model="fieldValues[field.id]"
            />
            <input
              v-else
              v-model="fieldValues[field.id]"
              type="text"
              class="bw-input font-serif text-lg italic"
              placeholder="Type your full signature"
            />
          </template>

          <input
            v-else-if="field.type === 'date'"
            v-model="fieldValues[field.id]"
            type="date"
            class="bw-input"
            :required="field.required !== false"
          />

          <input
            v-else
            v-model="fieldValues[field.id]"
            type="text"
            class="bw-input"
            :required="field.required !== false"
          />
        </div>

        <p class="text-xs text-muted-foreground">
          By signing, you agree this electronic signature is legally binding. Your IP address and
          timestamp will be recorded in the audit trail.
        </p>

        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="bw-btn bw-btn-primary"
            :disabled="isSubmitting"
            @click="submitSignature"
          >
            {{ isSubmitting ? 'Submitting…' : 'Sign document' }}
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline"
            @click="showDeclineForm = !showDeclineForm"
          >
            Decline to sign
          </button>
        </div>

        <form v-if="showDeclineForm" class="space-y-3 border-t border-border pt-4" @submit.prevent="declineSignature">
          <label class="bw-label" for="decline-reason">Reason (optional)</label>
          <textarea
            id="decline-reason"
            v-model="declineReason"
            class="bw-input min-h-[72px]"
            placeholder="Let your legal team know why you are declining."
          />
          <button type="submit" class="bw-btn bw-btn-outline" :disabled="isDeclining">
            {{ isDeclining ? 'Submitting…' : 'Confirm decline' }}
          </button>
        </form>
      </section>

      <section v-else-if="request.status === 'signed'" class="bw-card p-5 text-sm">
        <p class="font-medium text-foreground">This document was signed successfully.</p>
        <p v-if="request.signed_at" class="mt-1 text-muted-foreground">
          Signed on {{ new Date(request.signed_at).toLocaleString() }}
        </p>
      </section>

      <section v-else-if="request.status === 'declined'" class="bw-card p-5 text-sm">
        <p class="font-medium text-foreground">You declined to sign this document.</p>
      </section>

      <button type="button" class="bw-btn bw-btn-outline" @click="goToCase">
        Back to case
      </button>
    </template>

    <EmptyState
      v-else
      :icon="PhSignature"
      title="Signature request not found"
      :message="error || 'This request may have expired or you do not have access.'"
    />
  </div>
</template>
