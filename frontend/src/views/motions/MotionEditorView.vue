<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { PhArrowLeft, PhListChecks, PhSparkle } from '@phosphor-icons/vue'
import RichTextEditor from '@/components/editor/RichTextEditor.vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { aiApi, motionsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { AiChatResponse, AiGovernanceSettings, LegalMotion } from '@/types'

const route = useRoute()
const { can } = usePermissions()

const motion = ref<LegalMotion | null>(null)
const title = ref('')
const contentHtml = ref('')
const aiSettings = ref<AiGovernanceSettings | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const isChecking = ref(false)
const updatingStatus = ref(false)
const creatingFiling = ref(false)
const error = ref<string | null>(null)
const pendingAi = ref<AiChatResponse | null>(null)
const activeTab = ref<'setup' | 'draft' | 'ai-tools' | 'sections'>('draft')

const motionId = computed(() => Number(route.params.id))
const isReadOnly = computed(() => motion.value?.status === 'filing_ready')
const canUseAi = computed(() => can('ai.use'))
const canEdit = computed(() => can('motions.update') && !isReadOnly.value)

const nextStatusOptions: Record<string, string[]> = {
  draft: ['review'],
  review: ['approved', 'draft'],
  approved: ['filing_ready'],
}

const requiredSections = computed(() => motion.value?.template?.required_sections ?? [])

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const loaded = await motionsApi.get(motionId.value)
    const settings = canUseAi.value ? await aiApi.governanceSettings().catch(() => null) : null
    motion.value = loaded
    title.value = loaded.title
    contentHtml.value = loaded.content_html ?? ''
    aiSettings.value = settings
  } catch (err) {
    error.value = formatApiError(err, 'Motion is not available.')
  } finally {
    isLoading.value = false
  }
}

async function save() {
  if (!motion.value || !canEdit.value) return
  isSaving.value = true
  error.value = null
  try {
    const updated = await motionsApi.update(motion.value.id, {
      title: title.value.trim(),
      content_html: contentHtml.value,
      last_ai_governance_log_id: pendingAi.value?.governance_log_id ?? undefined,
    })
    motion.value = updated
    pendingAi.value = null
  } catch (err) {
    error.value = formatApiError(err, 'We could not save the motion.')
  } finally {
    isSaving.value = false
  }
}

async function runStructureCheck() {
  if (!motion.value || !canUseAi.value || !contentHtml.value.trim()) return
  isChecking.value = true
  error.value = null
  pendingAi.value = null
  try {
    pendingAi.value = await aiApi.motionStructureCheck({
      legal_matter_id: motion.value.legal_matter_id,
      title: title.value.trim() || motion.value.title,
      motion_type: motion.value.motion_type ?? undefined,
      content_html: contentHtml.value,
      required_sections: requiredSections.value,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not run the structure check.')
  } finally {
    isChecking.value = false
  }
}

async function advanceStatus(status: string) {
  if (!motion.value || !can('motions.update')) return
  updatingStatus.value = true
  error.value = null
  try {
    motion.value = await motionsApi.updateStatus(motion.value.id, status)
  } catch (err) {
    error.value = formatApiError(err, 'We could not update motion status.')
  } finally {
    updatingStatus.value = false
  }
}

async function createFiling() {
  if (!motion.value || !can('motions.update') || !can('filings.create')) return
  creatingFiling.value = true
  error.value = null
  try {
    motion.value = await motionsApi.createFiling(motion.value.id)
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the court filing.')
  } finally {
    creatingFiling.value = false
  }
}

watch(motionId, () => {
  void load()
})

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <RouterLink
        v-if="motion?.legal_matter_id"
        :to="`/cases/${motion.legal_matter_id}/motions`"
        class="bw-btn bw-btn-secondary inline-flex items-center gap-2 text-sm"
      >
        <PhArrowLeft class="size-4" aria-hidden="true" />
        Back to case
      </RouterLink>
      <RouterLink to="/motions" class="text-sm text-primary hover:underline">
        All motions
      </RouterLink>
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="6" />

    <template v-else-if="motion">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1 space-y-2">
          <input
            v-model="title"
            type="text"
            class="bw-input w-full border-0 bg-transparent px-0 text-2xl font-semibold tracking-tight shadow-none focus:ring-0"
            :disabled="!canEdit"
            placeholder="Motion title"
          />
          <p v-if="motion.legal_matter" class="text-sm text-muted-foreground">
            {{ motion.legal_matter.title }}
            <span v-if="motion.template"> · {{ motion.template.name }}</span>
            <span v-else-if="motion.motion_type"> · {{ humanize(motion.motion_type) }}</span>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <StatusBadge :status="motion.status" />
          <button
            v-for="nextStatus in nextStatusOptions[motion.status] ?? []"
            :key="nextStatus"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingStatus"
            @click="advanceStatus(nextStatus)"
          >
            Mark {{ humanize(nextStatus) }}
          </button>
          <button
            v-if="canEdit"
            type="button"
            class="bw-btn bw-btn-action bw-btn-sm"
            :disabled="isSaving"
            @click="save"
          >
            {{ isSaving ? 'Saving…' : 'Save motion' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive" role="alert">
        {{ error }}
      </p>

      <AiDisclaimerBanner
        v-if="canUseAi && aiSettings?.disclaimer"
        :disclaimer="aiSettings.disclaimer"
      />

      <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div class="min-w-0 space-y-4">
          <nav class="bw-tabs" aria-label="Motion editor sections">
            <button
              type="button"
              class="bw-tab"
              :class="{ 'bw-tab-active': activeTab === 'setup' }"
              @click="activeTab = 'setup'"
            >
              Case setup
            </button>
            <button
              type="button"
              class="bw-tab"
              :class="{ 'bw-tab-active': activeTab === 'draft' }"
              @click="activeTab = 'draft'"
            >
              Draft
            </button>
            <button
              v-if="canUseAi"
              type="button"
              class="bw-tab"
              :class="{ 'bw-tab-active': activeTab === 'ai-tools' }"
              @click="activeTab = 'ai-tools'"
            >
              AI tools
            </button>
            <button
              type="button"
              class="bw-tab"
              :class="{ 'bw-tab-active': activeTab === 'sections' }"
              @click="activeTab = 'sections'"
            >
              Sections
              <span v-if="requiredSections.length" class="ml-1 text-muted-foreground">({{ requiredSections.length }})</span>
            </button>
          </nav>

          <!-- Case setup -->
          <section v-show="activeTab === 'setup'" class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Motion setup</h2>
                <p class="text-sm text-muted-foreground">
                  Review template, motion type, and filing workflow before drafting.
                </p>
              </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
              <label class="text-sm">
                <span class="bw-label">Motion type</span>
                <input
                  :value="humanize(motion.motion_type ?? 'general')"
                  class="bw-input mt-1.5"
                  disabled
                  readonly
                />
              </label>
              <label class="text-sm">
                <span class="bw-label">Template</span>
                <input
                  :value="motion.template?.name ?? 'No template'"
                  class="bw-input mt-1.5"
                  disabled
                  readonly
                />
              </label>
              <label class="text-sm sm:col-span-2">
                <span class="bw-label">Related case</span>
                <input
                  :value="motion.legal_matter?.title ?? '—'"
                  class="bw-input mt-1.5"
                  disabled
                  readonly
                />
              </label>
              <label v-if="motion.template?.description" class="text-sm sm:col-span-2">
                <span class="bw-label">Template description</span>
                <textarea
                  :value="motion.template.description"
                  class="bw-textarea mt-1.5 min-h-[88px]"
                  disabled
                  readonly
                />
              </label>
              <div class="flex flex-wrap gap-2 border-t border-border pt-4 sm:col-span-2">
                <button
                  v-if="can('motions.update') && can('filings.create') && ['approved', 'filing_ready'].includes(motion.status) && !motion.court_filing_id"
                  type="button"
                  class="bw-btn bw-btn-action"
                  :disabled="creatingFiling"
                  @click="createFiling"
                >
                  {{ creatingFiling ? 'Creating…' : 'Create court filing' }}
                </button>
                <RouterLink
                  v-if="motion.court_filing_id"
                  to="/filings"
                  class="bw-btn bw-btn-outline"
                >
                  View linked filing
                </RouterLink>
              </div>
            </div>
          </section>

          <!-- Draft -->
          <section v-show="activeTab === 'draft'" class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Motion content</h2>
                <p class="text-sm text-muted-foreground">
                  Draft and edit the body of your motion. All AI output requires lawyer review.
                </p>
              </div>
              <button
                v-if="canEdit"
                type="button"
                class="bw-btn bw-btn-action bw-btn-sm"
                :disabled="isSaving"
                @click="save"
              >
                {{ isSaving ? 'Saving…' : 'Save' }}
              </button>
            </div>
            <div class="p-5">
              <RichTextEditor
                v-if="canEdit"
                v-model="contentHtml"
                placeholder="Draft motion sections…"
              />
              <div
                v-else
                class="prose prose-sm max-w-none rounded-lg border border-border bg-surface-muted p-4 text-sm"
                v-html="contentHtml || '<p>No content.</p>'"
              />
            </div>
          </section>

          <!-- AI tools -->
          <section v-show="activeTab === 'ai-tools' && canUseAi" class="space-y-4">
            <div class="bw-card overflow-hidden">
              <div class="bw-card-header">
                <div>
                  <h2 class="font-semibold text-foreground">Structure check</h2>
                  <p class="text-sm text-muted-foreground">
                    Validate your draft against the template's required sections.
                  </p>
                </div>
              </div>
              <div class="space-y-4 p-5">
                <p v-if="requiredSections.length" class="text-sm text-muted-foreground">
                  Expected sections:
                  {{ requiredSections.map((s) => humanize(s)).join(', ') }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                  No required sections defined for this template.
                </p>
                <button
                  type="button"
                  class="bw-btn bw-btn-action inline-flex items-center gap-2"
                  :disabled="isChecking || !contentHtml.trim()"
                  @click="runStructureCheck"
                >
                  <PhSparkle class="size-4" aria-hidden="true" />
                  {{ isChecking ? 'Checking…' : 'Check structure' }}
                </button>
              </div>
            </div>

            <div v-if="pendingAi" class="bw-card space-y-3 p-5">
              <AiOutputBadges :label="pendingAi.label" :requires-review="pendingAi.requires_review" />
              <div class="prose prose-sm max-w-none text-sm" v-html="pendingAi.content" />
              <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="pendingAi = null">
                Dismiss
              </button>
            </div>
          </section>

          <!-- Sections -->
          <section v-show="activeTab === 'sections'" class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Required sections</h2>
                <p class="text-sm text-muted-foreground">
                  Template sections to include in this motion.
                </p>
              </div>
            </div>
            <div class="space-y-4 p-5">
              <ul v-if="requiredSections.length > 0" class="space-y-3">
                <li
                  v-for="section in requiredSections"
                  :key="section"
                  class="flex items-start gap-3 rounded-lg border border-border bg-surface-muted p-4 text-sm"
                >
                  <PhListChecks class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                  <div>
                    <p class="font-medium text-foreground">{{ humanize(section) }}</p>
                    <p class="mt-1 text-muted-foreground">
                      Include this section in your draft before filing.
                    </p>
                  </div>
                </li>
              </ul>
              <p v-else class="text-sm text-muted-foreground">
                No required sections defined for this motion template.
              </p>
              <div v-if="canUseAi && canEdit" class="border-t border-border pt-4">
                <button
                  type="button"
                  class="bw-btn bw-btn-outline inline-flex items-center gap-2"
                  :disabled="isChecking || !contentHtml.trim()"
                  @click="activeTab = 'ai-tools'; runStructureCheck()"
                >
                  <PhSparkle class="size-4" aria-hidden="true" />
                  Run AI structure check
                </button>
              </div>
            </div>
          </section>
        </div>

        <aside class="space-y-4">
          <section class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Workflow</h2>
                <p class="text-sm text-muted-foreground">
                  Status and filing actions
                </p>
              </div>
            </div>
            <div class="space-y-4 p-5">
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-muted-foreground">Current status</span>
                <StatusBadge :status="motion.status" />
              </div>
              <div v-if="requiredSections.length" class="rounded-lg border border-border bg-surface-muted p-4 text-sm">
                <p class="font-medium text-foreground">{{ requiredSections.length }} required section(s)</p>
                <p class="mt-1 text-muted-foreground">
                  Use AI structure check to verify completeness.
                </p>
              </div>
              <div class="flex flex-col gap-2">
                <button
                  v-for="nextStatus in nextStatusOptions[motion.status] ?? []"
                  :key="nextStatus"
                  type="button"
                  class="bw-btn bw-btn-outline w-full"
                  :disabled="updatingStatus"
                  @click="advanceStatus(nextStatus)"
                >
                  Mark {{ humanize(nextStatus) }}
                </button>
                <button
                  v-if="can('motions.update') && can('filings.create') && ['approved', 'filing_ready'].includes(motion.status) && !motion.court_filing_id"
                  type="button"
                  class="bw-btn bw-btn-action w-full"
                  :disabled="creatingFiling"
                  @click="createFiling"
                >
                  {{ creatingFiling ? 'Creating…' : 'Create court filing' }}
                </button>
                <RouterLink
                  v-if="motion.court_filing_id"
                  to="/filings"
                  class="bw-btn bw-btn-outline w-full text-center"
                >
                  View linked filing
                </RouterLink>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>
