<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PhFile, PhSignature, PhUploadSimple } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import MessageThreadPanel from '@/components/messages/MessageThreadPanel.vue'
import { portalCasesApi, portalDocumentsApi, portalSignaturesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import type { LegalMatter, PortalDocument, SignatureRequest } from '@/types'

const route = useRoute()
const router = useRouter()
const caseId = Number(route.params.id)

const matter = ref<LegalMatter | null>(null)
const sharedDocuments = ref<PortalDocument[]>([])
const pendingDocuments = ref<PortalDocument[]>([])
const signatureRequests = ref<SignatureRequest[]>([])
const selectedFile = ref<File | null>(null)
const uploadName = ref('')
const isLoading = ref(true)
const isUploading = ref(false)
const downloadingId = ref<number | null>(null)
const error = ref<string | null>(null)
const uploadSuccess = ref<string | null>(null)
const showMessages = ref(route.query.tab === 'messages')

const initialThreadId = computed(() => {
  const raw = route.query.thread
  const id = typeof raw === 'string' ? Number(raw) : null
  return id && !Number.isNaN(id) ? id : null
})

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [caseData, shared, pending, signatures] = await Promise.all([
      portalCasesApi.get(caseId),
      portalDocumentsApi.list(caseId, 'shared'),
      portalDocumentsApi.list(caseId, 'pending'),
      portalSignaturesApi.list(caseId, 'pending'),
    ])
    matter.value = caseData
    sharedDocuments.value = shared
    pendingDocuments.value = pending
    signatureRequests.value = signatures
  } catch (err) {
    error.value = formatApiError(err, 'This case is not available.')
  } finally {
    isLoading.value = false
  }
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

async function uploadDocument() {
  if (!selectedFile.value) return
  isUploading.value = true
  error.value = null
  uploadSuccess.value = null
  try {
    const uploaded = await portalDocumentsApi.upload(caseId, {
      file: selectedFile.value,
      name: uploadName.value || undefined,
    })
    pendingDocuments.value = [uploaded, ...pendingDocuments.value]
    selectedFile.value = null
    uploadName.value = ''
    uploadSuccess.value = 'Your document was submitted and is pending review by your legal team.'
  } catch (err) {
    error.value = formatApiError(err, 'We could not upload this document.')
  } finally {
    isUploading.value = false
  }
}

async function downloadDocument(doc: PortalDocument) {
  downloadingId.value = doc.id
  try {
    await portalDocumentsApi.download(doc.id, doc.original_filename || doc.name)
  } catch (err) {
    error.value = formatApiError(err, 'Download failed.')
  } finally {
    downloadingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div v-if="isLoading">
      <Skeleton class="mb-4 h-8 w-64 rounded-md" />
      <Skeleton class="h-40 rounded-lg" />
    </div>
    <template v-else-if="matter">
      <PageHeader
        :title="matter.title"
        :subtitle="matter.matter_number || 'Case details'"
      />

      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <p v-if="uploadSuccess" class="text-sm text-primary">{{ uploadSuccess }}</p>

      <section class="bw-card p-5 space-y-4">
        <div class="flex flex-wrap items-center gap-3">
          <StatusBadge :status="matter.status" />
          <span v-if="matter.lead_lawyer" class="text-sm text-muted-foreground">
            Lead lawyer: {{ matter.lead_lawyer.name }}
          </span>
        </div>
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-muted-foreground">Practice area</dt>
            <dd class="font-medium">{{ matter.practice_area || '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Case type</dt>
            <dd class="font-medium">{{ matter.case_type || '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Jurisdiction</dt>
            <dd class="font-medium">{{ matter.court_jurisdiction || '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Opened</dt>
            <dd class="font-medium">{{ formatDate(matter.opened_at) }}</dd>
          </div>
        </dl>
        <p v-if="matter.description" class="text-sm leading-relaxed text-foreground">
          {{ matter.description }}
        </p>
      </section>

      <section class="bw-card">
        <div class="border-b border-border px-5 py-4">
          <h2 class="font-semibold">Upload a document</h2>
          <p class="text-sm text-muted-foreground">
            Submit files for your legal team to review. Shared documents appear after approval.
          </p>
        </div>
        <form class="space-y-4 p-5" @submit.prevent="uploadDocument">
          <div>
            <label class="bw-label" for="portal-doc-file">File</label>
            <input
              id="portal-doc-file"
              required
              type="file"
              class="bw-input"
              @change="handleFileChange"
            />
          </div>
          <div>
            <label class="bw-label" for="portal-doc-name">Display name</label>
            <input
              id="portal-doc-name"
              v-model="uploadName"
              class="bw-input"
              placeholder="Leave blank to use the filename"
            />
          </div>
          <button
            type="submit"
            class="bw-btn bw-btn-primary"
            :disabled="isUploading || !selectedFile"
          >
            <PhUploadSimple class="h-4 w-4" />
            {{ isUploading ? 'Uploading…' : 'Submit for review' }}
          </button>
        </form>
      </section>

      <section v-if="signatureRequests.length" class="bw-card">
        <div class="border-b border-border px-5 py-4">
          <h2 class="font-semibold">Documents to sign</h2>
          <p class="text-sm text-muted-foreground">
            Review and sign documents sent by your legal team.
          </p>
        </div>
        <ul class="divide-y divide-border">
          <li
            v-for="sig in signatureRequests"
            :key="sig.id"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
          >
            <div class="flex items-center gap-3">
              <PhSignature class="h-5 w-5 text-primary-700" weight="fill" />
              <div>
                <p class="font-medium">{{ sig.document?.name ?? 'Document' }}</p>
                <p v-if="sig.message" class="text-sm text-muted-foreground">{{ sig.message }}</p>
              </div>
            </div>
            <button
              type="button"
              class="bw-btn bw-btn-primary bw-btn-sm"
              @click="router.push(`/portal/sign/${sig.id}`)"
            >
              Review &amp; sign
            </button>
          </li>
        </ul>
      </section>

      <section v-if="pendingDocuments.length" class="bw-card">
        <div class="border-b border-border px-5 py-4">
          <h2 class="font-semibold">Pending review</h2>
          <p class="text-sm text-muted-foreground">Your uploads awaiting firm review.</p>
        </div>
        <ul class="divide-y divide-border">
          <li
            v-for="doc in pendingDocuments"
            :key="doc.id"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
          >
            <div class="flex items-center gap-3">
              <PhFile class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="font-medium">{{ doc.name }}</p>
                <p class="text-sm text-muted-foreground">
                  Submitted {{ formatDate(doc.created_at) }} · Pending review
                </p>
              </div>
            </div>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :disabled="downloadingId === doc.id"
              @click="downloadDocument(doc)"
            >
              {{ downloadingId === doc.id ? 'Downloading…' : 'Download' }}
            </button>
          </li>
        </ul>
      </section>

      <section class="bw-card">
        <div class="border-b border-border px-5 py-4">
          <h2 class="font-semibold">Shared documents</h2>
        </div>
        <ul v-if="sharedDocuments.length" class="divide-y divide-border">
          <li
            v-for="doc in sharedDocuments"
            :key="doc.id"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
          >
            <div class="flex items-center gap-3">
              <PhFile class="h-5 w-5 text-muted-foreground" />
              <div>
                <p class="font-medium">{{ doc.name }}</p>
                <p class="text-sm text-muted-foreground">{{ formatDate(doc.created_at) }}</p>
              </div>
            </div>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :disabled="downloadingId === doc.id"
              @click="downloadDocument(doc)"
            >
              {{ downloadingId === doc.id ? 'Downloading…' : 'Download' }}
            </button>
          </li>
        </ul>
        <EmptyState
          v-else
          :icon="PhFile"
          title="No shared documents"
          message="Documents your firm shares for this case will appear here."
          class="p-6"
        />
      </section>

      <section class="bw-card">
        <div class="flex items-center justify-between border-b border-border px-5 py-4">
          <h2 class="font-semibold">Messages</h2>
          <button
            type="button"
            class="text-sm text-primary hover:underline"
            @click="showMessages = !showMessages"
          >
            {{ showMessages ? 'Hide' : 'Show' }}
          </button>
        </div>
        <div v-if="showMessages" class="p-4">
          <MessageThreadPanel
            mode="portal"
            :case-id="caseId"
            :initial-thread-id="initialThreadId"
            compact
          />
        </div>
        <p v-else class="px-5 py-4 text-sm text-muted-foreground">
          Open messages to communicate with your legal team about this case.
        </p>
      </section>
    </template>
    <EmptyState
      v-else
      :icon="PhFile"
      title="Case not found"
      :message="error || 'This case may not exist or you do not have access.'"
    />
  </div>
</template>
