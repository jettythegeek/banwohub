<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhFolderSimple, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { evidenceApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { EvidenceCustodyLog, EvidenceItem } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()
const items = ref<EvidenceItem[]>([])
const statuses = ref<string[]>([])
const evidenceTypes = ref<string[]>([])
const custodyActions = ref<string[]>([])
const selectedFile = ref<File | null>(null)
const title = ref('')
const description = ref('')
const evidenceType = ref('pdf')
const source = ref('')
const dateObtained = ref('')
const showUploadModal = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const isExporting = ref(false)
const updatingId = ref<number | null>(null)
const expandedId = ref<number | null>(null)
const custodyLogs = ref<EvidenceCustodyLog[]>([])
const custodyAction = ref('transferred')
const custodyNotes = ref('')
const error = ref<string | null>(null)

const exhibits = computed(() => items.value.filter((item) => item.exhibit_number))

const nextStatusOptions: Record<string, string[]> = {
  uploaded: ['under_review', 'archived'],
  under_review: ['approved', 'rejected', 'uploaded'],
  approved: ['marked_as_exhibit', 'rejected'],
  rejected: ['uploaded', 'archived'],
  marked_as_exhibit: ['filed', 'archived'],
  filed: ['archived'],
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await evidenceApi.list({ legal_matter_id: props.caseId })
    items.value = result.items
    statuses.value = result.statuses
    evidenceTypes.value = result.evidenceTypes
    custodyActions.value = result.custodyActions
  } catch (err) {
    error.value = formatApiError(err, 'Evidence is not available yet.')
  } finally {
    isLoading.value = false
  }
}

function resetUploadForm() {
  title.value = ''
  description.value = ''
  source.value = ''
  dateObtained.value = ''
  evidenceType.value = 'pdf'
  selectedFile.value = null
}

function openUpload() {
  resetUploadForm()
  showUploadModal.value = true
}

function closeUpload() {
  showUploadModal.value = false
  resetUploadForm()
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
  if (selectedFile.value && !title.value) {
    title.value = selectedFile.value.name.replace(/\.[^.]+$/, '')
  }
}

async function uploadEvidence() {
  if (!can('evidence.create') || !title.value.trim()) return
  isSaving.value = true
  error.value = null
  try {
    await evidenceApi.upload(props.caseId, {
      file: selectedFile.value ?? undefined,
      title: title.value.trim(),
      description: description.value || undefined,
      evidence_type: evidenceType.value,
      source: source.value || undefined,
      date_obtained: dateObtained.value || undefined,
    })
    closeUpload()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not upload this evidence.')
  } finally {
    isSaving.value = false
  }
}

async function advanceStatus(item: EvidenceItem, status: string) {
  if (!can('evidence.update')) return
  updatingId.value = item.id
  error.value = null
  try {
    await evidenceApi.updateStatus(item.id, status)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not update evidence status.')
  } finally {
    updatingId.value = null
  }
}

async function assignExhibit(item: EvidenceItem) {
  if (!can('evidence.update')) return
  updatingId.value = item.id
  error.value = null
  try {
    await evidenceApi.assignExhibit(item.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not assign an exhibit number.')
  } finally {
    updatingId.value = null
  }
}

async function toggleCustody(item: EvidenceItem) {
  if (expandedId.value === item.id) {
    expandedId.value = null
    custodyLogs.value = []
    return
  }
  expandedId.value = item.id
  custodyLogs.value = await evidenceApi.custodyLogs(item.id)
}

async function addCustodyLog(item: EvidenceItem) {
  if (!can('evidence.update') || !custodyAction.value) return
  isSaving.value = true
  error.value = null
  try {
    await evidenceApi.addCustodyLog(item.id, {
      action: custodyAction.value,
      notes: custodyNotes.value || undefined,
    })
    custodyNotes.value = ''
    custodyLogs.value = await evidenceApi.custodyLogs(item.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not log chain of custody.')
  } finally {
    isSaving.value = false
  }
}

async function exportBundle() {
  if (!can('evidence.view') || exhibits.value.length === 0) return
  isExporting.value = true
  error.value = null
  try {
    const blob = await evidenceApi.exportBundle(props.caseId)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'exhibit-bundle.zip'
    link.click()
    URL.revokeObjectURL(url)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not export the exhibit bundle.')
  } finally {
    isExporting.value = false
  }
}

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(load)
</script>

<template>
  <section class="bw-card overflow-hidden">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">Evidence</h2>
        <p class="text-sm text-muted-foreground">
          Upload PDFs, images, statements, and other materials with metadata for this case.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-if="can('evidence.view') && exhibits.length > 0"
          type="button"
          class="bw-btn bw-btn-outline bw-btn-sm"
          :disabled="isExporting"
          @click="exportBundle"
        >
          Export exhibit bundle
        </button>
        <button
          v-if="can('evidence.create')"
          type="button"
          class="bw-btn bw-btn-accent bw-btn-sm"
          @click="openUpload"
        >
          <PhPlus class="h-4 w-4" weight="bold" />
          Upload evidence
        </button>
      </div>
    </div>

    <p v-if="error" class="px-6 pt-4 text-sm text-destructive" role="alert">{{ error }}</p>

    <Skeleton v-if="isLoading" variant="panel" :rows="3" class="p-6" />

    <EmptyState
      v-else-if="items.length === 0"
      :icon="PhFolderSimple"
      title="No evidence yet"
      message="Upload the first file with metadata for this case."
    />

    <div v-else class="divide-y divide-border">
      <article v-for="item in items" :key="item.id" class="space-y-3 px-6 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="font-medium text-foreground">{{ item.title }}</p>
            <p class="text-sm text-muted-foreground">
              {{ humanize(item.evidence_type) }}
              <span v-if="item.source"> · {{ item.source }}</span>
              <span v-if="item.date_obtained"> · {{ formatDate(item.date_obtained) }}</span>
            </p>
            <p v-if="item.description" class="mt-1 text-sm text-foreground">{{ item.description }}</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <StatusBadge :status="item.status" />
            <span v-if="item.exhibit_number" class="bw-badge bw-badge-neutral">
              {{ item.exhibit_number }}
            </span>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-if="can('evidence.update') && !item.exhibit_number"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingId === item.id"
            @click="assignExhibit(item)"
          >
            Assign exhibit #
          </button>
          <button
            v-if="can('evidence.view')"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            @click="toggleCustody(item)"
          >
            Chain of custody
          </button>
          <select
            v-if="can('evidence.update') && nextStatusOptions[item.status]?.length"
            class="bw-select bw-btn-sm text-xs"
            :disabled="updatingId === item.id"
            @change="advanceStatus(item, ($event.target as HTMLSelectElement).value)"
          >
            <option value="" selected disabled>Change status</option>
            <option
              v-for="status in nextStatusOptions[item.status]"
              :key="status"
              :value="status"
            >
              {{ humanize(status) }}
            </option>
          </select>
        </div>

        <div v-if="expandedId === item.id" class="border-t border-border pt-4">
          <h4 class="mb-2 text-sm font-semibold text-foreground">Chain of custody</h4>
          <ul v-if="custodyLogs.length" class="mb-3 space-y-2 text-sm">
            <li
              v-for="log in custodyLogs"
              :key="log.id"
              class="rounded-md border border-border bg-surface p-2"
            >
              <span class="font-medium">{{ humanize(log.action) }}</span>
              <span class="text-muted-foreground"> · {{ formatDate(log.logged_at) }}</span>
              <p v-if="log.notes" class="mt-1">{{ log.notes }}</p>
            </li>
          </ul>
          <p v-else class="mb-3 text-sm text-muted-foreground">No custody entries yet.</p>

          <form
            v-if="can('evidence.update')"
            class="flex flex-col gap-2 sm:flex-row sm:items-end"
            @submit.prevent="addCustodyLog(item)"
          >
            <select v-model="custodyAction" class="bw-select text-sm">
              <option v-for="action in custodyActions" :key="action" :value="action">
                {{ humanize(action) }}
              </option>
            </select>
            <input v-model="custodyNotes" class="bw-input flex-1 text-sm" placeholder="Notes" />
            <button type="submit" class="bw-btn bw-btn-action bw-btn-sm" :disabled="isSaving">
              Log entry
            </button>
          </form>
        </div>
      </article>
    </div>

    <BwModal :open="showUploadModal" title="Upload evidence" size="md" @close="closeUpload">
      <form id="evidence-upload-form" class="space-y-4" @submit.prevent="uploadEvidence">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="evidence-title">Title</label>
            <input id="evidence-title" v-model="title" class="bw-input" required />
          </div>
          <div>
            <label class="bw-label" for="evidence-type">Type</label>
            <select id="evidence-type" v-model="evidenceType" class="bw-select">
              <option v-for="type in evidenceTypes" :key="type" :value="type">
                {{ humanize(type) }}
              </option>
            </select>
          </div>
          <div>
            <label class="bw-label" for="evidence-source">Source</label>
            <input id="evidence-source" v-model="source" class="bw-input" />
          </div>
          <div>
            <label class="bw-label" for="evidence-date">Date obtained</label>
            <input id="evidence-date" v-model="dateObtained" type="date" class="bw-input" />
          </div>
        </div>
        <div>
          <label class="bw-label" for="evidence-description">Description</label>
          <textarea id="evidence-description" v-model="description" rows="2" class="bw-textarea" />
        </div>
        <div>
          <label class="bw-label" for="evidence-file">File</label>
          <input id="evidence-file" type="file" class="bw-input" @change="handleFileChange" />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeUpload">Cancel</button>
        <button
          type="submit"
          form="evidence-upload-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving"
        >
          {{ isSaving ? 'Uploading…' : 'Upload evidence' }}
        </button>
      </template>
    </BwModal>
  </section>
</template>
