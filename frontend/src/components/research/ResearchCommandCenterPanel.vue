<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhArrowSquareOut, PhPlus, PhSparkle } from '@phosphor-icons/vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import BwModal from '@/components/common/BwModal.vue'
import { usePermissions } from '@/composables/usePermissions'
import { aiApi, researchProjectsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type {
  AiGovernanceSettings,
  AiStructuredResponse,
  ResearchChatMessage,
  ResearchProject,
} from '@/types'

const props = defineProps<{
  legalMatterId?: number
}>()

const { can } = usePermissions()

const projects = ref<ResearchProject[]>([])
const selectedProjectId = ref<number | null>(null)
const chatMessages = ref<ResearchChatMessage[]>([])
const aiSettings = ref<AiGovernanceSettings | null>(null)
const isLoading = ref(true)
const isBusy = ref(false)
const error = ref<string | null>(null)

const nlQuery = ref('')
const caseIssue = ref('')
const memoIssue = ref('')
const statuteText = ref('')
const strategyIssue = ref('')
const chatInput = ref('')
const jurisdiction = ref('')
const courtType = ref('federal')
const transferBriefId = ref('')

const queryResult = ref<AiStructuredResponse | null>(null)
const casesResult = ref<AiStructuredResponse | null>(null)
const memoResult = ref<AiStructuredResponse | null>(null)
const statuteResult = ref<AiStructuredResponse | null>(null)
const strategyResult = ref<AiStructuredResponse | null>(null)
const transferBrief = ref<{ id: number; title: string } | null>(null)

const newProjectName = ref('')
const showCreateProject = ref(false)
const activeToolTab = ref<'query' | 'analysis' | 'collaborate'>('query')

function openCreateProject() {
  newProjectName.value = ''
  showCreateProject.value = true
}

function closeCreateProject() {
  showCreateProject.value = false
  newProjectName.value = ''
}

const canUseAi = computed(() => can('ai.use'))
const canCreate = computed(() => can('research.create'))
const selectedProject = computed(() =>
  projects.value.find((project) => project.id === selectedProjectId.value) ?? null,
)

const courtTypes = ['federal', 'state', 'immigration', 'admin', 'appellate']

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const tasks: Promise<unknown>[] = [
      researchProjectsApi.list(props.legalMatterId ? { legal_matter_id: props.legalMatterId } : {}),
    ]
    if (canUseAi.value) {
      tasks.push(aiApi.governanceSettings().catch(() => null))
    }
    const results = await Promise.all(tasks)
    projects.value = results[0] as ResearchProject[]
    if (canUseAi.value) {
      aiSettings.value = (results[1] as AiGovernanceSettings | null) ?? null
    }
    if (!selectedProjectId.value && projects.value.length > 0) {
      selectedProjectId.value = projects.value[0]?.id ?? null
      await loadMessages()
    }
  } catch (err) {
    error.value = formatApiError(err, 'Research command center is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function loadMessages() {
  if (!selectedProjectId.value) {
    chatMessages.value = []
    return
  }
  chatMessages.value = await researchProjectsApi.messages(selectedProjectId.value)
}

async function createProject() {
  if (!canCreate.value || !newProjectName.value.trim()) return
  isBusy.value = true
  error.value = null
  try {
    const project = await researchProjectsApi.create({
      name: newProjectName.value.trim(),
      legal_matter_id: props.legalMatterId,
      jurisdiction: jurisdiction.value.trim() || undefined,
    })
    projects.value = [project, ...projects.value]
    selectedProjectId.value = project.id
    newProjectName.value = ''
    showCreateProject.value = false
    chatMessages.value = []
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the research project.')
  } finally {
    isBusy.value = false
  }
}

async function runQuery() {
  if (!canUseAi.value || !nlQuery.value.trim()) return
  isBusy.value = true
  error.value = null
  queryResult.value = null
  try {
    queryResult.value = await aiApi.researchQuery({
      query: nlQuery.value.trim(),
      legal_matter_id: props.legalMatterId,
      jurisdiction: jurisdiction.value.trim() || undefined,
      court_type: courtType.value,
    })
  } catch (err) {
    error.value = formatApiError(err, 'Research query failed.')
  } finally {
    isBusy.value = false
  }
}

async function searchCases() {
  if (!canUseAi.value || !caseIssue.value.trim()) return
  isBusy.value = true
  casesResult.value = null
  try {
    casesResult.value = await aiApi.researchSearchCases({
      issue: caseIssue.value.trim(),
      legal_matter_id: props.legalMatterId,
      jurisdiction: jurisdiction.value.trim() || undefined,
      court_type: courtType.value,
    })
  } catch (err) {
    error.value = formatApiError(err, 'Case search failed.')
  } finally {
    isBusy.value = false
  }
}

async function generateMemo() {
  if (!canUseAi.value || !memoIssue.value.trim()) return
  isBusy.value = true
  memoResult.value = null
  try {
    memoResult.value = await aiApi.researchGenerateMemo({
      issue: memoIssue.value.trim(),
      legal_matter_id: props.legalMatterId,
      research_project_id: selectedProjectId.value ?? undefined,
      memo_type: 'research_memo',
    })
  } catch (err) {
    error.value = formatApiError(err, 'Memo generation failed.')
  } finally {
    isBusy.value = false
  }
}

async function analyzeStatute() {
  if (!canUseAi.value || !statuteText.value.trim()) return
  isBusy.value = true
  statuteResult.value = null
  try {
    statuteResult.value = await aiApi.researchAnalyzeStatute({
      statute_text: statuteText.value.trim(),
      jurisdiction: jurisdiction.value.trim() || undefined,
    })
  } catch (err) {
    error.value = formatApiError(err, 'Statute analysis failed.')
  } finally {
    isBusy.value = false
  }
}

async function buildStrategy() {
  if (!canUseAi.value || !strategyIssue.value.trim() || !props.legalMatterId) return
  isBusy.value = true
  strategyResult.value = null
  try {
    strategyResult.value = await aiApi.researchStrategy({
      issue: strategyIssue.value.trim(),
      legal_matter_id: props.legalMatterId,
    })
  } catch (err) {
    error.value = formatApiError(err, 'Strategy assistant failed.')
  } finally {
    isBusy.value = false
  }
}

async function sendChat() {
  if (!canUseAi.value || !selectedProjectId.value || !chatInput.value.trim()) return
  isBusy.value = true
  error.value = null
  try {
    await aiApi.researchChat({
      research_project_id: selectedProjectId.value,
      message: chatInput.value.trim(),
    })
    chatInput.value = ''
    await loadMessages()
  } catch (err) {
    error.value = formatApiError(err, 'Research chat failed.')
  } finally {
    isBusy.value = false
  }
}

async function transferToBrief() {
  if (!selectedProjectId.value || !props.legalMatterId) return
  isBusy.value = true
  error.value = null
  try {
    const brief = await researchProjectsApi.transferToBrief(selectedProjectId.value, {
      legal_matter_id: props.legalMatterId,
      legal_brief_id: transferBriefId.value ? Number(transferBriefId.value) : undefined,
      content_html: memoResult.value?.content ? `<div>${memoResult.value.content}</div>` : undefined,
      append: true,
    })
    transferBrief.value = { id: brief.id, title: brief.title }
  } catch (err) {
    error.value = formatApiError(err, 'Transfer to brief failed.')
  } finally {
    isBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <AiDisclaimerBanner
      v-if="canUseAi && aiSettings?.disclaimer"
      :disclaimer="aiSettings.disclaimer"
    />

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <section class="bw-card overflow-hidden">
      <div class="bw-card-header">
        <div>
          <h2 class="font-semibold text-foreground">Research workspace</h2>
          <p class="text-sm text-muted-foreground">
            Save projects, chat through theories, and transfer findings to Brief Writer.
          </p>
        </div>
        <button
          v-if="canCreate"
          type="button"
          class="bw-btn bw-btn-accent bw-btn-sm"
          @click="openCreateProject"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          New project
        </button>
      </div>

      <div class="space-y-4 px-6 py-4">
        <div class="grid gap-3 sm:grid-cols-3">
          <label class="text-sm sm:col-span-2">
            <span class="bw-label">Active project</span>
            <select
              v-model="selectedProjectId"
              class="bw-select mt-1.5 w-full"
              @change="loadMessages"
            >
              <option :value="null">Select project…</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">
                {{ project.name }}
              </option>
            </select>
          </label>
          <label class="text-sm">
            <span class="bw-label">Jurisdiction</span>
            <input v-model="jurisdiction" class="bw-input mt-1.5 w-full" placeholder="e.g. 9th Cir." />
          </label>
          <label class="text-sm">
            <span class="bw-label">Court type</span>
            <select v-model="courtType" class="bw-select mt-1.5 w-full">
              <option v-for="type in courtTypes" :key="type" :value="type">
                {{ humanize(type) }}
              </option>
            </select>
          </label>
        </div>
      </div>
    </section>

    <nav class="bw-tabs" aria-label="Research tools">
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeToolTab === 'query' }"
        @click="activeToolTab = 'query'"
      >
        Query & search
      </button>
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeToolTab === 'analysis' }"
        @click="activeToolTab = 'analysis'"
      >
        Memos & analysis
      </button>
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeToolTab === 'collaborate' }"
        @click="activeToolTab = 'collaborate'"
      >
        Chat & transfer
      </button>
    </nav>

    <div v-show="activeToolTab === 'query'" class="grid gap-6 xl:grid-cols-2">
      <section class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <h3 class="font-semibold text-foreground">Natural language research</h3>
        </div>
        <div class="space-y-4 p-5">
        <label class="block text-sm">
          <span class="bw-label">Research question</span>
          <textarea
            v-model="nlQuery"
            class="bw-textarea mt-1.5 min-h-[88px] w-full"
            placeholder="Ask a legal research question in plain English…"
          />
        </label>
        <button
          type="button"
          class="bw-btn bw-btn-action inline-flex items-center gap-2"
          :disabled="!canUseAi || isBusy || !nlQuery.trim()"
          @click="runQuery"
        >
          <PhSparkle class="size-4" aria-hidden="true" />
          Ask research assistant
        </button>
        <div v-if="queryResult" class="space-y-3 rounded-lg border border-border bg-surface p-3 text-sm">
          <AiOutputBadges :label="queryResult.label" :requires-review="queryResult.requires_review" />
          <p class="whitespace-pre-wrap">{{ queryResult.content }}</p>
          <p v-if="queryResult.verification_warning" class="text-xs text-status-warning">
            {{ queryResult.verification_warning }}
          </p>
          <article
            v-for="(authority, index) in queryResult.ranked_authorities ?? queryResult.authorities ?? []"
            :key="`${authority.citation}-${index}`"
            class="rounded-md border border-border p-2"
          >
            <p class="font-medium">{{ authority.citation }}</p>
            <p v-if="authority.relevance" class="text-muted-foreground">{{ authority.relevance }}</p>
            <p class="text-xs text-muted-foreground">
              Confidence: {{ authority.confidence ?? '—' }} ·
              {{ authority.verification_status ?? 'unverified' }}
            </p>
          </article>
        </div>
        </div>
      </section>

      <section class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <h3 class="font-semibold text-foreground">Case law search</h3>
        </div>
        <div class="space-y-4 p-5">
          <label class="block text-sm">
            <span class="bw-label">Legal issue</span>
            <textarea
              v-model="caseIssue"
              class="bw-textarea mt-1.5 min-h-[88px] w-full"
              placeholder="Describe the issue for case law matching…"
            />
          </label>
          <button
            type="button"
            class="bw-btn bw-btn-outline"
            :disabled="!canUseAi || isBusy || !caseIssue.trim()"
            @click="searchCases"
          >
            Search cases
          </button>
          <div v-if="casesResult?.cases?.length" class="space-y-2 text-sm">
            <article
              v-for="(caseRow, index) in casesResult.cases"
              :key="`${caseRow.citation}-${index}`"
              class="rounded-md border border-border bg-surface-muted p-3"
            >
              <p class="font-medium">{{ caseRow.citation }}</p>
              <p v-if="caseRow.holding" class="mt-1">{{ caseRow.holding }}</p>
              <p class="text-xs text-muted-foreground">
                Similarity: {{ caseRow.similarity_score ?? '—' }} ·
                {{ caseRow.verification_status ?? 'unverified' }}
              </p>
            </article>
          </div>
        </div>
      </section>
    </div>

    <div v-show="activeToolTab === 'analysis'" class="grid gap-6 xl:grid-cols-2">
      <section class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <h3 class="font-semibold text-foreground">Research memorandum</h3>
        </div>
        <div class="space-y-4 p-5">
          <label class="block text-sm">
            <span class="bw-label">Issue for memo</span>
            <textarea
              v-model="memoIssue"
              class="bw-textarea mt-1.5 min-h-[72px] w-full"
              placeholder="Issue for memo generation…"
            />
          </label>
          <button
            type="button"
            class="bw-btn bw-btn-outline"
            :disabled="!canUseAi || isBusy || !memoIssue.trim()"
            @click="generateMemo"
          >
            Generate memo
          </button>
          <div v-if="memoResult?.memo_sections?.length" class="space-y-2 text-sm">
            <article
              v-for="section in memoResult.memo_sections"
              :key="section.title"
              class="rounded-md border border-border bg-surface-muted p-3"
            >
              <h4 class="font-medium">{{ section.title }}</h4>
              <p class="mt-1 whitespace-pre-wrap">{{ section.content }}</p>
            </article>
          </div>
        </div>
      </section>

      <section class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <h3 class="font-semibold text-foreground">Statute analyzer</h3>
        </div>
        <div class="space-y-4 p-5">
          <label class="block text-sm">
            <span class="bw-label">Statute text</span>
            <textarea
              v-model="statuteText"
              class="bw-textarea mt-1.5 min-h-[88px] w-full"
              placeholder="Paste statute or regulation text…"
            />
          </label>
          <button
            type="button"
            class="bw-btn bw-btn-outline"
            :disabled="!canUseAi || isBusy || !statuteText.trim()"
            @click="analyzeStatute"
          >
            Analyze statute
          </button>
          <div v-if="statuteResult?.statute_analysis?.length" class="space-y-2 text-sm">
            <article
              v-for="(row, index) in statuteResult.statute_analysis"
              :key="`${row.provision ?? index}`"
              class="rounded-md border border-border bg-surface-muted p-3"
            >
              <h4 class="font-medium">{{ row.provision ?? 'Analysis' }}</h4>
              <p class="mt-1">{{ row.plain_english ?? row.comparison }}</p>
            </article>
          </div>
        </div>
      </section>

      <section class="bw-card overflow-hidden xl:col-span-2">
        <div class="bw-card-header">
          <h3 class="font-semibold text-foreground">Litigation strategy assistant</h3>
        </div>
        <div class="space-y-4 p-5">
          <label class="block text-sm">
            <span class="bw-label">Dispute or procedural posture</span>
            <textarea
              v-model="strategyIssue"
              class="bw-textarea mt-1.5 min-h-[72px] w-full"
              placeholder="Describe the dispute or procedural posture…"
            />
          </label>
          <button
            type="button"
            class="bw-btn bw-btn-outline"
            :disabled="!canUseAi || isBusy || !strategyIssue.trim() || !legalMatterId"
            @click="buildStrategy"
          >
            Build strategy
          </button>
          <div v-if="strategyResult?.strategy" class="space-y-2 text-sm">
            <p><strong>Claims:</strong> {{ strategyResult.strategy.claims?.join('; ') }}</p>
            <p><strong>Defenses:</strong> {{ strategyResult.strategy.defenses?.join('; ') }}</p>
            <p>
              <strong>Procedural options:</strong>
              {{ strategyResult.strategy.procedural_options?.join('; ') }}
            </p>
          </div>
        </div>
      </section>
    </div>

    <div v-show="activeToolTab === 'collaborate'" class="space-y-6">
      <section class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <h3 class="font-semibold text-foreground">AI research chat</h3>
        </div>
        <div class="space-y-4 p-5">
          <div class="max-h-56 space-y-2 overflow-y-auto rounded-md border border-border bg-surface-muted p-3 text-sm">
            <p v-if="chatMessages.length === 0" class="text-muted-foreground">
              Select a project and start a research conversation.
            </p>
            <article
              v-for="message in chatMessages"
              :key="message.id"
              class="rounded-md px-2 py-1"
              :class="message.role === 'user' ? 'bg-primary/10' : 'bg-surface'"
            >
              <p class="text-xs font-medium uppercase text-muted-foreground">{{ message.role }}</p>
              <p class="whitespace-pre-wrap">{{ message.content }}</p>
            </article>
          </div>
          <form class="flex gap-2" @submit.prevent="sendChat">
            <input
              v-model="chatInput"
              class="bw-input flex-1"
              placeholder="Follow up, test a theory, compare cases…"
            />
            <button type="submit" class="bw-btn bw-btn-action" :disabled="!canUseAi || isBusy || !chatInput.trim()">
              Send
            </button>
          </form>
        </div>
      </section>

      <section v-if="legalMatterId && selectedProjectId" class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <div>
            <h3 class="font-semibold text-foreground">Research-to-brief integration</h3>
            <p class="text-sm text-muted-foreground">
              Transfer this project's research (and latest memo output) into AI Brief Writer.
            </p>
          </div>
        </div>
        <div class="flex flex-wrap gap-3 p-5">
          <label class="text-sm">
            <span class="bw-label">Existing brief ID (optional)</span>
            <input
              v-model="transferBriefId"
              class="bw-input mt-1.5 w-48"
              placeholder="Brief ID"
            />
          </label>
          <div class="flex flex-wrap items-end gap-3">
            <button
              type="button"
              class="bw-btn bw-btn-action"
              :disabled="isBusy"
              @click="transferToBrief"
            >
              Transfer to brief
            </button>
            <RouterLink
              v-if="transferBrief"
              :to="`/briefs/${transferBrief.id}`"
              class="bw-btn bw-btn-outline inline-flex items-center gap-2"
            >
              Open {{ transferBrief.title }}
              <PhArrowSquareOut class="size-4" aria-hidden="true" />
            </RouterLink>
          </div>
        </div>
      </section>
    </div>

    <BwModal
      :open="showCreateProject && canCreate"
      title="New research project"
      size="sm"
      @close="closeCreateProject"
    >
      <form id="research-project-form" @submit.prevent="createProject">
        <label class="bw-label" for="project-name">Project name</label>
        <input id="project-name" v-model="newProjectName" class="bw-input" placeholder="Trial prep research" required />
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeCreateProject">Cancel</button>
        <button
          type="submit"
          form="research-project-form"
          class="bw-btn bw-btn-action"
          :disabled="isBusy || !newProjectName.trim()"
        >
          {{ isBusy ? 'Creating…' : 'Create project' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
