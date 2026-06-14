<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhPlus, PhScales } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { humanize } from '@/lib/status'
import { conflictChecksApi, type ConflictCheckPayload } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { ConflictCheck } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const checks = ref<ConflictCheck[]>([])
const search = ref('')
const isLoading = ref(true)
const isSaving = ref(false)
const updatingId = ref<number | null>(null)
const error = ref<string | null>(null)
const showModal = ref(false)
const form = ref<ConflictCheckPayload>({
  search_terms: [],
  notes: '',
})
const subjectName = ref('')
const extraTerms = ref('')

const filteredChecks = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return checks.value
  return checks.value.filter((check) => {
    return [termsLabel(check), check.status, check.decision]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle))
  })
})

function parseTerms(): string[] {
  return [subjectName.value, ...extraTerms.value.split(/[\n,]/)]
    .map((term) => term.trim())
    .filter(Boolean)
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    checks.value = await conflictChecksApi.list(props.caseId)
  } catch (err) {
    error.value = formatApiError(err, 'Conflict checks are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  subjectName.value = ''
  extraTerms.value = ''
  form.value = {
    search_terms: [],
    notes: '',
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

async function createCheck() {
  const searchTerms = parseTerms()
  if (searchTerms.length === 0) {
    error.value = 'Add at least one name, company, or alias to check.'
    return
  }
  isSaving.value = true
  error.value = null
  try {
    const created = await conflictChecksApi.create(props.caseId, {
      search_terms: searchTerms,
      notes: form.value.notes || null,
    })
    checks.value = [created, ...checks.value]
    closeModal()
  } catch (err) {
    error.value = formatApiError(err, 'We could not run this conflict check.')
  } finally {
    isSaving.value = false
  }
}

async function reviewCheck(check: ConflictCheck, status: string) {
  updatingId.value = check.id
  error.value = null
  const previousStatus = check.status
  check.status = status
  try {
    const updated = await conflictChecksApi.update(check.id, { status })
    checks.value = checks.value.map((item) =>
      item.id === updated.id ? updated : item,
    )
  } catch (err) {
    check.status = previousStatus
    error.value = formatApiError(err, 'We could not update this review.')
  } finally {
    updatingId.value = null
  }
}

function matchCount(check: ConflictCheck) {
  return Object.values(check.matches ?? {}).reduce((count, group) => {
    return count + (Array.isArray(group) ? group.length : 0)
  }, 0)
}

function termsLabel(check: ConflictCheck) {
  return check.search_terms.join(', ')
}

function formatDate(iso?: string | null) {
  if (!iso) return 'Not reviewed'
  return new Date(iso).toLocaleString()
}

onMounted(load)
</script>

<template>
  <section class="bw-card overflow-hidden">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">Conflict checks</h2>
        <p class="text-sm text-muted-foreground">
          Search for related parties, record risk, and clear the case with care.
        </p>
      </div>
      <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="openCreate">
        <PhPlus class="h-4 w-4" weight="bold" />
        Run check
      </button>
    </div>

    <div class="border-b border-border px-6 py-4">
      <input
        v-model="search"
        type="search"
        class="bw-input"
        placeholder="Search checks by party, status, or decision…"
        aria-label="Search conflict checks"
      />
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="3" />
    <p v-else-if="error" class="p-6 text-sm text-destructive" role="alert">
      {{ error }}
    </p>
    <div v-else-if="filteredChecks.length" class="divide-y divide-border">
      <article v-for="check in filteredChecks" :key="check.id" class="space-y-4 px-6 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-medium text-foreground">{{ termsLabel(check) }}</h3>
            <p class="text-sm text-muted-foreground">
              {{ check.case?.title || 'General conflict check' }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <StatusBadge :status="check.status" />
            <span v-if="check.decision" class="bw-badge bw-badge-neutral">
              {{ humanize(check.decision) }}
            </span>
            <span class="bw-badge bw-badge-neutral tabular-nums">
              {{ matchCount(check) }} matches
            </span>
          </div>
        </div>

        <p v-if="check.notes" class="whitespace-pre-wrap text-sm text-foreground">
          {{ check.notes }}
        </p>
        <p class="text-xs text-muted-foreground">
          Reviewed {{ formatDate(check.reviewed_at) }}
          <span v-if="check.reviewer"> by {{ check.reviewer.name }}</span>
        </p>

        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingId === check.id"
            @click="reviewCheck(check, 'cleared')"
          >
            Clear
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingId === check.id"
            @click="reviewCheck(check, 'in_review')"
          >
            In review
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-danger bw-btn-sm"
            :disabled="updatingId === check.id"
            @click="reviewCheck(check, 'rejected')"
          >
            Reject
          </button>
        </div>
      </article>
    </div>
    <EmptyState
      v-else
      :icon="PhScales"
      title="No conflict checks yet"
      message="Run a check to screen parties for this case."
    />

    <BwModal
      :open="showModal"
      title="Run conflict check"
      size="md"
      @close="closeModal"
    >
      <form id="conflict-check-form" class="space-y-4" @submit.prevent="createCheck">
        <div>
          <label class="bw-label" for="check-subject">Subject name</label>
          <input
            id="check-subject"
            v-model="subjectName"
            required
            class="bw-input"
            placeholder="Client, opposing party, company…"
          />
        </div>
        <div>
          <label class="bw-label" for="check-terms">Search terms</label>
          <textarea
            id="check-terms"
            v-model="extraTerms"
            rows="3"
            class="bw-textarea"
            placeholder="Aliases, directors, related matters, known addresses."
          />
        </div>
        <div>
          <label class="bw-label" for="check-notes">Review note</label>
          <textarea
            id="check-notes"
            v-model="form.notes"
            rows="3"
            class="bw-textarea"
            placeholder="Why this check is being run."
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeModal">
          Cancel
        </button>
        <button
          type="submit"
          form="conflict-check-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving"
        >
          {{ isSaving ? 'Checking…' : 'Run check' }}
        </button>
      </template>
    </BwModal>
  </section>
</template>
