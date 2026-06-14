<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { PhNotePencil, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { humanize } from '@/lib/status'
import { caseNotesApi, type CaseNotePayload } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { CaseNote } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const notes = ref<CaseNote[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showModal = ref(false)
const editingId = ref<number | null>(null)
const form = ref<CaseNotePayload>({
  title: '',
  body: '',
  note_type: 'private_note',
  visibility: 'assigned_team',
})

function resetForm() {
  editingId.value = null
  form.value = {
    title: '',
    body: '',
    note_type: 'private_note',
    visibility: 'assigned_team',
  }
}

function openCreate() {
  resetForm()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  resetForm()
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    notes.value = await caseNotesApi.list(props.caseId)
  } catch (err) {
    error.value = formatApiError(err, 'Notes are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function editNote(note: CaseNote) {
  editingId.value = note.id
  form.value = {
    title: note.title ?? '',
    body: note.body,
    note_type: note.note_type,
    visibility: note.visibility,
  }
  showModal.value = true
}

async function saveNote() {
  isSaving.value = true
  error.value = null
  try {
    const payload = {
      ...form.value,
      title: form.value.title || null,
    }
    if (editingId.value) {
      const updated = await caseNotesApi.update(editingId.value, payload)
      notes.value = notes.value.map((note) =>
        note.id === updated.id ? updated : note,
      )
    } else {
      const created = await caseNotesApi.create(props.caseId, payload)
      notes.value = [created, ...notes.value]
    }
    closeModal()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this note.')
  } finally {
    isSaving.value = false
  }
}

async function deleteNote(note: CaseNote) {
  if (!window.confirm('Delete this note?')) return
  error.value = null
  try {
    await caseNotesApi.delete(note.id)
    notes.value = notes.value.filter((item) => item.id !== note.id)
    if (editingId.value === note.id) closeModal()
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this note.')
  }
}

function formatDate(iso?: string) {
  if (!iso) return 'Just now'
  return new Date(iso).toLocaleString()
}

onMounted(load)
</script>

<template>
  <section class="bw-card overflow-hidden">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">Case notes</h2>
        <p class="text-sm text-muted-foreground">
          Keep strategy, updates, and client context close to the matter.
        </p>
      </div>
      <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="openCreate">
        <PhPlus class="h-4 w-4" weight="bold" />
        Add note
      </button>
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="3" />
    <p v-else-if="error" class="p-6 text-sm text-destructive" role="alert">
      {{ error }}
    </p>
    <div v-else-if="notes.length" class="divide-y divide-border">
      <article v-for="note in notes" :key="note.id" class="space-y-3 px-6 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-medium text-foreground">
              {{ note.title || 'Untitled note' }}
            </h3>
            <p class="text-xs text-muted-foreground">
              {{ note.author?.name || 'Team note' }} · {{ formatDate(note.created_at) }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2 text-xs">
            <span class="bw-badge bw-badge-neutral">{{ humanize(note.note_type) }}</span>
            <span class="bw-badge bw-badge-info">{{ humanize(note.visibility) }}</span>
          </div>
        </div>
        <p class="whitespace-pre-wrap text-sm text-foreground">{{ note.body }}</p>
        <div class="flex gap-3 text-sm">
          <button
            type="button"
            class="font-medium text-[var(--action-teal)] hover:underline"
            @click="editNote(note)"
          >
            Edit
          </button>
          <button
            type="button"
            class="font-medium text-destructive hover:underline"
            @click="deleteNote(note)"
          >
            Delete
          </button>
        </div>
      </article>
    </div>
    <EmptyState
      v-else
      :icon="PhNotePencil"
      title="No notes yet"
      message="Add the first case note when something important comes up."
    />

    <BwModal
      :open="showModal"
      :title="editingId ? 'Edit note' : 'New note'"
      size="md"
      @close="closeModal"
    >
      <form id="note-form" class="space-y-4" @submit.prevent="saveNote">
        <div>
          <label class="bw-label" for="note-title">Title</label>
          <input
            id="note-title"
            v-model="form.title"
            class="bw-input"
            placeholder="Call summary, filing update…"
          />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="note-type">Type</label>
            <select id="note-type" v-model="form.note_type" class="bw-select">
              <option value="private_note">Private note</option>
              <option value="meeting_note">Meeting note</option>
              <option value="court_note">Court note</option>
              <option value="strategy_note">Strategy note</option>
              <option value="research_summary">Research summary</option>
              <option value="internal_memo">Internal memo</option>
              <option value="call_note">Call note</option>
              <option value="instruction_note">Instruction note</option>
            </select>
          </div>
          <div>
            <label class="bw-label" for="note-visibility">Visibility</label>
            <select id="note-visibility" v-model="form.visibility" class="bw-select">
              <option value="private">Private</option>
              <option value="assigned_team">Assigned team</option>
              <option value="senior_lawyers">Senior lawyers</option>
              <option value="admin">Admin</option>
              <option value="client_visible">Client visible</option>
            </select>
          </div>
        </div>
        <div>
          <label class="bw-label" for="note-body">Note</label>
          <textarea
            id="note-body"
            v-model="form.body"
            required
            rows="6"
            class="bw-textarea"
            placeholder="Write the useful details here."
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeModal">
          Cancel
        </button>
        <button type="submit" form="note-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : editingId ? 'Update note' : 'Add note' }}
        </button>
      </template>
    </BwModal>
  </section>
</template>
