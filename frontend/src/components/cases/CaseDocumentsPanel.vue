<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhFile, PhFileText, PhFolder, PhPlus, PhSparkle } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import ClauseLibraryPanel from '@/components/documents/ClauseLibraryPanel.vue'
import MergeFieldPicker from '@/components/documents/MergeFieldPicker.vue'
import RichTextEditor from '@/components/editor/RichTextEditor.vue'
import OnlyOfficeEditor from '@/components/editor/OnlyOfficeEditor.vue'
import ApprovalWorkflowPanel from '@/components/approvals/ApprovalWorkflowPanel.vue'
import SignatureSendPanel from '@/components/signatures/SignatureSendPanel.vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import {
  DOCUMENT_TYPES,
  documentTypeBadge,
  documentTypeLabel,
} from '@/lib/enums'
import { humanize } from '@/lib/status'
import { aiApi, caseDocumentsApi, documentFoldersApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { useAuthStore } from '@/stores/auth'
import type {
  AiContractReviewResponse,
  AiGovernanceSettings,
  AiLetterPackResponse,
  CaseDocument,
  DocumentFolder,
  DocumentVersion,
  DocumentVersionCompare,
} from '@/types'

const auth = useAuthStore()

const props = defineProps<{
  caseId: number
}>()

const documents = ref<CaseDocument[]>([])
const folders = ref<DocumentFolder[]>([])
const selectedFolderId = ref<number | 'none' | 'all'>('all')
const newFolderName = ref('')
const showFolderModal = ref(false)
const showUploadModal = ref(false)
const isCreatingFolder = ref(false)
const isFolderBusy = ref(false)
const templates = ref<CaseDocument[]>([])
const selectedFile = ref<File | null>(null)
const name = ref('')
const documentType = ref('pleading')
const typeFilter = ref<string>('')
const selectedTemplateId = ref<number | null>(null)
const editingDoc = ref<CaseDocument | null>(null)
const editorHtml = ref('')
const templateName = ref('')
const templateHtml = ref('<p>Dear {{client.name}},</p><p>Re: {{case.title}} ({{case.matter_number}})</p><p>{{today}}</p>')
const isLoading = ref(true)
const isUploading = ref(false)
const isGenerating = ref(false)
const isSavingDraft = ref(false)
const isSavingTemplate = ref(false)
const isAiDrafting = ref(false)
const isAiSummarizing = ref(false)
const isUpdatingAiReview = ref(false)
const aiSettings = ref<AiGovernanceSettings | null>(null)
const aiSummary = ref<string | null>(null)
const pendingAiDraft = ref<{ content: string; governanceLogId?: number | null } | null>(null)
const downloadingId = ref<number | null>(null)
const changeSummary = ref('')
const documentVersions = ref<DocumentVersion[]>([])
const compareFromVersion = ref<number | null>(null)
const compareToVersion = ref<number | null>(null)
const versionCompare = ref<DocumentVersionCompare | null>(null)
const isLoadingVersions = ref(false)
const isComparingVersions = ref(false)
const onlyOfficeDoc = ref<CaseDocument | null>(null)
const contractReviewDoc = ref<CaseDocument | null>(null)
const contractReviewResult = ref<AiContractReviewResponse | null>(null)
const isContractReviewing = ref(false)
const isGeneratingLetterPack = ref(false)
const letterPackResult = ref<AiLetterPackResponse | null>(null)
const error = ref<string | null>(null)

const WORD_MIME_TYPES = [
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/msword',
]

const PDF_MIME_TYPES = ['application/pdf']

function isWordUpload(doc: CaseDocument): boolean {
  return Boolean(
    doc.mime_type &&
      WORD_MIME_TYPES.includes(doc.mime_type) &&
      !doc.content_html,
  )
}

function isPdfUpload(doc: CaseDocument): boolean {
  return Boolean(doc.mime_type && PDF_MIME_TYPES.includes(doc.mime_type) && !doc.content_html)
}

function isContractReviewable(doc: CaseDocument): boolean {
  return isWordUpload(doc) || isPdfUpload(doc)
}

function versionSourceLabel(source?: string | null) {
  if (source === 'ai') return 'AI'
  if (source === 'system') return 'System'
  return 'Human'
}

function versionSourceBadgeClass(source?: string | null) {
  if (source === 'ai') return 'bw-badge-info'
  if (source === 'system') return 'bw-badge-neutral'
  return 'bw-badge-success'
}

function openOnlyOffice(doc: CaseDocument) {
  editingDoc.value = null
  pendingAiDraft.value = null
  onlyOfficeDoc.value = doc
}

function closeOnlyOffice() {
  onlyOfficeDoc.value = null
  void load()
}

const lawyerRoles = ['Lawyer', 'Partner', 'Firm Admin']
const canApproveAi = computed(() =>
  lawyerRoles.some((role) => auth.user?.roles?.includes(role)),
)

const editingAiStatus = computed(() => editingDoc.value?.ai_review_status ?? null)
const editingIsAi = computed(() => Boolean(editingDoc.value?.ai_generated))
const canFinalizeAi = computed(() => editingAiStatus.value === 'approved')
const showAiWorkflow = computed(() => editingIsAi.value || pendingAiDraft.value !== null)

const drafts = computed(() =>
  documents.value.filter((doc) => doc.category === 'template_draft' || doc.content_html),
)

const pendingPortalUploads = computed(() =>
  documents.value.filter((doc) => doc.portal_pending_review),
)

const reviewedDocuments = computed(() =>
  documents.value.filter((doc) => !doc.portal_pending_review),
)

function resolveDocumentType(doc: CaseDocument): string {
  if (doc.document_type && doc.document_type !== 'case_document') {
    return doc.document_type
  }
  return doc.category || doc.document_type || 'pleading'
}

const visibleDocuments = computed(() => {
  let base = reviewedDocuments.value
  if (typeFilter.value) {
    base = base.filter((doc) => resolveDocumentType(doc) === typeFilter.value)
  }
  if (selectedFolderId.value === 'all') return base
  if (selectedFolderId.value === 'none') {
    return base.filter((doc) => !doc.document_folder_id)
  }
  return base.filter((doc) => doc.document_folder_id === selectedFolderId.value)
})

function flattenFolders(list: DocumentFolder[]): DocumentFolder[] {
  return list.flatMap((folder) => [folder, ...(folder.children ? flattenFolders(folder.children) : [])])
}

const folderOptions = computed(() => flattenFolders(folders.value))

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [docs, tmpl, folderList, settings] = await Promise.all([
      caseDocumentsApi.list(props.caseId),
      caseDocumentsApi.listTemplates(),
      documentFoldersApi.list(props.caseId).catch(() => []),
      aiApi.governanceSettings().catch(() => null),
    ])
    documents.value = docs
    folders.value = folderList
    templates.value = tmpl
    aiSettings.value = settings
    if (!selectedTemplateId.value && tmpl[0]) {
      selectedTemplateId.value = tmpl[0].id
    }
  } catch (err) {
    error.value = formatApiError(err, 'Documents are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function createFolder() {
  const trimmed = newFolderName.value.trim()
  if (!trimmed) return
  isCreatingFolder.value = true
  error.value = null
  try {
    await documentFoldersApi.create({ legal_matter_id: props.caseId, name: trimmed })
    newFolderName.value = ''
    showFolderModal.value = false
    folders.value = await documentFoldersApi.list(props.caseId)
  } catch (err) {
    error.value = formatApiError(err, 'We could not create this folder.')
  } finally {
    isCreatingFolder.value = false
  }
}

function openFolderModal() {
  newFolderName.value = ''
  showFolderModal.value = true
}

function openUploadModal() {
  selectedFile.value = null
  name.value = ''
  showUploadModal.value = true
}

function closeUploadModal() {
  showUploadModal.value = false
  selectedFile.value = null
  name.value = ''
}

async function assignDocumentFolder(doc: CaseDocument, folderId: string) {
  isFolderBusy.value = true
  error.value = null
  try {
    const updated = await caseDocumentsApi.assignFolder(
      doc.id,
      folderId ? Number(folderId) : null,
    )
    documents.value = documents.value.map((row) => (row.id === doc.id ? updated : row))
  } catch (err) {
    error.value = formatApiError(err, 'We could not assign this folder.')
  } finally {
    isFolderBusy.value = false
  }
}

async function toggleCheckout(doc: CaseDocument) {
  isFolderBusy.value = true
  error.value = null
  try {
    const updated = doc.is_checked_out
      ? await caseDocumentsApi.checkin(doc.id)
      : await caseDocumentsApi.checkout(doc.id)
    documents.value = documents.value.map((row) => (row.id === doc.id ? updated : row))
  } catch (err) {
    error.value = formatApiError(err, 'Checkout failed.')
  } finally {
    isFolderBusy.value = false
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
  try {
    const uploaded = await caseDocumentsApi.upload(props.caseId, {
      file: selectedFile.value,
      name: name.value,
      document_type: documentType.value,
    })
    documents.value = [uploaded, ...documents.value]
    selectedFile.value = null
    name.value = ''
    showUploadModal.value = false
  } catch (err) {
    error.value = formatApiError(err, 'We could not upload this document.')
  } finally {
    isUploading.value = false
  }
}

function insertMergeField(token: string) {
  templateHtml.value += token
}

function insertClause(html: string) {
  if (editingDoc.value || pendingAiDraft.value) {
    editorHtml.value += html
    return
  }
  templateHtml.value += html
}

async function runContractReview(doc: CaseDocument) {
  isContractReviewing.value = true
  error.value = null
  contractReviewDoc.value = doc
  contractReviewResult.value = null
  try {
    contractReviewResult.value = await aiApi.contractReview(doc.id)
  } catch (err) {
    error.value = formatApiError(err, 'Contract review is not available.')
    contractReviewDoc.value = null
  } finally {
    isContractReviewing.value = false
  }
}

async function runLetterPack() {
  isGeneratingLetterPack.value = true
  error.value = null
  letterPackResult.value = null
  try {
    letterPackResult.value = await aiApi.generateLetterPack({
      legal_matter_id: props.caseId,
    })
  } catch (err) {
    error.value = formatApiError(err, 'Letter pack generation is not available.')
  } finally {
    isGeneratingLetterPack.value = false
  }
}

function applyLetterDraft(contentHtml: string) {
  editorHtml.value = contentHtml
  pendingAiDraft.value = { content: contentHtml }
  editingDoc.value = null
  onlyOfficeDoc.value = null
}

async function uploadTemplate() {
  if (!templateName.value.trim()) return
  isSavingTemplate.value = true
  error.value = null
  try {
    const created = await caseDocumentsApi.uploadTemplate({
      name: templateName.value.trim(),
      content_html: templateHtml.value,
      category: 'template',
    })
    templates.value = [created, ...templates.value]
    selectedTemplateId.value = created.id
    templateName.value = ''
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this template.')
  } finally {
    isSavingTemplate.value = false
  }
}

async function generateDraft() {
  if (!selectedTemplateId.value) return
  isGenerating.value = true
  error.value = null
  try {
    const draft = await caseDocumentsApi.generateDraft(
      props.caseId,
      selectedTemplateId.value,
    )
    documents.value = [draft, ...documents.value]
    openEditor(draft)
  } catch (err) {
    error.value = formatApiError(err, 'We could not generate this draft.')
  } finally {
    isGenerating.value = false
  }
}

function openEditor(doc: CaseDocument) {
  onlyOfficeDoc.value = null
  editingDoc.value = doc
  editorHtml.value = doc.content_html || '<p></p>'
  pendingAiDraft.value = null
  aiSummary.value = null
  changeSummary.value = ''
  versionCompare.value = null
  compareFromVersion.value = doc.version ? Number(doc.version) : null
  compareToVersion.value = null
  void loadDocumentVersions(doc.id)
}

async function loadDocumentVersions(documentId: number) {
  isLoadingVersions.value = true
  try {
    documentVersions.value = await caseDocumentsApi.listVersions(documentId)
    if (documentVersions.value.length >= 2) {
      compareFromVersion.value = documentVersions.value[1]?.version_number ?? null
      compareToVersion.value = documentVersions.value[0]?.version_number ?? null
    } else if (documentVersions.value.length === 1) {
      compareFromVersion.value = documentVersions.value[0]?.version_number ?? null
      compareToVersion.value = documentVersions.value[0]?.version_number ?? null
    }
  } catch {
    documentVersions.value = []
  } finally {
    isLoadingVersions.value = false
  }
}

async function runVersionCompare() {
  if (!editingDoc.value || compareFromVersion.value == null || compareToVersion.value == null) return
  isComparingVersions.value = true
  error.value = null
  try {
    versionCompare.value = await caseDocumentsApi.compareVersions(
      editingDoc.value.id,
      compareFromVersion.value,
      compareToVersion.value,
    )
  } catch (err) {
    error.value = formatApiError(err, 'We could not compare these versions.')
  } finally {
    isComparingVersions.value = false
  }
}

async function runAiDraftAssist() {
  isAiDrafting.value = true
  error.value = null
  aiSummary.value = null
  try {
    const response = await aiApi.draftAssist({
      legal_matter_id: props.caseId,
      template_id: selectedTemplateId.value,
    })
    editorHtml.value = response.content
    pendingAiDraft.value = {
      content: response.content,
      governanceLogId: response.governance_log_id,
    }
    editingDoc.value = null
  } catch (err) {
    error.value = formatApiError(err, 'AI draft assistance is not available.')
  } finally {
    isAiDrafting.value = false
  }
}

async function saveAiDraftToCase() {
  if (!pendingAiDraft.value && !editingDoc.value?.ai_generated) return
  isSavingDraft.value = true
  error.value = null
  try {
    if (editingDoc.value?.ai_generated) {
      const updated = await caseDocumentsApi.updateContent(
        editingDoc.value.id,
        editorHtml.value,
        changeSummary.value || 'AI draft edited',
      )
      documents.value = documents.value.map((doc) => (doc.id === updated.id ? updated : doc))
      editingDoc.value = updated
      await loadDocumentVersions(updated.id)
    } else if (pendingAiDraft.value) {
      const created = await caseDocumentsApi.saveAiDraft({
        caseId: props.caseId,
        contentHtml: editorHtml.value,
        templateId: selectedTemplateId.value,
        governanceLogId: pendingAiDraft.value.governanceLogId,
      })
      documents.value = [created, ...documents.value]
      openEditor(created)
      pendingAiDraft.value = null
    }
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this AI draft.')
  } finally {
    isSavingDraft.value = false
  }
}

async function summarizeEditingDocument() {
  if (!editingDoc.value) return
  isAiSummarizing.value = true
  error.value = null
  try {
    const response = await aiApi.summarizeDocument(editingDoc.value.id)
    aiSummary.value = response.content
  } catch (err) {
    error.value = formatApiError(err, 'We could not summarize this document.')
  } finally {
    isAiSummarizing.value = false
  }
}

async function transitionAiReview(status: string) {
  if (!editingDoc.value?.ai_generated) return
  isUpdatingAiReview.value = true
  error.value = null
  try {
    const updated = await caseDocumentsApi.updateAiReview(editingDoc.value.id, status)
    documents.value = documents.value.map((doc) => (doc.id === updated.id ? updated : doc))
    editingDoc.value = updated
  } catch (err) {
    error.value = formatApiError(err, 'We could not update the AI review status.')
  } finally {
    isUpdatingAiReview.value = false
  }
}

function aiStatusLabel(status?: string | null) {
  if (!status) return 'AI draft'
  return humanize(status)
}

async function saveDraftContent() {
  if (!editingDoc.value) return
  isSavingDraft.value = true
  error.value = null
  try {
    const updated = await caseDocumentsApi.updateContent(
      editingDoc.value.id,
      editorHtml.value,
      changeSummary.value || undefined,
    )
    documents.value = documents.value.map((doc) => (doc.id === updated.id ? updated : doc))
    editingDoc.value = updated
    changeSummary.value = ''
    await loadDocumentVersions(updated.id)
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this draft.')
  } finally {
    isSavingDraft.value = false
  }
}

async function downloadDocument(doc: CaseDocument) {
  downloadingId.value = doc.id
  error.value = null
  try {
    await caseDocumentsApi.download(doc.id, doc.original_filename || doc.name)
  } catch (err) {
    error.value = formatApiError(err, 'We could not download this document.')
  } finally {
    downloadingId.value = null
  }
}

async function exportDocument(doc: CaseDocument) {
  downloadingId.value = doc.id
  error.value = null
  try {
    await caseDocumentsApi.exportPdf(doc.id, doc.name)
  } catch (err) {
    error.value = formatApiError(err, 'We could not export this document.')
  } finally {
    downloadingId.value = null
  }
}

function formatDate(iso?: string) {
  if (!iso) return 'Recently'
  return new Date(iso).toLocaleString()
}

function formatSize(size?: number | null) {
  if (!size) return 'Size pending'
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

async function toggleClientVisible(doc: CaseDocument) {
  error.value = null
  try {
    const updated = await caseDocumentsApi.updateVisibility(doc.id, !doc.client_visible)
    documents.value = documents.value.map((item) => (item.id === updated.id ? updated : item))
  } catch (err) {
    error.value = formatApiError(err, 'We could not update document visibility.')
  }
}

async function reviewPortalUpload(doc: CaseDocument, shareWithClient: boolean) {
  error.value = null
  try {
    const updated = await caseDocumentsApi.updateVisibility(doc.id, shareWithClient)
    documents.value = documents.value.map((item) => (item.id === updated.id ? updated : item))
  } catch (err) {
    error.value = formatApiError(err, 'We could not review this client upload.')
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <section v-if="pendingPortalUploads.length" class="bw-card">
      <div class="bw-card-header">
        <div>
          <h2 class="font-semibold text-foreground">Client uploads pending review</h2>
          <p class="text-sm text-muted-foreground">
            Approve to share with the client portal, or keep internal after review.
          </p>
        </div>
      </div>
      <div class="divide-y divide-border">
        <article
          v-for="doc in pendingPortalUploads"
          :key="`pending-${doc.id}`"
          class="flex flex-wrap items-center justify-between gap-4 px-6 py-4"
        >
          <div class="flex min-w-0 items-start gap-3">
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-800"
            >
              <PhFile class="h-4 w-4" weight="fill" />
            </span>
            <div class="min-w-0">
              <h3 class="truncate font-medium text-foreground">{{ doc.name }}</h3>
              <p class="text-sm text-muted-foreground">
                Client upload · {{ formatSize(doc.size) }} · {{ formatDate(doc.created_at) }}
              </p>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="bw-btn bw-btn-primary bw-btn-sm"
              @click="reviewPortalUpload(doc, true)"
            >
              Approve & share
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              @click="reviewPortalUpload(doc, false)"
            >
              Keep internal
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :disabled="downloadingId === doc.id"
              @click="downloadDocument(doc)"
            >
              Download
            </button>
          </div>
        </article>
      </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[220px_minmax(0,1fr)_360px]">
      <aside class="bw-card p-4 space-y-3">
        <div class="flex items-center gap-2">
          <PhFolder class="h-4 w-4 text-muted-foreground" />
          <h2 class="text-sm font-semibold text-foreground">Folders</h2>
        </div>
        <nav class="space-y-1 text-sm">
          <button
            type="button"
            class="w-full rounded-md px-2 py-1.5 text-left hover:bg-primary-50"
            :class="selectedFolderId === 'all' ? 'bg-primary-50 font-medium text-foreground' : 'text-muted-foreground'"
            @click="selectedFolderId = 'all'"
          >
            All documents
          </button>
          <button
            type="button"
            class="w-full rounded-md px-2 py-1.5 text-left hover:bg-primary-50"
            :class="selectedFolderId === 'none' ? 'bg-primary-50 font-medium text-foreground' : 'text-muted-foreground'"
            @click="selectedFolderId = 'none'"
          >
            Unfiled
          </button>
          <button
            v-for="folder in folderOptions"
            :key="folder.id"
            type="button"
            class="w-full rounded-md px-2 py-1.5 text-left hover:bg-primary-50"
            :class="selectedFolderId === folder.id ? 'bg-primary-50 font-medium text-foreground' : 'text-muted-foreground'"
            @click="selectedFolderId = folder.id"
          >
            {{ folder.name }}
            <span v-if="folder.documents_count != null" class="text-xs tabular-nums">
              ({{ folder.documents_count }})
            </span>
          </button>
        </nav>
        <div class="border-t border-border px-2 pt-3">
          <button type="button" class="bw-btn bw-btn-accent bw-btn-sm w-full" @click="openFolderModal">
            <PhPlus class="h-4 w-4" weight="bold" />
            New folder
          </button>
        </div>
      </aside>

      <section class="space-y-3">
        <div class="bw-card">
          <div class="bw-card-header flex flex-wrap items-end justify-between gap-4">
            <div>
              <h2 class="font-semibold text-foreground">Case documents</h2>
              <p class="text-sm text-muted-foreground">
                Upload, edit drafts, and download files for this matter.
              </p>
            </div>
            <div class="min-w-[10rem]">
              <label class="bw-label text-xs" for="doc-type-filter">Filter by type</label>
              <select id="doc-type-filter" v-model="typeFilter" class="bw-select bw-btn-sm mt-1 w-full">
                <option value="">All types</option>
                <option v-for="type in DOCUMENT_TYPES" :key="type" :value="type">
                  {{ documentTypeLabel(type) }}
                </option>
              </select>
            </div>
            <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="openUploadModal">
              <PhPlus class="h-4 w-4" weight="bold" />
              Upload
            </button>
          </div>

          <Skeleton v-if="isLoading" variant="panel" :rows="4" />
          <p v-else-if="error" class="p-6 text-sm text-destructive" role="alert">
            {{ error }}
          </p>
          <div v-else-if="visibleDocuments.length" class="divide-y divide-border">
            <article
              v-for="doc in visibleDocuments"
              :key="doc.id"
              class="flex flex-wrap items-center justify-between gap-4 px-6 py-4"
            >
              <div class="flex min-w-0 items-start gap-3">
                <span
                  class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700"
                >
                  <PhFile class="h-4 w-4" weight="fill" />
                </span>
                <div class="min-w-0">
                  <h3 class="truncate font-medium text-foreground">{{ doc.name }}</h3>
                  <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span
                      class="bw-badge"
                      :class="documentTypeBadge(resolveDocumentType(doc))"
                    >
                      {{ documentTypeLabel(resolveDocumentType(doc)) }}
                    </span>
                    <span>{{ formatSize(doc.size) }} · {{ formatDate(doc.created_at) }}</span>
                  </p>
                  <p v-if="doc.version" class="text-xs text-muted-foreground">
                    Version {{ doc.version }}
                    <span v-if="doc.parent_template?.name">
                      · from {{ doc.parent_template.name }}
                    </span>
                  </p>
                  <p v-if="doc.ai_generated" class="mt-1 text-xs text-muted-foreground">
                    <span class="bw-badge bw-badge-info mr-1">{{ aiSettings?.label ?? 'AI-generated' }}</span>
                    <span class="bw-badge bw-badge-warning">{{ aiStatusLabel(doc.ai_review_status) }}</span>
                  </p>
                  <p v-if="doc.is_checked_out" class="mt-1 text-xs text-amber-700">
                    Checked out
                    <span v-if="doc.checked_out_by"> by {{ doc.checked_out_by.name }}</span>
                  </p>
                </div>
              </div>
              <div class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-[12rem]">
                <select
                  class="bw-select bw-btn-sm w-full text-sm"
                  :value="doc.document_folder_id ?? ''"
                  :disabled="isFolderBusy"
                  @change="assignDocumentFolder(doc, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">Unfiled</option>
                  <option v-for="folder in folderOptions" :key="folder.id" :value="folder.id">
                    {{ folder.name }}
                  </option>
                </select>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-if="!doc.uploaded_by_client"
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  :disabled="isFolderBusy"
                  @click="toggleCheckout(doc)"
                >
                  {{ doc.is_checked_out ? 'Check in' : 'Check out' }}
                </button>
                <button
                  v-if="isWordUpload(doc)"
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  @click="openOnlyOffice(doc)"
                >
                  Edit in Word
                </button>
                <button
                  v-if="isContractReviewable(doc)"
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  :disabled="isContractReviewing && contractReviewDoc?.id === doc.id"
                  @click="runContractReview(doc)"
                >
                  {{ isContractReviewing && contractReviewDoc?.id === doc.id ? 'Reviewing…' : 'AI contract review' }}
                </button>
                <button
                  v-if="doc.content_html || doc.category === 'template_draft'"
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  @click="openEditor(doc)"
                >
                  Edit
                </button>
                <button
                  v-if="doc.content_html"
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  :disabled="downloadingId === doc.id"
                  @click="exportDocument(doc)"
                >
                  Export
                </button>
                <button
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  :disabled="downloadingId === doc.id"
                  @click="downloadDocument(doc)"
                >
                  Download
                </button>
                <SignatureSendPanel
                  v-if="!doc.uploaded_by_client"
                  :document-id="doc.id"
                  :case-id="caseId"
                  :document-name="doc.name"
                  class="w-full basis-full"
                  @sent="load"
                />
                <label
                  v-if="!doc.uploaded_by_client"
                  class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                >
                  <input
                    type="checkbox"
                    class="rounded border-border"
                    :checked="doc.client_visible"
                    @change="toggleClientVisible(doc)"
                  />
                  Client visible
                </label>
                <span
                  v-else-if="doc.client_visible"
                  class="text-xs text-primary"
                >
                  Shared with client
                </span>
                <span
                  v-else
                  class="text-xs text-muted-foreground"
                >
                  Internal only
                </span>
              </div>
            </article>
          </div>
          <EmptyState
            v-else
            :icon="PhFile"
            title="No documents yet"
            message="Upload the first file or generate a draft from a template."
          />
        </div>
      </section>

      <div class="space-y-6">
        <section class="bw-card h-fit space-y-4 p-6">
          <div>
            <h2 class="font-semibold text-foreground">Generate from template</h2>
            <p class="text-sm text-muted-foreground">
              Merge case and client fields into a prefilled draft.
            </p>
          </div>
          <select v-model="selectedTemplateId" class="bw-select">
            <option :value="null" disabled>Choose template</option>
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
          <button
            type="button"
            class="bw-btn bw-btn-primary w-full"
            :disabled="isGenerating || !selectedTemplateId"
            @click="generateDraft"
          >
            {{ isGenerating ? 'Generating…' : 'Generate draft' }}
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline w-full"
            :disabled="isAiDrafting"
            @click="runAiDraftAssist"
          >
            <PhSparkle class="mr-1 inline h-4 w-4" weight="fill" />
            {{ isAiDrafting ? 'AI drafting…' : 'AI draft assist' }}
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline w-full"
            :disabled="isGeneratingLetterPack"
            @click="runLetterPack"
          >
            <PhSparkle class="mr-1 inline h-4 w-4" weight="fill" />
            {{ isGeneratingLetterPack ? 'Generating pack…' : 'AI letter pack' }}
          </button>
        </section>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="bw-card space-y-4 p-6">
        <div>
          <h2 class="font-semibold text-foreground">Firm template library</h2>
          <p class="text-sm text-muted-foreground">
            Templates use merge fields like <code v-pre>{{client.name}}</code> and
            <code v-pre>{{case.title}}</code>.
          </p>
        </div>
        <div v-if="templates.length" class="divide-y divide-border rounded-lg border border-border">
          <article
            v-for="template in templates"
            :key="template.id"
            class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
          >
            <div class="flex items-center gap-2">
              <PhFileText class="h-4 w-4 text-primary-700" />
              <span class="font-medium text-foreground">{{ template.name }}</span>
            </div>
            <span class="text-xs text-muted-foreground">v{{ template.version ?? 1 }}</span>
          </article>
        </div>
        <p v-else class="text-sm text-muted-foreground">No templates yet. Create one below.</p>
        <div>
          <label class="bw-label">Template name</label>
          <input v-model="templateName" class="bw-input" placeholder="Engagement letter" />
        </div>
        <MergeFieldPicker @insert="insertMergeField" />
        <ClauseLibraryPanel @insert="insertClause" />
        <RichTextEditor v-model="templateHtml" :enable-comments="false" />
        <button
          type="button"
          class="bw-btn bw-btn-outline w-full"
          :disabled="isSavingTemplate || !templateName.trim()"
          @click="uploadTemplate"
        >
          {{ isSavingTemplate ? 'Saving…' : 'Save template' }}
        </button>
      </section>

      <section v-if="onlyOfficeDoc" class="bw-card space-y-4 p-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-semibold text-foreground">Word editor (OnlyOffice)</h2>
            <p class="text-sm text-muted-foreground">{{ onlyOfficeDoc.name }}</p>
          </div>
          <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="closeOnlyOffice">
            Close
          </button>
        </div>
        <OnlyOfficeEditor
          :document-id="onlyOfficeDoc.id"
          @saved="load"
          @close="closeOnlyOffice"
        />
      </section>

      <section v-if="editingDoc || pendingAiDraft" class="bw-card space-y-4 p-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-semibold text-foreground">
              {{ pendingAiDraft ? 'AI draft preview' : 'Edit draft' }}
            </h2>
            <p v-if="editingDoc" class="text-sm text-muted-foreground">{{ editingDoc.name }}</p>
            <p v-else class="text-sm text-muted-foreground">
              Review AI output before saving to the case file.
            </p>
          </div>
          <button
            type="button"
            class="bw-btn bw-btn-ghost bw-btn-sm"
            @click="editingDoc = null; pendingAiDraft = null; aiSummary = null"
          >
            Close
          </button>
        </div>

        <AiDisclaimerBanner
          v-if="showAiWorkflow && aiSettings"
          :disclaimer="aiSettings.disclaimer"
          compact
        />
        <AiOutputBadges
          v-if="showAiWorkflow && aiSettings"
          :label="aiSettings.label"
          :requires-review="editingAiStatus !== 'finalized' && editingAiStatus !== 'approved'"
        />

        <ClauseLibraryPanel v-if="editingDoc || pendingAiDraft" @insert="insertClause" />
        <RichTextEditor v-model="editorHtml" :enable-comments="!pendingAiDraft" />

        <div v-if="editingDoc && !pendingAiDraft" class="space-y-3 rounded-lg border border-border p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="text-sm font-semibold text-foreground">Version history</h3>
              <p class="text-xs text-muted-foreground">
                Each save creates a snapshot you can compare side by side.
              </p>
            </div>
            <span v-if="editingDoc.version" class="bw-badge bw-badge-neutral">
              Current v{{ editingDoc.version }}
            </span>
          </div>

          <div>
            <label class="bw-label" for="change-summary">Change summary (optional)</label>
            <input
              id="change-summary"
              v-model="changeSummary"
              class="bw-input"
              placeholder="Describe what changed in this save"
            />
          </div>

          <Skeleton v-if="isLoadingVersions" variant="panel" :rows="2" />
          <ul v-else-if="documentVersions.length" class="divide-y divide-border rounded-lg border border-border">
            <li
              v-for="version in documentVersions"
              :key="version.id"
              class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
            >
              <div>
                <span class="font-medium text-foreground">Version {{ version.version_number }}</span>
                <span
                  class="ml-2 bw-badge bw-badge-sm"
                  :class="versionSourceBadgeClass(version.source)"
                >
                  {{ versionSourceLabel(version.source) }}
                </span>
                <span v-if="version.change_summary" class="ml-2 text-muted-foreground">
                  — {{ version.change_summary }}
                </span>
              </div>
              <span class="text-xs text-muted-foreground">
                {{ version.created_by?.name ?? 'Staff' }} · {{ formatDate(version.created_at) }}
              </span>
            </li>
          </ul>
          <p v-else class="text-xs text-muted-foreground">No saved versions yet.</p>

          <div
            v-if="documentVersions.length >= 1"
            class="grid gap-3 border-t border-border pt-3 sm:grid-cols-[1fr_1fr_auto]"
          >
            <div>
              <label class="bw-label">Compare from</label>
              <select v-model.number="compareFromVersion" class="bw-select">
                <option v-for="version in documentVersions" :key="`from-${version.id}`" :value="version.version_number">
                  v{{ version.version_number }}
                </option>
              </select>
            </div>
            <div>
              <label class="bw-label">Compare to</label>
              <select v-model.number="compareToVersion" class="bw-select">
                <option v-for="version in documentVersions" :key="`to-${version.id}`" :value="version.version_number">
                  v{{ version.version_number }}
                </option>
              </select>
            </div>
            <div class="flex items-end">
              <button
                type="button"
                class="bw-btn bw-btn-outline w-full sm:w-auto"
                :disabled="isComparingVersions || compareFromVersion == null || compareToVersion == null"
                @click="runVersionCompare"
              >
                {{ isComparingVersions ? 'Comparing…' : 'Compare' }}
              </button>
            </div>
          </div>

          <div
            v-if="versionCompare"
            class="grid gap-3 border-t border-border pt-3 lg:grid-cols-2"
          >
            <div class="rounded-lg border border-border bg-surface p-3">
              <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">
                Version {{ versionCompare.from.version_number }}
              </p>
              <div
                class="prose prose-sm max-w-none text-sm text-foreground"
                v-html="versionCompare.from.content_html"
              />
            </div>
            <div class="rounded-lg border border-border bg-surface p-3">
              <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">
                Version {{ versionCompare.to.version_number }}
              </p>
              <div
                class="prose prose-sm max-w-none text-sm text-foreground"
                v-html="versionCompare.to.content_html"
              />
            </div>
          </div>
        </div>

        <div v-if="aiSummary" class="rounded-lg border border-border bg-surface p-4 text-sm">
          <p class="mb-1 font-semibold text-foreground">AI summary</p>
          <p class="text-muted-foreground">{{ aiSummary }}</p>
        </div>

        <div v-if="editingIsAi" class="flex flex-wrap items-center gap-2 text-sm">
          <span class="text-muted-foreground">Review status:</span>
          <span class="bw-badge bw-badge-warning">{{ aiStatusLabel(editingAiStatus) }}</span>
          <span v-if="editingDoc?.ai_approved_by" class="text-xs text-muted-foreground">
            Approved by {{ editingDoc.ai_approved_by.name }}
          </span>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-if="pendingAiDraft"
            type="button"
            class="bw-btn bw-btn-primary"
            :disabled="isSavingDraft"
            @click="saveAiDraftToCase"
          >
            {{ isSavingDraft ? 'Saving…' : 'Save AI draft' }}
          </button>
          <button
            v-else-if="editingDoc"
            type="button"
            class="bw-btn bw-btn-primary"
            :disabled="isSavingDraft"
            @click="editingIsAi ? saveAiDraftToCase() : saveDraftContent()"
          >
            {{ isSavingDraft ? 'Saving…' : 'Save draft' }}
          </button>
          <button
            v-if="editingDoc && !pendingAiDraft"
            type="button"
            class="bw-btn bw-btn-outline"
            :disabled="isAiSummarizing"
            @click="summarizeEditingDocument"
          >
            {{ isAiSummarizing ? 'Summarizing…' : 'AI summarize' }}
          </button>
          <button
            v-if="editingDoc"
            type="button"
            class="bw-btn bw-btn-outline"
            :disabled="downloadingId === editingDoc.id"
            @click="exportDocument(editingDoc)"
          >
            Export PDF/HTML
          </button>
        </div>

        <ApprovalWorkflowPanel
          v-if="editingDoc && !pendingAiDraft"
          subject-type="legal_document"
          :subject-id="editingDoc.id"
          :requires-approval="editingDoc.requires_approval"
          @updated="load"
        />

        <SignatureSendPanel
          v-if="editingDoc && !pendingAiDraft && !editingDoc.uploaded_by_client"
          :document-id="editingDoc.id"
          :case-id="caseId"
          :document-name="editingDoc.name"
          @sent="load"
        />

        <div v-if="editingIsAi" class="flex flex-wrap gap-2 border-t border-border pt-4">
          <button
            v-if="['generated', 'edited'].includes(editingAiStatus ?? '')"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="isUpdatingAiReview"
            @click="transitionAiReview('under_review')"
          >
            Submit for review
          </button>
          <button
            v-if="editingAiStatus === 'under_review' && canApproveAi"
            type="button"
            class="bw-btn bw-btn-primary bw-btn-sm"
            :disabled="isUpdatingAiReview"
            @click="transitionAiReview('approved')"
          >
            Lawyer approve
          </button>
          <button
            v-if="canFinalizeAi"
            type="button"
            class="bw-btn bw-btn-primary bw-btn-sm"
            :disabled="isUpdatingAiReview"
            @click="transitionAiReview('finalized')"
          >
            Finalize
          </button>
          <p
            v-else-if="editingIsAi && editingAiStatus !== 'finalized'"
            class="text-xs text-muted-foreground"
          >
            Finalize is available after lawyer approval.
          </p>
        </div>
      </section>
      <section v-else-if="drafts.length" class="bw-card p-6 text-sm text-muted-foreground">
        Select a draft from the list and click Edit to open the rich text editor.
      </section>
    </div>

    <section v-if="contractReviewResult" class="bw-card space-y-4 p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold text-foreground">AI contract review</h2>
          <p v-if="contractReviewDoc" class="text-sm text-muted-foreground">
            {{ contractReviewDoc.name }}
          </p>
        </div>
        <button
          type="button"
          class="bw-btn bw-btn-ghost bw-btn-sm"
          @click="contractReviewResult = null; contractReviewDoc = null"
        >
          Close
        </button>
      </div>
      <AiDisclaimerBanner v-if="aiSettings" :disclaimer="contractReviewResult.disclaimer" compact />
      <p class="text-sm text-muted-foreground">{{ contractReviewResult.content }}</p>
      <ul v-if="contractReviewResult.issues?.length" class="divide-y divide-border rounded-lg border border-border">
        <li
          v-for="(issue, index) in contractReviewResult.issues"
          :key="`${issue.title}-${index}`"
          class="space-y-1 px-4 py-3 text-sm"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium text-foreground">{{ issue.title }}</span>
            <span class="bw-badge bw-badge-warning">{{ humanize(issue.severity) }}</span>
          </div>
          <p class="text-muted-foreground">{{ issue.description }}</p>
          <p v-if="issue.clause_ref" class="text-xs text-muted-foreground">{{ issue.clause_ref }}</p>
        </li>
      </ul>
    </section>

    <section v-if="letterPackResult" class="bw-card space-y-4 p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold text-foreground">AI letter pack</h2>
          <p class="text-sm text-muted-foreground">{{ letterPackResult.content }}</p>
        </div>
        <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="letterPackResult = null">
          Close
        </button>
      </div>
      <AiDisclaimerBanner v-if="aiSettings" :disclaimer="letterPackResult.disclaimer" compact />
      <div v-if="letterPackResult.letters?.length" class="divide-y divide-border rounded-lg border border-border">
        <article
          v-for="letter in letterPackResult.letters"
          :key="letter.type"
          class="space-y-2 px-4 py-3"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-medium text-foreground">{{ letter.title }}</h3>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              @click="applyLetterDraft(letter.content_html)"
            >
              Open draft
            </button>
          </div>
          <div
            class="prose prose-sm max-w-none text-sm text-muted-foreground"
            v-html="letter.content_html"
          />
        </article>
      </div>
    </section>
  </div>

  <BwModal :open="showFolderModal" title="New folder" size="sm" @close="showFolderModal = false">
    <form id="folder-form" @submit.prevent="createFolder">
      <label class="bw-label" for="folder-name">Folder name</label>
      <input id="folder-name" v-model="newFolderName" class="bw-input" placeholder="Pleadings" required />
    </form>
    <template #footer>
      <button type="button" class="bw-btn bw-btn-outline" @click="showFolderModal = false">Cancel</button>
      <button type="submit" form="folder-form" class="bw-btn bw-btn-action" :disabled="isCreatingFolder">
        {{ isCreatingFolder ? 'Creating…' : 'Create folder' }}
      </button>
    </template>
  </BwModal>

  <BwModal :open="showUploadModal" title="Upload document" size="md" @close="closeUploadModal">
    <form id="doc-upload-form" class="space-y-4" @submit.prevent="uploadDocument">
      <div>
        <label class="bw-label" for="doc-file">File</label>
        <input id="doc-file" required type="file" class="bw-input" @change="handleFileChange" />
      </div>
      <div>
        <label class="bw-label" for="doc-name">Display name</label>
        <input id="doc-name" v-model="name" class="bw-input" placeholder="Leave blank to use the filename" />
      </div>
      <div>
        <label class="bw-label" for="doc-type">Document type</label>
        <select id="doc-type" v-model="documentType" class="bw-select">
          <option v-for="type in DOCUMENT_TYPES" :key="type" :value="type">
            {{ documentTypeLabel(type) }}
          </option>
        </select>
      </div>
    </form>
    <template #footer>
      <button type="button" class="bw-btn bw-btn-outline" @click="closeUploadModal">Cancel</button>
      <button
        type="submit"
        form="doc-upload-form"
        class="bw-btn bw-btn-action"
        :disabled="isUploading || !selectedFile"
      >
        {{ isUploading ? 'Uploading…' : 'Upload document' }}
      </button>
    </template>
  </BwModal>
</template>
