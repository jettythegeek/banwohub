<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PhFileText, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { briefsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { LegalBrief } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const router = useRouter()
const { can } = usePermissions()
const briefs = ref<LegalBrief[]>([])
const newTitle = ref('')
const showModal = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const updatingBriefId = ref<number | null>(null)
const error = ref<string | null>(null)

const nextStatusOptions: Record<string, string[]> = {
  draft: ['review'],
  review: ['final', 'draft'],
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await briefsApi.list({ legal_matter_id: props.caseId })
    briefs.value = result.briefs
  } catch (err) {
    error.value = formatApiError(err, 'Briefs are not available yet.')
  } finally {
    isLoading.value = false
  }
}

function openCreate() {
  newTitle.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  newTitle.value = ''
}

async function createBrief() {
  if (!can('briefs.create') || !newTitle.value.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const brief = await briefsApi.create({
      legal_matter_id: props.caseId,
      title: newTitle.value.trim(),
    })
    closeModal()
    await router.push(`/briefs/${brief.id}`)
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the brief.')
  } finally {
    isSaving.value = false
  }
}

async function advanceStatus(brief: LegalBrief, status: string) {
  if (!can('briefs.update')) return
  updatingBriefId.value = brief.id
  error.value = null
  try {
    await briefsApi.updateStatus(brief.id, status)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not update brief status.')
  } finally {
    updatingBriefId.value = null
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
        <h2 class="font-semibold text-foreground">Briefs</h2>
        <p class="text-sm text-muted-foreground">
          Draft arguments with research notes, citations, and AI outline assistance.
        </p>
      </div>
      <button
        v-if="can('briefs.create')"
        type="button"
        class="bw-btn bw-btn-accent bw-btn-sm"
        @click="openCreate"
      >
        <PhPlus class="h-4 w-4" weight="bold" />
        Add brief
      </button>
    </div>

    <p v-if="error" class="px-6 pt-4 text-sm text-destructive" role="alert">{{ error }}</p>

    <Skeleton v-if="isLoading" variant="panel" :rows="3" class="p-6" />

    <EmptyState
      v-else-if="briefs.length === 0"
      :icon="PhFileText"
      title="No briefs yet"
      message="Create a brief to draft arguments for this case."
    />

    <div v-else class="divide-y divide-border">
      <article v-for="brief in briefs" :key="brief.id" class="space-y-3 px-6 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <RouterLink
              :to="`/briefs/${brief.id}`"
              class="font-medium text-[var(--action-teal)] hover:underline"
            >
              {{ brief.title }}
            </RouterLink>
            <p class="text-sm text-muted-foreground">
              Updated {{ formatDate(brief.updated_at) }}
            </p>
          </div>
          <StatusBadge :status="brief.status" />
        </div>

        <div
          v-if="can('briefs.update') && (nextStatusOptions[brief.status] ?? []).length > 0"
          class="flex flex-wrap gap-2"
        >
          <button
            v-for="nextStatus in nextStatusOptions[brief.status]"
            :key="nextStatus"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingBriefId === brief.id"
            @click="advanceStatus(brief, nextStatus)"
          >
            Mark {{ humanize(nextStatus) }}
          </button>
        </div>
      </article>
    </div>

    <BwModal :open="showModal" title="New brief" size="md" @close="closeModal">
      <form id="brief-form" class="space-y-4" @submit.prevent="createBrief">
        <div>
          <label class="bw-label" for="brief-title">Title</label>
          <input
            id="brief-title"
            v-model="newTitle"
            type="text"
            class="bw-input"
            placeholder="Motion in limine, opposition brief…"
            required
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeModal">
          Cancel
        </button>
        <button
          type="submit"
          form="brief-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !newTitle.trim()"
        >
          {{ isSaving ? 'Creating…' : 'Create brief' }}
        </button>
      </template>
    </BwModal>
  </section>
</template>
