<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhPlus, PhUploadSimple } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { ediscoveryApi, usersApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type {
  EdiscoveryCollection,
  EdiscoveryDocument,
  EdiscoveryReviewProgress,
  User,
} from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()
const collections = ref<EdiscoveryCollection[]>([])
const documents = ref<EdiscoveryDocument[]>([])
const progress = ref<EdiscoveryReviewProgress | null>(null)
const users = ref<User[]>([])
const privileges = ref<string[]>([])
const relevances = ref<string[]>([])
const reviewStatuses = ref<string[]>([])
const selectedCollectionId = ref<number | null>(null)
const collectionName = ref('')
const selectedFiles = ref<File[]>([])
const privilegeFilter = ref('')
const relevanceFilter = ref('')
const statusFilter = ref('')
const tagFilter = ref('')
const keywordFilter = ref('')
const reviewerFilter = ref<number | ''>('')
const customTagInput = ref('')
const assignReviewerId = ref<number | ''>('')
const expandedId = ref<number | null>(null)
const showCollectionModal = ref(false)
const showUploadModal = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const updatingId = ref<number | null>(null)
const error = ref<string | null>(null)

const activeCollection = computed(() =>
  collections.value.find((c) => c.id === selectedCollectionId.value) ?? null,
)

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [collectionResult, documentResult, progressResult, userResult] = await Promise.all([
      ediscoveryApi.listCollections({ legal_matter_id: props.caseId }),
      ediscoveryApi.listDocuments({
        legal_matter_id: props.caseId,
        ediscovery_collection_id: selectedCollectionId.value ?? undefined,
        privilege: privilegeFilter.value || undefined,
        relevance: relevanceFilter.value || undefined,
        review_status: statusFilter.value || undefined,
        tag: tagFilter.value || undefined,
        reviewer_id: reviewerFilter.value ? Number(reviewerFilter.value) : undefined,
        keyword: keywordFilter.value || undefined,
      }),
      ediscoveryApi.reviewProgress(props.caseId),
      usersApi.listActive(),
    ])
    collections.value = collectionResult.collections
    documents.value = documentResult.documents
    privileges.value = documentResult.privileges
    relevances.value = documentResult.relevances
    reviewStatuses.value = documentResult.reviewStatuses
    progress.value = progressResult
    users.value = userResult
    if (!selectedCollectionId.value && collections.value.length > 0) {
      selectedCollectionId.value = collections.value[0]?.id ?? null
    }
  } catch (err) {
    error.value = formatApiError(err, 'E-discovery is not available yet.')
  } finally {
    isLoading.value = false
  }
}

function openCollectionModal() {
  collectionName.value = ''
  showCollectionModal.value = true
}

function closeCollectionModal() {
  showCollectionModal.value = false
  collectionName.value = ''
}

function openUploadModal() {
  selectedFiles.value = []
  showUploadModal.value = true
}

function closeUploadModal() {
  showUploadModal.value = false
  selectedFiles.value = []
}

function handleFilesChange(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFiles.value = input.files ? Array.from(input.files) : []
}

async function createCollection() {
  if (!can('ediscovery.create') || !collectionName.value.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const collection = await ediscoveryApi.createCollection({
      legal_matter_id: props.caseId,
      name: collectionName.value.trim(),
    })
    selectedCollectionId.value = collection.id
    closeCollectionModal()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create this collection.')
  } finally {
    isSaving.value = false
  }
}

async function bulkUpload() {
  if (!can('ediscovery.create') || !selectedCollectionId.value || selectedFiles.value.length === 0) {
    return
  }
  isSaving.value = true
  error.value = null
  try {
    await ediscoveryApi.bulkUpload(props.caseId, selectedCollectionId.value, selectedFiles.value)
    closeUploadModal()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not upload discovery documents.')
  } finally {
    isSaving.value = false
  }
}

async function tagDocument(doc: EdiscoveryDocument, field: 'privilege' | 'relevance', value: string) {
  if (!can('ediscovery.update')) return
  updatingId.value = doc.id
  error.value = null
  try {
    await ediscoveryApi.updateTags(doc.id, { [field]: value })
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not update document tags.')
  } finally {
    updatingId.value = null
  }
}

async function addCustomTag(doc: EdiscoveryDocument) {
  if (!can('ediscovery.update') || !customTagInput.value.trim()) return
  const tags = [...(doc.custom_tags ?? [])]
  const next = customTagInput.value.trim()
  if (tags.includes(next)) return
  updatingId.value = doc.id
  error.value = null
  try {
    await ediscoveryApi.updateTags(doc.id, { custom_tags: [...tags, next] })
    customTagInput.value = ''
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not add a custom tag.')
  } finally {
    updatingId.value = null
  }
}

async function updateReviewStatus(doc: EdiscoveryDocument, review_status: string) {
  if (!can('ediscovery.update')) return
  updatingId.value = doc.id
  error.value = null
  try {
    await ediscoveryApi.updateReviewStatus(doc.id, review_status)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not update review status.')
  } finally {
    updatingId.value = null
  }
}

async function assignReviewer(doc: EdiscoveryDocument) {
  if (!can('ediscovery.update') || !assignReviewerId.value) return
  updatingId.value = doc.id
  error.value = null
  try {
    await ediscoveryApi.assignReviewer({
      ediscovery_document_id: doc.id,
      reviewer_id: Number(assignReviewerId.value),
    })
    assignReviewerId.value = ''
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not assign a reviewer.')
  } finally {
    updatingId.value = null
  }
}

function toggleExpanded(doc: EdiscoveryDocument) {
  expandedId.value = expandedId.value === doc.id ? null : doc.id
}

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <section v-if="progress" class="bw-card p-6">
      <h2 class="mb-4 font-semibold text-foreground">Review progress</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-border p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Documents</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums">{{ progress.total_documents }}</p>
        </div>
        <div class="rounded-lg border border-border p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Completion</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums">{{ progress.completion_rate }}%</p>
        </div>
        <div class="rounded-lg border border-border p-4 sm:col-span-2">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">By reviewer</p>
          <ul v-if="progress.by_reviewer.length" class="space-y-1 text-sm">
            <li v-for="row in progress.by_reviewer" :key="row.reviewer_id">
              <span class="font-medium">{{ row.reviewer_name }}</span>
              <span class="text-muted-foreground">
                · {{ row.completed }}/{{ row.total }} completed
              </span>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">No reviewer assignments yet.</p>
        </div>
      </div>
    </section>

    <section v-if="can('ediscovery.create')" class="bw-card overflow-hidden">
      <div class="bw-card-header">
        <h2 class="font-semibold text-foreground">Collections</h2>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="activeCollection"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            @click="openUploadModal"
          >
            <PhUploadSimple class="h-4 w-4" />
            Bulk upload
          </button>
          <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="openCollectionModal">
            <PhPlus class="h-4 w-4" weight="bold" />
            New collection
          </button>
        </div>
      </div>
      <div v-if="collections.length" class="flex flex-wrap gap-2 px-6 py-4">
        <button
          v-for="collection in collections"
          :key="collection.id"
          type="button"
          class="bw-btn bw-btn-sm"
          :class="selectedCollectionId === collection.id ? 'bw-btn-accent' : 'bw-btn-outline'"
          @click="selectedCollectionId = collection.id; load()"
        >
          {{ collection.name }}
          <span class="opacity-70">({{ collection.documents_count ?? 0 }})</span>
        </button>
      </div>
      <p v-else class="px-6 pb-4 text-sm text-muted-foreground">Create a collection to organize discovery documents.</p>
    </section>

    <section class="bw-card overflow-hidden">
      <div class="bw-card-header flex-wrap">
        <h2 class="font-semibold text-foreground">Discovery documents</h2>
        <div class="flex flex-wrap gap-2">
          <select v-model="privilegeFilter" class="bw-select bw-btn-sm text-sm" @change="load">
            <option value="">All privilege</option>
            <option v-for="value in privileges" :key="value" :value="value">
              {{ humanize(value) }}
            </option>
          </select>
          <select v-model="relevanceFilter" class="bw-select bw-btn-sm text-sm" @change="load">
            <option value="">All relevance</option>
            <option v-for="value in relevances" :key="value" :value="value">
              {{ humanize(value) }}
            </option>
          </select>
          <select v-model="statusFilter" class="bw-select bw-btn-sm text-sm" @change="load">
            <option value="">All statuses</option>
            <option v-for="value in reviewStatuses" :key="value" :value="value">
              {{ humanize(value) }}
            </option>
          </select>
          <select v-model="reviewerFilter" class="bw-select bw-btn-sm text-sm" @change="load">
            <option value="">All reviewers</option>
            <option v-for="user in users" :key="user.id" :value="user.id">
              {{ user.name }}
            </option>
          </select>
          <input v-model="tagFilter" class="bw-input bw-btn-sm text-sm" placeholder="Tag" @change="load" />
          <input v-model="keywordFilter" class="bw-input bw-btn-sm text-sm" placeholder="Keyword" @change="load" />
        </div>
      </div>

      <Skeleton v-if="isLoading" variant="panel" :rows="3" class="p-6" />

      <EmptyState
        v-else-if="documents.length === 0"
        title="No discovery documents yet"
        description="Create a collection and bulk-upload documents for privilege and relevance review."
      />

      <div v-else class="divide-y divide-border">
        <article v-for="doc in documents" :key="doc.id" class="space-y-3 px-6 py-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-medium text-foreground">{{ doc.title }}</p>
              <p class="text-sm text-muted-foreground">
                {{ humanize(doc.file_type) }}
                <span v-if="doc.sender"> · From {{ doc.sender }}</span>
                <span v-if="doc.recipient"> · To {{ doc.recipient }}</span>
                <span v-if="doc.document_date"> · {{ formatDate(doc.document_date) }}</span>
              </p>
              <div v-if="doc.custom_tags?.length" class="mt-2 flex flex-wrap gap-1">
                <span v-for="tag in doc.custom_tags" :key="tag" class="bw-badge bw-badge-neutral">
                  {{ tag }}
                </span>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <StatusBadge :status="doc.review_status" />
              <span class="bw-badge bw-badge-neutral">{{ humanize(doc.privilege) }}</span>
              <span class="bw-badge bw-badge-neutral">{{ humanize(doc.relevance) }}</span>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <select
              v-if="can('ediscovery.update')"
              class="bw-select bw-btn-sm text-xs"
              :disabled="updatingId === doc.id"
              :value="doc.privilege"
              @change="tagDocument(doc, 'privilege', ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="value in privileges" :key="value" :value="value">
                {{ humanize(value) }}
              </option>
            </select>
            <select
              v-if="can('ediscovery.update')"
              class="bw-select bw-btn-sm text-xs"
              :disabled="updatingId === doc.id"
              :value="doc.relevance"
              @change="tagDocument(doc, 'relevance', ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="value in relevances" :key="value" :value="value">
                {{ humanize(value) }}
              </option>
            </select>
            <select
              v-if="can('ediscovery.update')"
              class="bw-select bw-btn-sm text-xs"
              :disabled="updatingId === doc.id"
              @change="updateReviewStatus(doc, ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="value in reviewStatuses" :key="value" :value="value">
                {{ humanize(value) }}
              </option>
            </select>
            <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="toggleExpanded(doc)">
              Reviewers
            </button>
          </div>

          <div v-if="expandedId === doc.id" class="border-t border-border pt-4">
            <ul v-if="doc.review_assignments?.length" class="mb-3 space-y-2 text-sm">
              <li
                v-for="assignment in doc.review_assignments"
                :key="assignment.id"
                class="rounded-md border border-border bg-surface p-2"
              >
                <span class="font-medium">{{ assignment.reviewer?.name }}</span>
                <span class="text-muted-foreground"> · {{ humanize(assignment.review_status) }}</span>
              </li>
            </ul>
            <p v-else class="mb-3 text-sm text-muted-foreground">No reviewers assigned.</p>

            <form
              v-if="can('ediscovery.update')"
              class="flex flex-col gap-2 sm:flex-row sm:items-end"
              @submit.prevent="assignReviewer(doc)"
            >
              <select v-model="assignReviewerId" class="bw-select text-sm">
                <option value="" disabled>Select reviewer</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ user.name }}
                </option>
              </select>
              <input v-model="customTagInput" class="bw-input flex-1 text-sm" placeholder="Add custom tag" />
              <button
                type="button"
                class="bw-btn bw-btn-outline bw-btn-sm"
                :disabled="updatingId === doc.id"
                @click="addCustomTag(doc)"
              >
                Add tag
              </button>
              <button type="submit" class="bw-btn bw-btn-action bw-btn-sm" :disabled="isSaving">
                Assign reviewer
              </button>
            </form>
          </div>
        </article>
      </div>
    </section>

    <BwModal :open="showCollectionModal" title="New collection" size="sm" @close="closeCollectionModal">
      <form id="collection-form" @submit.prevent="createCollection">
        <label class="bw-label" for="collection-name">Collection name</label>
        <input id="collection-name" v-model="collectionName" class="bw-input" required />
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeCollectionModal">Cancel</button>
        <button
          type="submit"
          form="collection-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving"
        >
          {{ isSaving ? 'Creating…' : 'Create collection' }}
        </button>
      </template>
    </BwModal>

    <BwModal
      :open="showUploadModal && !!activeCollection"
      :title="`Bulk upload to ${activeCollection?.name ?? 'collection'}`"
      size="md"
      @close="closeUploadModal"
    >
      <form id="bulk-upload-form" class="space-y-4" @submit.prevent="bulkUpload">
        <input type="file" multiple class="bw-input" @change="handleFilesChange" />
        <p v-if="selectedFiles.length" class="text-sm text-muted-foreground">
          {{ selectedFiles.length }} file(s) selected
        </p>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeUploadModal">Cancel</button>
        <button
          type="submit"
          form="bulk-upload-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !selectedFiles.length"
        >
          {{ isSaving ? 'Uploading…' : 'Upload documents' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
