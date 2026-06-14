<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import {
  courtFilingsApi,
  courtFormInstancesApi,
  courtFormTemplatesApi,
} from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { CourtFiling, CourtFormInstance, CourtFormTemplate } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()
const filings = ref<CourtFiling[]>([])
const forms = ref<CourtFormInstance[]>([])
const templates = ref<CourtFormTemplate[]>([])
const selectedTemplateId = ref<number | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const updatingFilingId = ref<number | null>(null)
const error = ref<string | null>(null)
const showCreateFormModal = ref(false)

const nextStatusOptions: Record<string, string[]> = {
  draft: ['under_review', 'ready_to_file'],
  under_review: ['approved', 'draft'],
  approved: ['ready_to_file'],
  ready_to_file: ['filed'],
  filed: ['accepted_by_court', 'rejected_by_court'],
  rejected_by_court: ['correction_required', 'resubmitted'],
  correction_required: ['resubmitted', 'ready_to_file'],
  resubmitted: ['filed', 'accepted_by_court', 'rejected_by_court'],
  accepted_by_court: ['hearing_date_assigned', 'completed'],
  hearing_date_assigned: ['completed'],
}

const activeForms = computed(() => forms.value.filter((form) => form.status !== 'filed'))

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [filingResult, formList, templateList] = await Promise.all([
      courtFilingsApi.list({ legal_matter_id: props.caseId }),
      courtFormInstancesApi.list({ legal_matter_id: props.caseId }),
      can('court-forms.view') ? courtFormTemplatesApi.list() : Promise.resolve([]),
    ])
    filings.value = filingResult.filings
    forms.value = formList
    templates.value = templateList
    if (!selectedTemplateId.value && templateList.length > 0) {
      selectedTemplateId.value = templateList[0]!.id
    }
  } catch (err) {
    error.value = formatApiError(err, 'Filings are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function createForm() {
  if (!can('court-forms.create') || !selectedTemplateId.value) return
  isSaving.value = true
  error.value = null
  try {
    await courtFormInstancesApi.create({
      legal_matter_id: props.caseId,
      court_form_template_id: selectedTemplateId.value,
    })
    await load()
    showCreateFormModal.value = false
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the court form.')
  } finally {
    isSaving.value = false
  }
}

async function createFilingFromForm(form: CourtFormInstance) {
  if (!can('filings.create')) return
  isSaving.value = true
  error.value = null
  try {
    await courtFormInstancesApi.createFiling(form.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the filing.')
  } finally {
    isSaving.value = false
  }
}

async function advanceStatus(filing: CourtFiling, status: string) {
  if (!can('filings.update')) return
  updatingFilingId.value = filing.id
  error.value = null
  try {
    await courtFilingsApi.updateStatus(filing.id, { status })
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not update filing status.')
  } finally {
    updatingFilingId.value = null
  }
}

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(load)
</script>

<template>
  <div class="space-y-8">
    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

    <section v-if="can('court-forms.view')" class="bw-card overflow-hidden">
      <div class="bw-card-header">
        <h3 class="font-semibold text-foreground">Court forms</h3>
        <button
          v-if="can('court-forms.create') && templates.length > 0"
          type="button"
          class="bw-btn bw-btn-accent bw-btn-sm"
          @click="showCreateFormModal = true"
        >
          <PhPlus class="h-4 w-4" weight="bold" />
          New form
        </button>
      </div>

      <div v-if="isLoading" class="space-y-2 p-4">
        <Skeleton class="h-14 w-full" />
      </div>

      <EmptyState
        v-else-if="activeForms.length === 0"
        title="No court forms"
        message="Select a template to auto-fill from case and client data."
      />

      <div v-else class="divide-y divide-border">
        <article
          v-for="form in activeForms"
          :key="form.id"
          class="space-y-3 p-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-medium">{{ form.title }}</p>
              <p class="text-sm text-muted-foreground">
                {{ form.template?.jurisdiction }} · {{ humanize(form.status) }}
              </p>
            </div>
            <StatusBadge :status="form.status" />
          </div>

          <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div v-for="field in form.template?.fields ?? []" :key="field.key">
              <dt class="text-muted-foreground">{{ field.label }}</dt>
              <dd>{{ form.field_values?.[field.key] || '—' }}</dd>
            </div>
          </dl>

          <button
            v-if="can('filings.create') && !form.court_filing_id"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="isSaving"
            @click="createFilingFromForm(form)"
          >
            Create filing draft
          </button>
        </article>
      </div>
    </section>

    <section class="space-y-4">
      <h3 class="text-base font-semibold">Filing tracker</h3>

      <div v-if="isLoading" class="space-y-2">
        <Skeleton v-for="n in 3" :key="n" class="h-16 w-full" />
      </div>

      <EmptyState
        v-else-if="filings.length === 0"
        title="No filings tracked"
        description="Create a filing from a court form or add one manually to track court responses."
      />

      <div v-else class="space-y-3">
        <article
          v-for="filing in filings"
          :key="filing.id"
          class="bw-card space-y-3 p-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-medium">{{ filing.title }}</p>
              <p class="text-sm text-muted-foreground">
                {{ filing.court }} · {{ humanize(filing.filing_method) }}
              </p>
            </div>
            <StatusBadge :status="filing.status" />
          </div>

          <div class="grid gap-2 text-sm sm:grid-cols-3">
            <div>
              <p class="text-muted-foreground">Filing date</p>
              <p>{{ formatDate(filing.filing_date) }}</p>
            </div>
            <div>
              <p class="text-muted-foreground">Court reference</p>
              <p>{{ filing.court_reference_number || '—' }}</p>
            </div>
            <div>
              <p class="text-muted-foreground">Filed by</p>
              <p>{{ filing.filed_by_user?.name || '—' }}</p>
            </div>
          </div>

          <p v-if="filing.court_response" class="text-sm text-muted-foreground">
            Court response: {{ filing.court_response }}
          </p>

          <div
            v-if="can('filings.update') && (nextStatusOptions[filing.status] ?? []).length > 0"
            class="flex flex-wrap gap-2"
          >
            <button
              v-for="nextStatus in nextStatusOptions[filing.status]"
              :key="nextStatus"
              type="button"
              class="bw-btn-secondary text-sm"
              :disabled="updatingFilingId === filing.id"
              @click="advanceStatus(filing, nextStatus)"
            >
              Mark {{ humanize(nextStatus) }}
            </button>
          </div>
        </article>
      </div>
    </section>

    <BwModal
      v-if="can('court-forms.create') && templates.length > 0"
      :open="showCreateFormModal"
      title="New court form"
      size="md"
      @close="showCreateFormModal = false"
    >
      <form id="court-form" class="space-y-4" @submit.prevent="createForm">
        <div>
          <label class="bw-label" for="court-form-template">Template</label>
          <select id="court-form-template" v-model="selectedTemplateId" class="bw-select">
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }} ({{ template.jurisdiction }})
            </option>
          </select>
        </div>
        <p class="text-sm text-muted-foreground">
          Fields auto-fill from case and client data.
        </p>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showCreateFormModal = false">
          Cancel
        </button>
        <button type="submit" form="court-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Creating…' : 'Create form' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
