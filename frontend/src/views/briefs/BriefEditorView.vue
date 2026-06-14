<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { PhArrowLeft, PhBooks, PhSparkle } from '@phosphor-icons/vue'
import RichTextEditor from '@/components/editor/RichTextEditor.vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { aiApi, briefsApi, caseNotesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type {
  AiChatResponse,
  AiGovernanceSettings,
  AiResearchAuthoritiesResponse,
  AiStructuredResponse,
  BriefCitation,
  CaseNote,
  LegalBrief,
} from '@/types'

const route = useRoute()
const { can } = usePermissions()

const brief = ref<LegalBrief | null>(null)
const title = ref('')
const briefType = ref('memorandum_of_law')
const jurisdiction = ref('')
const courtType = ref('federal')
const causeOfAction = ref('')
const caseFacts = ref('')
const statutes = ref('')
const desiredOutcome = ref('')
const citationStyle = ref('bluebook')
const briefTypes = ref<string[]>([])
const courtTypes = ref<string[]>([])
const citationStyles = ref<string[]>([])
const contentHtml = ref('')
const citations = ref<BriefCitation[]>([])
const authorityTypes = ref<string[]>(['case', 'statute', 'regulation', 'other'])
const notes = ref<CaseNote[]>([])
const aiSettings = ref<AiGovernanceSettings | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const isOutlining = ref(false)
const isRewriting = ref(false)
const isSummarizing = ref(false)
const isSuggesting = ref(false)
const isAddingCitation = ref(false)
const updatingStatus = ref(false)
const error = ref<string | null>(null)
const issue = ref('')
const outlineIssue = ref('')
const isGenerating = ref(false)
const isBuildingArgs = ref(false)
const isAnalyzingOpposition = ref(false)
const isEnhancing = ref(false)
const isFormatting = ref(false)
const isExporting = ref(false)
const structuredResult = ref<AiStructuredResponse | null>(null)
const enhancementGoal = ref<'strengthen' | 'tone' | 'clarity' | 'dedupe'>('clarity')
const argumentIssue = ref('')
const rewriteInstruction = ref('Improve clarity and legal precision.')
const pendingAi = ref<AiChatResponse | null>(null)
const summaryResult = ref<AiChatResponse | null>(null)
const authoritiesResult = ref<AiResearchAuthoritiesResponse | null>(null)
const newCitation = ref({ authority: 'case', citation_text: '', source_note: '' })
const activeTab = ref<'setup' | 'draft' | 'ai-tools' | 'citations'>('draft')

const briefId = computed(() => Number(route.params.id))
const isReadOnly = computed(() => brief.value?.status === 'final')
const canUseAi = computed(() => can('ai.use'))
const canEdit = computed(() => can('briefs.update') && !isReadOnly.value)

const researchNotes = computed(() =>
  notes.value.filter((note) =>
    ['research_summary', 'strategy_note', 'internal_memo'].includes(note.note_type),
  ),
)

const nextStatusOptions: Record<string, string[]> = {
  draft: ['review'],
  review: ['final', 'draft'],
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const loaded = await briefsApi.get(briefId.value)
    const listMeta = await briefsApi.list({ legal_matter_id: loaded.legal_matter_id })
    const [noteRows, settings] = await Promise.all([
      caseNotesApi.list(loaded.legal_matter_id),
      canUseAi.value ? aiApi.governanceSettings().catch(() => null) : Promise.resolve(null),
    ])
    brief.value = loaded
    title.value = loaded.title
    briefType.value = loaded.brief_type ?? 'memorandum_of_law'
    jurisdiction.value = loaded.jurisdiction ?? ''
    courtType.value = loaded.court_type ?? 'federal'
    causeOfAction.value = loaded.cause_of_action ?? ''
    caseFacts.value = loaded.case_facts ?? ''
    statutes.value = loaded.statutes ?? ''
    desiredOutcome.value = loaded.desired_outcome ?? ''
    citationStyle.value = loaded.citation_style ?? 'bluebook'
    briefTypes.value = listMeta.briefTypes.length ? listMeta.briefTypes : [briefType.value]
    courtTypes.value = listMeta.courtTypes.length ? listMeta.courtTypes : [courtType.value]
    citationStyles.value = listMeta.citationStyles.length ? listMeta.citationStyles : [citationStyle.value]
    contentHtml.value = loaded.content_html ?? ''
    citations.value = loaded.citations ?? []
    notes.value = noteRows
    aiSettings.value = settings
  } catch (err) {
    error.value = formatApiError(err, 'Brief is not available.')
  } finally {
    isLoading.value = false
  }
}

async function save() {
  if (!brief.value || !canEdit.value) return
  isSaving.value = true
  error.value = null
  try {
    const updated = await briefsApi.update(brief.value.id, {
      title: title.value.trim(),
      brief_type: briefType.value,
      jurisdiction: jurisdiction.value.trim() || undefined,
      court_type: courtType.value,
      cause_of_action: causeOfAction.value.trim() || undefined,
      case_facts: caseFacts.value.trim() || undefined,
      statutes: statutes.value.trim() || undefined,
      desired_outcome: desiredOutcome.value.trim() || undefined,
      citation_style: citationStyle.value,
      content_html: contentHtml.value,
      last_ai_governance_log_id: pendingAi.value?.governance_log_id ?? structuredResult.value?.governance_log_id ?? undefined,
    })
    brief.value = updated
    citations.value = updated.citations ?? citations.value
    pendingAi.value = null
  } catch (err) {
    error.value = formatApiError(err, 'We could not save the brief.')
  } finally {
    isSaving.value = false
  }
}

async function generateOutline() {
  if (!brief.value || !canUseAi.value || !canEdit.value) return
  isOutlining.value = true
  error.value = null
  pendingAi.value = null
  try {
    pendingAi.value = await aiApi.briefOutline({
      legal_matter_id: brief.value.legal_matter_id,
      title: title.value.trim() || brief.value.title,
      issue: outlineIssue.value.trim() || undefined,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not generate an outline.')
  } finally {
    isOutlining.value = false
  }
}

function applyOutline() {
  if (!pendingAi.value?.content) return
  contentHtml.value = pendingAi.value.content
}

async function rewriteSection() {
  if (!brief.value || !canUseAi.value || !canEdit.value || !contentHtml.value.trim()) return
  isRewriting.value = true
  error.value = null
  pendingAi.value = null
  try {
    pendingAi.value = await aiApi.briefRewrite({
      legal_matter_id: brief.value.legal_matter_id,
      section_html: contentHtml.value,
      instruction: rewriteInstruction.value.trim() || undefined,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not rewrite the section.')
  } finally {
    isRewriting.value = false
  }
}

function applyRewrite() {
  if (!pendingAi.value?.content) return
  contentHtml.value = pendingAi.value.content
}

async function generateFromFacts() {
  if (!brief.value || !canUseAi.value || !canEdit.value || !caseFacts.value.trim()) return
  isGenerating.value = true
  structuredResult.value = null
  error.value = null
  try {
    structuredResult.value = await aiApi.briefGenerateFromFacts({
      legal_matter_id: brief.value.legal_matter_id,
      legal_brief_id: brief.value.id,
      title: title.value.trim() || brief.value.title,
      brief_type: briefType.value,
      jurisdiction: jurisdiction.value.trim() || undefined,
      court_type: courtType.value,
      cause_of_action: causeOfAction.value.trim() || undefined,
      case_facts: caseFacts.value.trim(),
      statutes: statutes.value.trim() || undefined,
      desired_outcome: desiredOutcome.value.trim() || undefined,
      citation_style: citationStyle.value,
    })
    pendingAi.value = structuredResult.value
  } catch (err) {
    error.value = formatApiError(err, 'We could not generate the brief draft.')
  } finally {
    isGenerating.value = false
  }
}

async function buildArguments() {
  if (!brief.value || !canUseAi.value || !argumentIssue.value.trim()) return
  isBuildingArgs.value = true
  structuredResult.value = null
  try {
    structuredResult.value = await aiApi.briefBuildArguments({
      legal_matter_id: brief.value.legal_matter_id,
      legal_brief_id: brief.value.id,
      issue: argumentIssue.value.trim(),
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not build arguments.')
  } finally {
    isBuildingArgs.value = false
  }
}

async function analyzeOpposition() {
  if (!brief.value || !canUseAi.value) return
  isAnalyzingOpposition.value = true
  structuredResult.value = null
  try {
    structuredResult.value = await aiApi.briefAnalyzeOpposition({
      legal_matter_id: brief.value.legal_matter_id,
      legal_brief_id: brief.value.id,
      content_html: contentHtml.value,
      issue: argumentIssue.value.trim() || undefined,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not analyze opposition.')
  } finally {
    isAnalyzingOpposition.value = false
  }
}

async function enhanceBrief() {
  if (!brief.value || !canUseAi.value || !canEdit.value || !contentHtml.value.trim()) return
  isEnhancing.value = true
  structuredResult.value = null
  try {
    structuredResult.value = await aiApi.briefEnhance({
      legal_matter_id: brief.value.legal_matter_id,
      content_html: contentHtml.value,
      enhancement_goal: enhancementGoal.value,
    })
    pendingAi.value = structuredResult.value
  } catch (err) {
    error.value = formatApiError(err, 'We could not enhance the brief.')
  } finally {
    isEnhancing.value = false
  }
}

async function formatForCourt() {
  if (!brief.value || !canUseAi.value || !canEdit.value) return
  isFormatting.value = true
  structuredResult.value = null
  try {
    structuredResult.value = await aiApi.briefFormatCourt({
      legal_matter_id: brief.value.legal_matter_id,
      legal_brief_id: brief.value.id,
      content_html: contentHtml.value,
      court_type: courtType.value,
      jurisdiction: jurisdiction.value.trim() || undefined,
      citation_style: citationStyle.value,
    })
    pendingAi.value = structuredResult.value
  } catch (err) {
    error.value = formatApiError(err, 'We could not apply court formatting.')
  } finally {
    isFormatting.value = false
  }
}

async function exportBrief(format: 'html' | 'word' | 'pdf' | 'court_filing' | 'google_docs') {
  if (!brief.value) return
  isExporting.value = true
  error.value = null
  try {
    const result = await briefsApi.export(brief.value.id, format)
    if (!(result instanceof Blob)) {
      window.open(result.export_url_hint, '_blank')
      return
    }
    const url = URL.createObjectURL(result)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `${title.value || 'brief'}.${
      format === 'word'
        ? 'docx'
        : format === 'pdf'
          ? 'pdf'
          : format === 'court_filing'
            ? 'html'
            : 'html'
    }`
    anchor.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error.value = formatApiError(err, 'Export failed.')
  } finally {
    isExporting.value = false
  }
}

async function summarizeNotes() {
  if (!brief.value || !canUseAi.value) return
  isSummarizing.value = true
  error.value = null
  summaryResult.value = null
  try {
    summaryResult.value = await aiApi.summarizeResearchNotes({
      legal_matter_id: brief.value.legal_matter_id,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not summarize research notes.')
  } finally {
    isSummarizing.value = false
  }
}

async function suggestAuthorities() {
  if (!brief.value || !canUseAi.value || !issue.value.trim()) return
  isSuggesting.value = true
  error.value = null
  authoritiesResult.value = null
  try {
    authoritiesResult.value = await aiApi.suggestAuthorities({
      legal_matter_id: brief.value.legal_matter_id,
      issue: issue.value.trim(),
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not suggest authorities.')
  } finally {
    isSuggesting.value = false
  }
}

async function addCitationFromAuthority(authority: {
  type: string
  citation: string
  relevance?: string
}) {
  if (!brief.value || !canEdit.value) return
  await addCitation({
    authority: authority.type,
    citation_text: authority.citation,
    source_note: authority.relevance,
  })
}

async function addCitation(payload?: {
  authority: string
  citation_text: string
  source_note?: string
}) {
  if (!brief.value || !canEdit.value) return
  const data = payload ?? {
    authority: newCitation.value.authority,
    citation_text: newCitation.value.citation_text.trim(),
    source_note: newCitation.value.source_note.trim() || undefined,
  }
  if (!data.citation_text) return
  isAddingCitation.value = true
  error.value = null
  try {
    const citation = await briefsApi.addCitation(brief.value.id, data)
    citations.value = [...citations.value, citation]
    newCitation.value = { authority: 'case', citation_text: '', source_note: '' }
  } catch (err) {
    error.value = formatApiError(err, 'We could not add the citation.')
  } finally {
    isAddingCitation.value = false
  }
}

async function removeCitation(citationId: number) {
  if (!brief.value || !canEdit.value) return
  error.value = null
  try {
    await briefsApi.removeCitation(brief.value.id, citationId)
    citations.value = citations.value.filter((c) => c.id !== citationId)
  } catch (err) {
    error.value = formatApiError(err, 'We could not remove the citation.')
  }
}

async function advanceStatus(status: string) {
  if (!brief.value || !can('briefs.update')) return
  updatingStatus.value = true
  error.value = null
  try {
    brief.value = await briefsApi.updateStatus(brief.value.id, status)
  } catch (err) {
    error.value = formatApiError(err, 'We could not update brief status.')
  } finally {
    updatingStatus.value = false
  }
}

watch(briefId, () => {
  void load()
})

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <RouterLink
        v-if="brief?.legal_matter_id"
        :to="`/cases/${brief.legal_matter_id}/briefs`"
        class="bw-btn bw-btn-secondary inline-flex items-center gap-2 text-sm"
      >
        <PhArrowLeft class="size-4" aria-hidden="true" />
        Back to case
      </RouterLink>
      <RouterLink to="/briefs" class="text-sm text-primary hover:underline">
        All briefs
      </RouterLink>
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="6" />

    <template v-else-if="brief">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1 space-y-2">
          <input
            v-model="title"
            type="text"
            class="bw-input w-full border-0 bg-transparent px-0 text-2xl font-semibold tracking-tight shadow-none focus:ring-0"
            :disabled="!canEdit"
            placeholder="Brief title"
          />
          <p v-if="brief.legal_matter" class="text-sm text-muted-foreground">
            {{ brief.legal_matter.title }}
            <span v-if="briefType"> · {{ humanize(briefType) }}</span>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <StatusBadge :status="brief.status" />
          <button
            v-for="nextStatus in nextStatusOptions[brief.status] ?? []"
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
            {{ isSaving ? 'Saving…' : 'Save brief' }}
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
          <nav class="bw-tabs" aria-label="Brief editor sections">
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
              :class="{ 'bw-tab-active': activeTab === 'citations' }"
              @click="activeTab = 'citations'"
            >
              Citations
              <span v-if="citations.length" class="ml-1 text-muted-foreground">({{ citations.length }})</span>
            </button>
          </nav>

          <!-- Case setup -->
          <section v-show="activeTab === 'setup'" class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Case inputs</h2>
                <p class="text-sm text-muted-foreground">
                  Configure brief type, jurisdiction, facts, and desired outcome before generating drafts.
                </p>
              </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
              <label class="text-sm">
                <span class="bw-label">Brief type</span>
                <select v-model="briefType" class="bw-select mt-1.5" :disabled="!canEdit">
                  <option v-for="type in briefTypes" :key="type" :value="type">
                    {{ humanize(type) }}
                  </option>
                </select>
              </label>
              <label class="text-sm">
                <span class="bw-label">Court type</span>
                <select v-model="courtType" class="bw-select mt-1.5" :disabled="!canEdit">
                  <option v-for="type in courtTypes" :key="type" :value="type">
                    {{ humanize(type) }}
                  </option>
                </select>
              </label>
              <label class="text-sm">
                <span class="bw-label">Jurisdiction</span>
                <input v-model="jurisdiction" class="bw-input mt-1.5" :disabled="!canEdit" />
              </label>
              <label class="text-sm">
                <span class="bw-label">Citation style</span>
                <select v-model="citationStyle" class="bw-select mt-1.5" :disabled="!canEdit">
                  <option v-for="style in citationStyles" :key="style" :value="style">
                    {{ humanize(style) }}
                  </option>
                </select>
              </label>
              <label class="text-sm sm:col-span-2">
                <span class="bw-label">Cause of action</span>
                <input v-model="causeOfAction" class="bw-input mt-1.5" :disabled="!canEdit" />
              </label>
              <label class="text-sm sm:col-span-2">
                <span class="bw-label">Case facts</span>
                <textarea v-model="caseFacts" class="bw-textarea mt-1.5 min-h-[120px]" :disabled="!canEdit" />
              </label>
              <label class="text-sm sm:col-span-2">
                <span class="bw-label">Statutes / regulations</span>
                <textarea v-model="statutes" class="bw-textarea mt-1.5 min-h-[88px]" :disabled="!canEdit" />
              </label>
              <label class="text-sm sm:col-span-2">
                <span class="bw-label">Desired outcome</span>
                <textarea v-model="desiredOutcome" class="bw-textarea mt-1.5 min-h-[88px]" :disabled="!canEdit" />
              </label>
              <div class="flex flex-wrap gap-2 border-t border-border pt-4 sm:col-span-2">
                <button
                  v-if="canUseAi && canEdit"
                  type="button"
                  class="bw-btn bw-btn-action inline-flex items-center gap-2"
                  :disabled="isGenerating || !caseFacts.trim()"
                  @click="generateFromFacts"
                >
                  <PhSparkle class="size-4" aria-hidden="true" />
                  {{ isGenerating ? 'Generating…' : 'Generate full brief from facts' }}
                </button>
                <button type="button" class="bw-btn bw-btn-outline" :disabled="isExporting" @click="exportBrief('word')">
                  Export Word
                </button>
                <button type="button" class="bw-btn bw-btn-outline" :disabled="isExporting" @click="exportBrief('pdf')">
                  Export PDF
                </button>
                <button type="button" class="bw-btn bw-btn-outline" :disabled="isExporting" @click="exportBrief('court_filing')">
                  Court filing format
                </button>
                <button type="button" class="bw-btn bw-btn-outline" :disabled="isExporting" @click="exportBrief('google_docs')">
                  Google Docs
                </button>
              </div>
            </div>
          </section>

          <!-- Draft -->
          <section v-show="activeTab === 'draft'" class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Brief content</h2>
                <p class="text-sm text-muted-foreground">
                  Draft and edit the body of your brief. All AI output requires lawyer review.
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
              <RichTextEditor v-if="canEdit" v-model="contentHtml" />
              <div
                v-else
                class="prose prose-sm max-w-none rounded-lg border border-border bg-surface-muted p-4 text-sm"
                v-html="contentHtml || '<p>No content.</p>'"
              />
            </div>
          </section>

          <!-- AI tools -->
          <section v-show="activeTab === 'ai-tools' && canUseAi && canEdit" class="space-y-4">
            <div class="bw-card overflow-hidden">
              <div class="bw-card-header">
                <div>
                  <h2 class="font-semibold text-foreground">Drafting assistants</h2>
                  <p class="text-sm text-muted-foreground">
                    Outline, rewrite, build arguments, analyze opposition, enhance, and apply court formatting.
                  </p>
                </div>
              </div>
              <div class="space-y-4 p-5">
                <label class="block text-sm">
                  <span class="bw-label">Issue for outline (optional)</span>
                  <input
                    v-model="outlineIssue"
                    type="text"
                    class="bw-input mt-1.5"
                    placeholder="Legal issue or argument focus"
                  />
                </label>
                <label class="block text-sm">
                  <span class="bw-label">Issue for arguments / opposition</span>
                  <input
                    v-model="argumentIssue"
                    class="bw-input mt-1.5"
                    placeholder="Issue for argument builder and opposition analyzer"
                  />
                </label>

                <div class="flex flex-wrap gap-2">
                  <button type="button" class="bw-btn bw-btn-action inline-flex items-center gap-2" :disabled="isOutlining" @click="generateOutline">
                    <PhSparkle class="size-4" aria-hidden="true" />
                    {{ isOutlining ? 'Generating…' : 'Generate outline' }}
                  </button>
                  <button type="button" class="bw-btn bw-btn-outline inline-flex items-center gap-2" :disabled="isRewriting || !contentHtml.trim()" @click="rewriteSection">
                    <PhSparkle class="size-4" aria-hidden="true" />
                    {{ isRewriting ? 'Rewriting…' : 'Rewrite section' }}
                  </button>
                  <button type="button" class="bw-btn bw-btn-outline" :disabled="isBuildingArgs || !argumentIssue.trim()" @click="buildArguments">
                    Build arguments
                  </button>
                  <button type="button" class="bw-btn bw-btn-outline" :disabled="isAnalyzingOpposition" @click="analyzeOpposition">
                    Analyze opposition
                  </button>
                </div>

                <div class="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface-muted p-4">
                  <label class="text-sm">
                    <span class="bw-label">Enhancement goal</span>
                    <select v-model="enhancementGoal" class="bw-select mt-1.5">
                      <option value="strengthen">Strengthen arguments</option>
                      <option value="tone">Improve tone</option>
                      <option value="clarity">Improve clarity</option>
                      <option value="dedupe">Remove repetition</option>
                    </select>
                  </label>
                  <button type="button" class="bw-btn bw-btn-outline" :disabled="isEnhancing || !contentHtml.trim()" @click="enhanceBrief">
                    Enhance brief
                  </button>
                  <button type="button" class="bw-btn bw-btn-outline" :disabled="isFormatting" @click="formatForCourt">
                    Apply court formatting
                  </button>
                </div>

                <label class="block text-sm">
                  <span class="bw-label">Rewrite instruction</span>
                  <input v-model="rewriteInstruction" type="text" class="bw-input mt-1.5" />
                </label>
              </div>
            </div>

            <div v-if="pendingAi" class="bw-card space-y-3 p-5">
              <AiOutputBadges :label="pendingAi.label" :requires-review="pendingAi.requires_review" />
              <div class="prose prose-sm max-w-none text-sm" v-html="pendingAi.content" />
              <div class="flex flex-wrap gap-2">
                <button type="button" class="bw-btn bw-btn-action bw-btn-sm" @click="applyOutline">Apply to editor</button>
                <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="pendingAi = null">Dismiss</button>
              </div>
            </div>

            <div v-if="structuredResult?.arguments?.length" class="bw-card space-y-3 p-5">
              <h3 class="font-semibold text-foreground">Argument builder</h3>
              <article v-for="argument in structuredResult.arguments" :key="argument.rank" class="rounded-lg border border-border bg-surface-muted p-4 text-sm">
                <p class="font-medium">#{{ argument.rank }} — {{ argument.title }}</p>
                <p class="mt-1 text-muted-foreground">{{ argument.theory }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Strength: {{ argument.strength }}</p>
              </article>
            </div>

            <div v-if="structuredResult?.opposing_arguments?.length" class="bw-card space-y-3 p-5">
              <h3 class="font-semibold text-foreground">Opposing arguments</h3>
              <article v-for="(row, index) in structuredResult.opposing_arguments" :key="index" class="rounded-lg border border-border bg-surface-muted p-4 text-sm">
                <p>{{ row.argument }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Likelihood: {{ row.likelihood }}</p>
              </article>
            </div>

            <div v-if="structuredResult?.rebuttals?.length" class="bw-card space-y-3 p-5">
              <h3 class="font-semibold text-foreground">Suggested rebuttals</h3>
              <article v-for="(row, index) in structuredResult.rebuttals" :key="index" class="rounded-lg border border-border bg-surface-muted p-4 text-sm">
                <p><strong>Opposition:</strong> {{ row.opposing_argument }}</p>
                <p class="mt-1"><strong>Rebuttal:</strong> {{ row.rebuttal }}</p>
              </article>
            </div>

            <ul v-if="structuredResult?.formatting_notes?.length" class="bw-card list-disc space-y-1 p-5 pl-8 text-sm text-muted-foreground">
              <li v-for="(note, index) in structuredResult.formatting_notes" :key="index">{{ note.note }}</li>
            </ul>
          </section>

          <!-- Citations -->
          <section v-show="activeTab === 'citations'" class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div>
                <h2 class="font-semibold text-foreground">Citations</h2>
                <p class="text-sm text-muted-foreground">
                  Track authorities cited in this brief.
                </p>
              </div>
            </div>
            <div class="space-y-4 p-5">
              <ul v-if="citations.length > 0" class="space-y-3">
                <li
                  v-for="citation in citations"
                  :key="citation.id"
                  class="flex items-start justify-between gap-3 rounded-lg border border-border bg-surface-muted p-4 text-sm"
                >
                  <div>
                    <span class="bw-badge bw-badge-neutral">{{ humanize(citation.authority) }}</span>
                    <p class="mt-2 font-medium">{{ citation.citation_text }}</p>
                    <p v-if="citation.source_note" class="mt-1 text-muted-foreground">
                      {{ citation.source_note }}
                    </p>
                  </div>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="bw-btn bw-btn-ghost bw-btn-sm text-destructive"
                    @click="removeCitation(citation.id)"
                  >
                    Remove
                  </button>
                </li>
              </ul>
              <p v-else class="text-sm text-muted-foreground">No citations added yet.</p>

              <form
                v-if="canEdit"
                class="grid gap-4 border-t border-border pt-4 sm:grid-cols-2"
                @submit.prevent="addCitation()"
              >
                <label class="text-sm sm:col-span-1">
                  <span class="bw-label">Authority type</span>
                  <select v-model="newCitation.authority" class="bw-select mt-1.5">
                    <option v-for="type in authorityTypes" :key="type" :value="type">
                      {{ humanize(type) }}
                    </option>
                  </select>
                </label>
                <label class="text-sm sm:col-span-2">
                  <span class="bw-label">Citation text</span>
                  <input
                    v-model="newCitation.citation_text"
                    type="text"
                    class="bw-input mt-1.5"
                    placeholder="Case name, statute section, regulation…"
                    required
                  />
                </label>
                <label class="text-sm sm:col-span-2">
                  <span class="bw-label">Source note (optional)</span>
                  <input v-model="newCitation.source_note" type="text" class="bw-input mt-1.5" />
                </label>
                <button
                  type="submit"
                  class="bw-btn bw-btn-outline sm:col-span-2"
                  :disabled="isAddingCitation"
                >
                  Add citation
                </button>
              </form>
            </div>
          </section>
        </div>

        <aside class="space-y-4">
          <section class="bw-card overflow-hidden">
            <div class="bw-card-header">
              <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary-muted text-primary">
                  <PhBooks class="size-4" weight="fill" aria-hidden="true" />
                </span>
                <div>
                  <h2 class="font-semibold text-foreground">Research panel</h2>
                  <p class="text-sm text-muted-foreground">
                    {{ researchNotes.length }} research note(s) on this matter
                  </p>
                </div>
              </div>
            </div>
            <div class="space-y-4 p-5">
              <div v-if="canUseAi" class="space-y-4">
                <button
                  type="button"
                  class="bw-btn bw-btn-outline w-full"
                  :disabled="researchNotes.length === 0 || isSummarizing"
                  @click="summarizeNotes"
                >
                  {{ isSummarizing ? 'Summarizing…' : 'Summarize research notes' }}
                </button>

                <div
                  v-if="summaryResult"
                  class="rounded-lg border border-border bg-surface-muted p-4 text-sm"
                >
                  <AiOutputBadges
                    :label="summaryResult.label"
                    :requires-review="summaryResult.requires_review"
                  />
                  <p class="mt-2 whitespace-pre-wrap">{{ summaryResult.content }}</p>
                </div>

                <label class="block text-sm">
                  <span class="bw-label">Legal issue</span>
                  <textarea
                    v-model="issue"
                    class="bw-textarea mt-1.5 min-h-[88px]"
                    placeholder="Issue for authority suggestions"
                  />
                </label>

                <button
                  type="button"
                  class="bw-btn bw-btn-outline w-full"
                  :disabled="!issue.trim() || isSuggesting"
                  @click="suggestAuthorities"
                >
                  {{ isSuggesting ? 'Suggesting…' : 'Suggest authorities' }}
                </button>

                <div v-if="authoritiesResult?.authorities?.length" class="space-y-3">
                  <p
                    v-if="authoritiesResult.verification_warning"
                    class="rounded-md border border-status-warning-border bg-status-warning px-3 py-2 text-xs"
                  >
                    {{ authoritiesResult.verification_warning }}
                  </p>
                  <article
                    v-for="(authority, index) in authoritiesResult.authorities"
                    :key="`${authority.citation}-${index}`"
                    class="rounded-lg border border-border bg-surface-muted p-3 text-sm"
                  >
                    <span class="bw-badge bw-badge-neutral">{{ authority.type }}</span>
                    <p class="mt-2 font-medium">{{ authority.citation }}</p>
                    <p v-if="authority.relevance" class="mt-1 text-muted-foreground">
                      {{ authority.relevance }}
                    </p>
                    <button
                      v-if="canEdit"
                      type="button"
                      class="mt-2 text-xs font-medium text-primary hover:underline"
                      @click="addCitationFromAuthority(authority)"
                    >
                      Add as citation
                    </button>
                  </article>
                </div>
              </div>
              <p v-else class="text-sm text-muted-foreground">
                AI research tools require the ai.use permission.
              </p>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>
