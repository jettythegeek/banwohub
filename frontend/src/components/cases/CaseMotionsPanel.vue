<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PhGavel, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { motionsApi, motionTemplatesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { LegalMotion, MotionTemplate } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const router = useRouter()
const { can } = usePermissions()
const motions = ref<LegalMotion[]>([])
const templates = ref<MotionTemplate[]>([])
const newTitle = ref('')
const selectedTemplateId = ref<number | ''>('')
const showModal = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const updatingMotionId = ref<number | null>(null)
const creatingFilingId = ref<number | null>(null)
const error = ref<string | null>(null)

const nextStatusOptions: Record<string, string[]> = {
  draft: ['review'],
  review: ['approved', 'draft'],
  approved: ['filing_ready'],
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [motionResult, templateRows] = await Promise.all([
      motionsApi.list({ legal_matter_id: props.caseId }),
      can('motions.create') ? motionTemplatesApi.list() : Promise.resolve([]),
    ])
    motions.value = motionResult.motions
    templates.value = templateRows
  } catch (err) {
    error.value = formatApiError(err, 'Motions are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function openCreate() {
  newTitle.value = ''
  selectedTemplateId.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  newTitle.value = ''
  selectedTemplateId.value = ''
}

async function createMotion() {
  if (!can('motions.create') || !newTitle.value.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const motion = await motionsApi.create({
      legal_matter_id: props.caseId,
      title: newTitle.value.trim(),
      motion_template_id: selectedTemplateId.value ? Number(selectedTemplateId.value) : undefined,
    })
    closeModal()
    await router.push(`/motions/${motion.id}`)
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the motion.')
  } finally {
    isSaving.value = false
  }
}

async function advanceStatus(motion: LegalMotion, status: string) {
  if (!can('motions.update')) return
  updatingMotionId.value = motion.id
  error.value = null
  try {
    await motionsApi.updateStatus(motion.id, status)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not update motion status.')
  } finally {
    updatingMotionId.value = null
  }
}

async function createFiling(motion: LegalMotion) {
  if (!can('motions.update') || !can('filings.create')) return
  creatingFilingId.value = motion.id
  error.value = null
  try {
    await motionsApi.createFiling(motion.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the court filing.')
  } finally {
    creatingFilingId.value = null
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
        <h2 class="font-semibold text-foreground">Motions</h2>
        <p class="text-sm text-muted-foreground">
          Draft motions with templates, structure checks, and court filing links.
        </p>
      </div>
      <button
        v-if="can('motions.create')"
        type="button"
        class="bw-btn bw-btn-accent bw-btn-sm"
        @click="openCreate"
      >
        <PhPlus class="h-4 w-4" weight="bold" />
        Add motion
      </button>
    </div>

    <p v-if="error" class="px-6 pt-4 text-sm text-destructive" role="alert">{{ error }}</p>

    <Skeleton v-if="isLoading" variant="panel" :rows="3" class="p-6" />

    <EmptyState
      v-else-if="motions.length === 0"
      :icon="PhGavel"
      title="No motions yet"
      message="Create a motion with a template and link it to a court filing when approved."
    />

    <div v-else class="divide-y divide-border">
      <article v-for="motion in motions" :key="motion.id" class="space-y-3 px-6 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <RouterLink
              :to="`/motions/${motion.id}`"
              class="font-medium text-[var(--action-teal)] hover:underline"
            >
              {{ motion.title }}
            </RouterLink>
            <p class="text-sm text-muted-foreground">
              {{ motion.template?.name ?? humanize(motion.motion_type ?? 'motion') }}
              · Updated {{ formatDate(motion.updated_at) }}
            </p>
          </div>
          <StatusBadge :status="motion.status" />
        </div>

        <div v-if="can('motions.update')" class="flex flex-wrap gap-2">
          <button
            v-for="nextStatus in nextStatusOptions[motion.status] ?? []"
            :key="nextStatus"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingMotionId === motion.id"
            @click="advanceStatus(motion, nextStatus)"
          >
            Mark {{ humanize(nextStatus) }}
          </button>
          <button
            v-if="can('motions.update') && can('filings.create') && ['approved', 'filing_ready'].includes(motion.status) && !motion.court_filing_id"
            type="button"
            class="bw-btn bw-btn-action bw-btn-sm"
            :disabled="creatingFilingId === motion.id"
            @click="createFiling(motion)"
          >
            {{ creatingFilingId === motion.id ? 'Creating…' : 'Create filing' }}
          </button>
          <RouterLink v-if="motion.court_filing_id" to="/filings" class="bw-btn bw-btn-outline bw-btn-sm">
            View filing
          </RouterLink>
        </div>
      </article>
    </div>

    <BwModal :open="showModal" title="New motion" size="md" @close="closeModal">
      <form id="motion-form" class="space-y-4" @submit.prevent="createMotion">
        <div>
          <label class="bw-label" for="motion-title">Title</label>
          <input
            id="motion-title"
            v-model="newTitle"
            type="text"
            class="bw-input"
            placeholder="Motion to dismiss, extension of time…"
            required
          />
        </div>
        <div>
          <label class="bw-label" for="motion-template">Template</label>
          <select id="motion-template" v-model="selectedTemplateId" class="bw-select">
            <option value="">Blank motion</option>
            <option v-for="template in templates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeModal">
          Cancel
        </button>
        <button
          type="submit"
          form="motion-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !newTitle.trim()"
        >
          {{ isSaving ? 'Creating…' : 'Create motion' }}
        </button>
      </template>
    </BwModal>
  </section>
</template>
