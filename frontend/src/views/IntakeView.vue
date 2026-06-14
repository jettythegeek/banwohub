<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { VueDraggable, type DraggableEvent } from 'vue-draggable-plus'
import { PhArrowLeft, PhClipboardText, PhPlus } from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import IntakeFormBuilder from '@/components/intake/IntakeFormBuilder.vue'
import { humanize, intakePipelineDotVar } from '@/lib/status'
import {
  intakeFormsApi,
  intakeSubmissionsApi,
  type IntakeFormPayload,
} from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { IntakeField, IntakeForm, IntakeSubmission } from '@/types'

const route = useRoute()
const activeTab = ref<'forms' | 'submissions' | 'submit'>('forms')
const forms = ref<IntakeForm[]>([])
const submissions = ref<IntakeSubmission[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const updatingId = ref<number | null>(null)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const editingFormId = ref<number | null>(null)
const submitFormId = ref<number | null>(null)
const draftSubmissionId = ref<number | null>(null)
const submitData = ref<Record<string, unknown>>({})
const submitterName = ref('')
const submitterEmail = ref('')
const submitterPhone = ref('')
const requestInfoNotes = ref('')
const showFormBuilder = ref(false)

const formDraft = ref<IntakeFormPayload>({
  name: '',
  description: '',
  case_type: '',
  status: 'draft',
  fields: [
    { name: 'full_name', label: 'Full name', type: 'text', required: true },
    { name: 'email', label: 'Email', type: 'email', required: true },
    { name: 'phone', label: 'Phone', type: 'phone', required: false },
  ],
})

const publishedForms = computed(() =>
  forms.value.filter((form) => form.status === 'published'),
)

const selectedSubmitForm = computed(() =>
  publishedForms.value.find((form) => form.id === submitFormId.value) ?? null,
)

const visibleSubmitFields = computed(() => {
  const form = selectedSubmitForm.value
  if (!form) return []
  return form.fields.filter((field) => {
    if (field.type !== 'conditional' || !field.conditions?.field) return true
    const parentValue = submitData.value[String(field.conditions.field)]
    return String(parentValue ?? '') === String(field.conditions.equals ?? '')
  })
})

const submissionKanbanColumns = [
  { key: 'new', label: 'New leads' },
  { key: 'in_review', label: 'Reviewing' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'qualified', label: 'Qualified' },
] as const

type SubmissionColumnKey = (typeof submissionKanbanColumns)[number]['key']

const columnSubmissions = ref<Record<SubmissionColumnKey, IntakeSubmission[]>>({
  new: [],
  in_review: [],
  rejected: [],
  qualified: [],
})

function submissionColumnForStatus(status?: string | null): SubmissionColumnKey {
  const normalized = status?.toLowerCase()
  if (normalized === 'draft' || normalized === 'submitted') return 'new'
  if (normalized === 'in_review' || normalized === 'more_info_requested') return 'in_review'
  if (normalized === 'rejected') return 'rejected'
  if (normalized === 'approved' || normalized === 'qualified' || normalized === 'converted')
    return 'qualified'
  return 'new'
}

function statusForColumnDrop(columnKey: SubmissionColumnKey, submission: IntakeSubmission): string {
  if (columnKey === 'new') return submission.status === 'draft' ? 'draft' : 'submitted'
  if (columnKey === 'in_review') return 'in_review'
  if (columnKey === 'rejected') return 'rejected'
  return 'approved'
}

function syncColumnLists() {
  const buckets: Record<SubmissionColumnKey, IntakeSubmission[]> = {
    new: [],
    in_review: [],
    rejected: [],
    qualified: [],
  }
  for (const submission of submissions.value) {
    buckets[submissionColumnForStatus(submission.status)].push(submission)
  }
  for (const column of submissionKanbanColumns) {
    columnSubmissions.value[column.key] = buckets[column.key]
  }
}

function replaceSubmission(updated: IntakeSubmission) {
  submissions.value = submissions.value.map((item) => (item.id === updated.id ? updated : item))
  syncColumnLists()
}

const pipeline = computed(() => {
  const counts: Record<string, number> = {}
  for (const submission of submissions.value) {
    counts[submission.status] = (counts[submission.status] ?? 0) + 1
  }
  return [
    { key: 'new', label: 'New', value: (counts.submitted ?? 0) + (counts.draft ?? 0) },
    { key: 'in_review', label: 'In review', value: (counts.in_review ?? 0) + (counts.more_info_requested ?? 0) },
    { key: 'rejected', label: 'Rejected', value: counts.rejected ?? 0 },
    { key: 'qualified', label: 'Qualified', value: (counts.approved ?? 0) + (counts.converted ?? 0) },
  ]
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [formList, submissionList] = await Promise.all([
      intakeFormsApi.list(),
      intakeSubmissionsApi.list(),
    ])
    forms.value = formList
    submissions.value = submissionList
    syncColumnLists()
    if (!submitFormId.value && publishedForms.value[0]) {
      submitFormId.value = publishedForms.value[0].id
    }
  } catch (err) {
    error.value = formatApiError(err, 'Intake records are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  editingFormId.value = null
  formDraft.value = {
    name: '',
    description: '',
    case_type: '',
    status: 'draft',
    fields: [
      { name: 'full_name', label: 'Full name', type: 'text', required: true },
      { name: 'email', label: 'Email', type: 'email', required: true },
      { name: 'phone', label: 'Phone', type: 'phone', required: false },
    ],
  }
}

function editForm(form: IntakeForm) {
  editingFormId.value = form.id
  formDraft.value = {
    name: form.name,
    description: form.description ?? '',
    case_type: form.case_type ?? '',
    status: form.status,
    fields: [...form.fields],
  }
  showFormBuilder.value = true
}

function openNewForm() {
  resetForm()
  showFormBuilder.value = true
}

function closeFormBuilder() {
  showFormBuilder.value = false
  resetForm()
}

async function saveForm() {
  isSaving.value = true
  error.value = null
  try {
    if (editingFormId.value) {
      const updated = await intakeFormsApi.update(editingFormId.value, {
        ...formDraft.value,
        description: formDraft.value.description || null,
        case_type: formDraft.value.case_type || null,
      })
      forms.value = forms.value.map((form) => (form.id === updated.id ? updated : form))
    } else {
      const created = await intakeFormsApi.create({
        ...formDraft.value,
        description: formDraft.value.description || null,
        case_type: formDraft.value.case_type || null,
      })
      forms.value = [created, ...forms.value]
    }
    closeFormBuilder()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this intake form.')
  } finally {
    isSaving.value = false
  }
}

function startSubmit(form: IntakeForm) {
  submitFormId.value = form.id
  draftSubmissionId.value = null
  submitData.value = {}
  submitterName.value = ''
  submitterEmail.value = ''
  submitterPhone.value = ''
  activeTab.value = 'submit'
}

function resumeDraft(submission: IntakeSubmission) {
  submitFormId.value = submission.intake_form_id
  draftSubmissionId.value = submission.id
  submitData.value = { ...submission.data }
  submitterName.value = submission.submitter_name ?? ''
  submitterEmail.value = submission.submitter_email ?? ''
  submitterPhone.value = submission.submitter_phone ?? ''
  activeTab.value = 'submit'
}

async function saveDraft() {
  if (!submitFormId.value) return
  isSaving.value = true
  error.value = null
  successMessage.value = null
  try {
    const payload = {
      intake_form_id: submitFormId.value,
      submitter_name: submitterName.value || null,
      submitter_email: submitterEmail.value || null,
      submitter_phone: submitterPhone.value || null,
      status: 'draft',
      data: submitData.value,
    }
    const saved = draftSubmissionId.value
      ? await intakeSubmissionsApi.update(draftSubmissionId.value, payload)
      : await intakeSubmissionsApi.create(payload)
    draftSubmissionId.value = saved.id
    upsertSubmission(saved)
    successMessage.value = 'Draft saved. You can continue later from the submissions queue.'
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this draft.')
  } finally {
    isSaving.value = false
  }
}

async function submitIntake() {
  if (!submitFormId.value) return
  isSaving.value = true
  error.value = null
  successMessage.value = null
  try {
    const payload = {
      intake_form_id: submitFormId.value,
      submitter_name: submitterName.value || null,
      submitter_email: submitterEmail.value || null,
      submitter_phone: submitterPhone.value || null,
      status: 'submitted',
      data: submitData.value,
    }
    const saved = draftSubmissionId.value
      ? await intakeSubmissionsApi.update(draftSubmissionId.value, payload)
      : await intakeSubmissionsApi.create(payload)
    upsertSubmission(saved)
    draftSubmissionId.value = null
    submitData.value = {}
    successMessage.value = 'Intake submitted successfully.'
    activeTab.value = 'submissions'
  } catch (err) {
    error.value = formatApiError(err, 'We could not submit this intake.')
  } finally {
    isSaving.value = false
  }
}

function upsertSubmission(saved: IntakeSubmission) {
  if (submissions.value.some((item) => item.id === saved.id)) {
    replaceSubmission(saved)
  } else {
    submissions.value = [saved, ...submissions.value]
    syncColumnLists()
  }
}

async function updateSubmissionStatus(submission: IntakeSubmission, status: string) {
  if (submission.status === status) return
  error.value = null
  const previous = submission.status
  submission.status = status
  updatingId.value = submission.id
  try {
    const updated = await intakeSubmissionsApi.update(submission.id, { status })
    replaceSubmission(updated)
  } catch (err) {
    submission.status = previous
    syncColumnLists()
    error.value = formatApiError(err, 'We could not update this submission status.')
  } finally {
    updatingId.value = null
  }
}

async function onSubmissionDropped(
  columnKey: SubmissionColumnKey,
  event: DraggableEvent<IntakeSubmission>,
) {
  const list = columnSubmissions.value[columnKey]
  const submission = event.data ?? list[event.newIndex ?? 0]
  if (!submission) return
  const targetStatus = statusForColumnDrop(columnKey, submission)
  await updateSubmissionStatus(submission, targetStatus)
}

async function approveSubmission(submission: IntakeSubmission) {
  updatingId.value = submission.id
  try {
    const updated = await intakeSubmissionsApi.approve(submission.id)
    replaceSubmission(updated)
  } catch (err) {
    error.value = formatApiError(err, 'We could not approve this submission.')
  } finally {
    updatingId.value = null
  }
}

async function rejectSubmission(submission: IntakeSubmission) {
  updatingId.value = submission.id
  try {
    const updated = await intakeSubmissionsApi.reject(submission.id)
    replaceSubmission(updated)
  } catch (err) {
    error.value = formatApiError(err, 'We could not reject this submission.')
  } finally {
    updatingId.value = null
  }
}

async function requestInfo(submission: IntakeSubmission) {
  const notes = requestInfoNotes.value.trim()
  if (!notes) {
    error.value = 'Add review notes before requesting more information.'
    return
  }
  updatingId.value = submission.id
  try {
    const updated = await intakeSubmissionsApi.requestInfo(submission.id, notes)
    replaceSubmission(updated)
    requestInfoNotes.value = ''
  } catch (err) {
    error.value = formatApiError(err, 'We could not request more information.')
  } finally {
    updatingId.value = null
  }
}

function toggleCheckboxOption(fieldName: string, option: string, event: Event) {
  const checked = (event.target as HTMLInputElement).checked
  const current = Array.isArray(submitData.value[fieldName])
    ? [...(submitData.value[fieldName] as string[])]
    : []
  submitData.value[fieldName] = checked
    ? [...current, option]
    : current.filter((value) => value !== option)
}

async function convertSubmission(submission: IntakeSubmission) {
  updatingId.value = submission.id
  try {
    const updated = await intakeSubmissionsApi.convert(submission.id)
    replaceSubmission(updated)
  } catch (err) {
    error.value = formatApiError(err, 'We could not convert this submission.')
  } finally {
    updatingId.value = null
  }
}

function pipelineDotStyle(columnKey: string) {
  return { background: `var(${intakePipelineDotVar(columnKey)})` }
}

function formatDate(iso?: string | null) {
  if (!iso) return 'Not recorded'
  return new Date(iso).toLocaleString()
}

function fieldInputType(field: IntakeField) {
  if (field.type === 'email') return 'email'
  if (field.type === 'phone') return 'tel'
  if (field.type === 'date') return 'date'
  return 'text'
}

watch(
  () => route.query.tab,
  (tab) => {
    if (tab === 'submissions') activeTab.value = 'submissions'
  },
  { immediate: true },
)

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <section v-if="showFormBuilder" class="space-y-0 overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
      <header class="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4">
        <button
          type="button"
          class="bw-btn bw-btn-ghost bw-btn-icon"
          aria-label="Back to forms"
          @click="closeFormBuilder"
        >
          <PhArrowLeft class="h-5 w-5" />
        </button>
        <div class="min-w-0 flex-1">
          <input
            v-model="formDraft.name"
            required
            class="w-full max-w-md border-0 bg-transparent p-0 text-lg font-semibold text-foreground outline-none placeholder:text-muted focus-visible:ring-0"
            placeholder="Form name"
          />
          <p class="text-xs text-muted-foreground">
            {{ editingFormId ? 'Edit intake form' : 'New intake form' }}
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="formDraft.case_type"
            class="bw-input w-40"
            placeholder="Case type"
          />
          <select v-model="formDraft.status" class="bw-select w-36">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
          <button type="button" class="bw-btn bw-btn-outline" @click="closeFormBuilder">
            Cancel
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-action"
            :disabled="isSaving || !formDraft.name.trim()"
            @click="saveForm"
          >
            {{ isSaving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </header>
      <div class="border-b border-border px-5 py-3">
        <label class="bw-label" for="intake-description">Description</label>
        <textarea
          id="intake-description"
          v-model="formDraft.description"
          rows="1"
          class="bw-textarea"
          placeholder="Optional description for staff"
        />
      </div>
      <IntakeFormBuilder v-model="formDraft.fields" />
    </section>

    <template v-else>
    <PageHeader title="Intake" subtitle="Build forms, submit leads, and review submissions.">
      <template #actions>
        <button
          v-if="activeTab === 'forms'"
          type="button"
          class="bw-btn bw-btn-accent"
          @click="openNewForm"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          New form
        </button>
      </template>
    </PageHeader>

    <div class="bw-card p-5">
      <p class="text-sm font-semibold text-foreground">Work status</p>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div v-for="stage in pipeline" :key="stage.key" class="flex items-center gap-3">
          <span
            class="h-9 w-1.5 shrink-0 rounded-full"
            :style="pipelineDotStyle(stage.key)"
            aria-hidden="true"
          />
          <div>
            <p class="text-2xl font-semibold tabular-nums text-foreground">
              {{ isLoading ? '—' : stage.value }}
            </p>
            <p class="text-xs text-muted-foreground">{{ stage.label }}</p>
          </div>
        </div>
      </div>
    </div>

    <nav class="bw-tabs">
      <button
        v-for="tab in [
          { key: 'forms', label: 'Forms' },
          { key: 'submit', label: 'Submit intake' },
          { key: 'submissions', label: 'Submissions' },
        ]"
        :key="tab.key"
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeTab === tab.key }"
        @click="activeTab = tab.key as typeof activeTab"
      >
        {{ tab.label }}
      </button>
    </nav>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    <p v-if="successMessage" class="text-sm text-success">{{ successMessage }}</p>

    <Skeleton v-if="isLoading" variant="panel" :rows="4" />

    <section v-else-if="activeTab === 'forms'" class="bw-card overflow-hidden">
      <div class="bw-card-header">
        <div>
          <h2 class="font-semibold text-foreground">Intake forms</h2>
          <p class="text-sm text-muted-foreground">Build and publish staff-facing intake forms.</p>
        </div>
      </div>
      <div v-if="forms.length" class="divide-y divide-border">
        <article v-for="form in forms" :key="form.id" class="px-6 py-4 text-sm">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="font-medium text-foreground">{{ form.name }}</h3>
              <p class="text-muted-foreground">
                {{ form.description || form.case_type || 'General intake' }}
              </p>
            </div>
            <StatusBadge :status="form.status" />
          </div>
          <p class="mt-2 text-xs tabular-nums text-muted-foreground">
            {{ form.fields.length }} fields · {{ form.submissions_count ?? 0 }} submissions
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="editForm(form)">
              Edit
            </button>
            <button
              v-if="form.status === 'published'"
              type="button"
              class="bw-btn bw-btn-action bw-btn-sm"
              @click="startSubmit(form)"
            >
              Submit
            </button>
          </div>
        </article>
      </div>
      <EmptyState
        v-else
        :icon="PhClipboardText"
        title="No intake forms yet"
        message="Create a form to start collecting leads."
      />
    </section>

    <section v-else-if="activeTab === 'submit'" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
      <form class="bw-card space-y-4 p-6" @submit.prevent="submitIntake">
        <div>
          <h2 class="font-semibold text-foreground">Staff intake submission</h2>
          <p class="text-sm text-muted-foreground">Save a draft or submit when ready.</p>
        </div>
        <div>
          <label class="bw-label">Form</label>
          <select v-model="submitFormId" class="bw-select">
            <option :value="null" disabled>Choose form</option>
            <option v-for="form in publishedForms" :key="form.id" :value="form.id">
              {{ form.name }}
            </option>
          </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
          <div>
            <label class="bw-label">Submitter name</label>
            <input v-model="submitterName" class="bw-input" />
          </div>
          <div>
            <label class="bw-label">Email</label>
            <input v-model="submitterEmail" type="email" class="bw-input" />
          </div>
          <div>
            <label class="bw-label">Phone</label>
            <input v-model="submitterPhone" type="tel" class="bw-input" />
          </div>
        </div>
        <div v-for="field in visibleSubmitFields" :key="field.name" class="space-y-1">
          <label class="bw-label">
            {{ field.label }}
            <span v-if="field.required" class="text-destructive">*</span>
          </label>
          <textarea
            v-if="field.type === 'long_text'"
            v-model="submitData[field.name]"
            rows="3"
            class="bw-textarea"
            :required="field.required"
          />
          <input
            v-else-if="field.type === 'file'"
            type="file"
            class="bw-input"
            @change="
              submitData[field.name] = ($event.target as HTMLInputElement).files?.[0]?.name ?? ''
            "
          />
          <select
            v-else-if="field.type === 'dropdown'"
            v-model="submitData[field.name]"
            class="bw-select"
            :required="field.required"
          >
            <option value="">Choose…</option>
            <option v-for="option in field.options ?? []" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
          <div v-else-if="field.type === 'checkbox'" class="space-y-2">
            <label
              v-for="option in field.options ?? []"
              :key="option"
              class="flex items-center gap-2 text-sm"
            >
              <input
                type="checkbox"
                :value="option"
                @change="toggleCheckboxOption(field.name, option, $event)"
              />
              {{ option }}
            </label>
          </div>
          <div v-else-if="field.type === 'radio'" class="space-y-2">
            <label
              v-for="option in field.options ?? []"
              :key="option"
              class="flex items-center gap-2 text-sm"
            >
              <input v-model="submitData[field.name]" type="radio" :value="option" />
              {{ option }}
            </label>
          </div>
          <div
            v-else-if="field.type === 'signature'"
            class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
          >
            Signature captured via the client portal e-sign flow when a document is sent for signature.
          </div>
          <input
            v-else
            v-model="submitData[field.name]"
            :type="fieldInputType(field)"
            class="bw-input"
            :required="field.required"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="bw-btn bw-btn-outline" :disabled="isSaving" @click="saveDraft">
            Save & continue
          </button>
          <button type="submit" class="bw-btn bw-btn-primary" :disabled="isSaving || !submitFormId">
            Submit intake
          </button>
        </div>
      </form>
      <aside class="bw-card p-6 text-sm text-muted-foreground">
        Drafts appear in the submissions queue with status “draft”. Resume any draft from the
        submissions board.
      </aside>
    </section>

    <section v-else class="space-y-4">
      <p class="text-sm text-muted-foreground">
        Drag cards between columns to update status — same pipeline as Work status above.
      </p>
      <div class="overflow-x-auto">
        <div class="flex min-w-max gap-4">
          <div
            v-for="column in submissionKanbanColumns"
            :key="column.key"
            class="flex w-72 shrink-0 flex-col gap-3 rounded-lg border border-border bg-surface p-3 shadow-sm"
          >
            <div class="flex items-center gap-2 px-1">
              <span
                class="bw-kanban-dot"
                :style="pipelineDotStyle(column.key)"
                aria-hidden="true"
              />
              <span class="flex-1 text-sm font-semibold text-foreground">{{ column.label }}</span>
              <span
                class="inline-flex h-6 min-w-6 items-center justify-center rounded-full border border-dashed border-border px-1.5 text-xs tabular-nums text-muted-foreground"
              >
                {{ columnSubmissions[column.key].length }}
              </span>
            </div>
            <VueDraggable
              v-model="columnSubmissions[column.key]"
              group="intake-submissions"
              :animation="200"
              ghost-class="bw-kanban-ghost"
              class="min-h-[120px] space-y-3"
              @add="onSubmissionDropped(column.key, $event)"
            >
              <article
                v-for="submission in columnSubmissions[column.key]"
                :key="submission.id"
                class="bw-kanban-card bw-card space-y-3 p-3.5"
              >
                <div class="flex items-start gap-2.5">
                  <AppAvatar
                    :name="submission.submitter_name || submission.submitter_email || 'Lead'"
                    size="sm"
                    tone="accent"
                  />
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-foreground">
                      {{ submission.submitter_name || submission.submitter_email || 'Unnamed lead' }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                      {{ submission.form?.name || 'Intake form' }}
                    </p>
                    <StatusBadge :status="submission.status" />
                  </div>
                </div>
                <dl class="space-y-1 text-xs text-muted-foreground">
                  <div v-for="(value, key) in submission.data" :key="String(key)">
                    <dt class="font-medium text-foreground">{{ humanize(String(key)) }}</dt>
                    <dd class="truncate">{{ Array.isArray(value) ? value.join(', ') : value }}</dd>
                  </div>
                  <div v-if="submission.review_notes">
                    <dt class="font-medium text-foreground">Review notes</dt>
                    <dd>{{ submission.review_notes }}</dd>
                  </div>
                </dl>
                <div class="flex flex-wrap gap-1.5 border-t border-border pt-2.5">
                  <button
                    v-if="submission.status === 'draft'"
                    type="button"
                    class="bw-btn bw-btn-outline bw-btn-sm"
                    @click="resumeDraft(submission)"
                  >
                    Resume
                  </button>
                  <button
                    v-if="submission.status === 'submitted'"
                    type="button"
                    class="bw-btn bw-btn-outline bw-btn-sm"
                    :disabled="updatingId === submission.id"
                    @click="approveSubmission(submission)"
                  >
                    Approve
                  </button>
                  <button
                    v-if="!submission.converted_case && submission.status !== 'rejected'"
                    type="button"
                    class="bw-btn bw-btn-outline bw-btn-sm"
                    :disabled="updatingId === submission.id"
                    @click="rejectSubmission(submission)"
                  >
                    Reject
                  </button>
                  <button
                    v-if="submission.status === 'submitted' || submission.status === 'in_review'"
                    type="button"
                    class="bw-btn bw-btn-outline bw-btn-sm"
                    :disabled="updatingId === submission.id"
                    @click="requestInfo(submission)"
                  >
                    Request info
                  </button>
                  <button
                    v-if="!submission.converted_case && submission.status !== 'draft'"
                    type="button"
                    class="bw-btn bw-btn-primary bw-btn-sm"
                    :disabled="updatingId === submission.id"
                    @click="convertSubmission(submission)"
                  >
                    Convert
                  </button>
                  <RouterLink
                    v-if="submission.converted_case"
                    :to="`/cases/${submission.converted_case.id}`"
                    class="bw-btn bw-btn-outline bw-btn-sm"
                  >
                    Open case
                  </RouterLink>
                </div>
              </article>
            </VueDraggable>
          </div>
        </div>
      </div>
      <input
        v-model="requestInfoNotes"
        class="bw-input max-w-xl"
        placeholder="Review notes for request-info action"
      />
    </section>

    </template>
  </div>
</template>
