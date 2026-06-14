<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhBookmarkSimple, PhBooks, PhMagnifyingGlass, PhPlus, PhSparkle } from '@phosphor-icons/vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import AiOutputBadges from '@/components/ai/AiOutputBadges.vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { usePermissions } from '@/composables/usePermissions'
import {
  aiApi,
  caseNotesApi,
  researchEntriesApi,
  researchFoldersApi,
  researchSavedItemsApi,
} from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type {
  AiGovernanceSettings,
  AiResearchAuthoritiesResponse,
  AiChatResponse,
  CaseNote,
  LegalResearchEntry,
  ResearchFolder,
  ResearchSavedItem,
} from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()

const notes = ref<CaseNote[]>([])
const entries = ref<LegalResearchEntry[]>([])
const folders = ref<ResearchFolder[]>([])
const savedItems = ref<ResearchSavedItem[]>([])
const documentTypes = ref<string[]>([])
const aiSettings = ref<AiGovernanceSettings | null>(null)
const isLoading = ref(true)
const isSearching = ref(false)
const isSummarizing = ref(false)
const isSuggesting = ref(false)
const isSavingFolder = ref(false)
const error = ref<string | null>(null)
const issue = ref('')
const keyword = ref('')
const jurisdiction = ref('')
const typeFilter = ref('')
const summaryResult = ref<AiChatResponse | null>(null)
const authoritiesResult = ref<AiResearchAuthoritiesResponse | null>(null)
const showFolderModal = ref(false)
const folderName = ref('')

const canUseAi = computed(() => can('ai.use'))
const canResearch = computed(() => can('research.view'))
const canCreateResearch = computed(() => can('research.create'))

const researchNotes = computed(() =>
  notes.value.filter((note) =>
    ['research_summary', 'strategy_note', 'internal_memo'].includes(note.note_type),
  ),
)

const hasResearchNotes = computed(() => researchNotes.value.length > 0)

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const tasks: Promise<unknown>[] = [caseNotesApi.list(props.caseId)]
    if (canUseAi.value) {
      tasks.push(aiApi.governanceSettings().catch(() => null))
    }
    if (canResearch.value) {
      tasks.push(
        researchEntriesApi.list({}),
        researchFoldersApi.list({ legal_matter_id: props.caseId }),
        researchSavedItemsApi.list({ legal_matter_id: props.caseId }),
      )
    }

    const results = await Promise.all(tasks)
    notes.value = results[0] as CaseNote[]

    let index = 1
    if (canUseAi.value) {
      aiSettings.value = (results[index] as AiGovernanceSettings | null) ?? null
      index++
    }
    if (canResearch.value) {
      const entryResult = results[index] as { entries: LegalResearchEntry[]; documentTypes: string[] }
      folders.value = results[index + 1] as ResearchFolder[]
      savedItems.value = results[index + 2] as ResearchSavedItem[]
      entries.value = entryResult.entries
      documentTypes.value = entryResult.documentTypes
    }
  } catch (err) {
    error.value = formatApiError(err, 'Research assistant is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function searchLibrary() {
  if (!canResearch.value) return
  isSearching.value = true
  error.value = null
  try {
    const filters: {
      keyword?: string
      jurisdiction?: string
      document_type?: string
    } = {}
    if (keyword.value.trim()) filters.keyword = keyword.value.trim()
    if (jurisdiction.value) filters.jurisdiction = jurisdiction.value
    if (typeFilter.value) filters.document_type = typeFilter.value
    const result = await researchEntriesApi.list(filters)
    entries.value = result.entries
    documentTypes.value = result.documentTypes
  } catch (err) {
    error.value = formatApiError(err, 'We could not search the research library.')
  } finally {
    isSearching.value = false
  }
}

function openFolderModal() {
  folderName.value = ''
  showFolderModal.value = true
}

function closeFolderModal() {
  showFolderModal.value = false
  folderName.value = ''
}

async function createCaseFolder() {
  if (!canCreateResearch.value || !folderName.value.trim()) return
  isSavingFolder.value = true
  error.value = null
  try {
    await researchFoldersApi.create({
      name: folderName.value.trim(),
      legal_matter_id: props.caseId,
    })
    closeFolderModal()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the research folder.')
  } finally {
    isSavingFolder.value = false
  }
}

async function saveEntryToFolder(entry: LegalResearchEntry, folder: ResearchFolder) {
  if (!canCreateResearch.value) return
  error.value = null
  try {
    await researchFoldersApi.saveItem(folder.id, {
      legal_research_entry_id: entry.id,
      legal_matter_id: props.caseId,
    })
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this entry.')
  }
}

async function removeSavedItem(item: ResearchSavedItem) {
  if (!can('research.delete')) return
  error.value = null
  try {
    await researchSavedItemsApi.remove(item.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not remove the saved item.')
  }
}

async function summarizeNotes() {
  if (!canUseAi.value) return
  isSummarizing.value = true
  error.value = null
  summaryResult.value = null
  try {
    summaryResult.value = await aiApi.summarizeResearchNotes({
      legal_matter_id: props.caseId,
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not summarize research notes.')
  } finally {
    isSummarizing.value = false
  }
}

async function suggestAuthorities() {
  if (!canUseAi.value || !issue.value.trim()) return
  isSuggesting.value = true
  error.value = null
  authoritiesResult.value = null
  try {
    authoritiesResult.value = await aiApi.suggestAuthorities({
      legal_matter_id: props.caseId,
      issue: issue.value.trim(),
    })
  } catch (err) {
    error.value = formatApiError(err, 'We could not suggest authorities.')
  } finally {
    isSuggesting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6">
    <Skeleton v-if="isLoading" variant="panel" />

    <template v-else>
      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

      <section v-if="canResearch" class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <div class="flex items-center gap-2">
            <PhBooks class="h-5 w-5 text-primary-700" aria-hidden="true" />
            <div>
              <h2 class="font-semibold text-foreground">Research library</h2>
              <p class="text-sm text-muted-foreground">Search authorities and save them to case folders.</p>
            </div>
          </div>
          <RouterLink :to="{ name: 'research' }" class="bw-btn bw-btn-outline bw-btn-sm">
            Full workspace
          </RouterLink>
        </div>

        <div class="space-y-4 px-6 py-4">
          <div class="grid gap-3 sm:grid-cols-3">
            <div class="relative sm:col-span-1">
              <PhMagnifyingGlass
                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
              />
              <input
                v-model="keyword"
                type="search"
                class="bw-input pl-9"
                placeholder="Keyword or citation"
                @keyup.enter="searchLibrary"
              />
            </div>
            <input
              v-model="jurisdiction"
              class="bw-input"
              placeholder="Jurisdiction"
              @keyup.enter="searchLibrary"
            />
            <select v-model="typeFilter" class="bw-select" @change="searchLibrary">
              <option value="">All types</option>
              <option v-for="type in documentTypes" :key="type" :value="type">
                {{ humanize(type) }}
              </option>
            </select>
          </div>

          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="isSearching"
            @click="searchLibrary"
          >
            {{ isSearching ? 'Searching…' : 'Search library' }}
          </button>

          <div v-if="entries.length" class="divide-y divide-border rounded-lg border border-border">
            <article
              v-for="entry in entries.slice(0, 5)"
              :key="entry.id"
              class="px-4 py-3"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <span class="bw-badge bw-badge-neutral">{{ humanize(entry.document_type) }}</span>
                  <h3 class="mt-2 text-sm font-medium text-foreground">{{ entry.title }}</h3>
                  <p v-if="entry.citation" class="mt-1 text-xs text-muted-foreground">
                    {{ entry.citation }}
                  </p>
                </div>
                <select
                  v-if="canCreateResearch && folders.length"
                  class="bw-select text-sm"
                  @change="(e) => {
                    const folderId = Number((e.target as HTMLSelectElement).value)
                    const folder = folders.find((row) => row.id === folderId)
                    if (folder) void saveEntryToFolder(entry, folder)
                    ;(e.target as HTMLSelectElement).value = ''
                  }"
                >
                  <option value="">Save to folder</option>
                  <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                    {{ folder.name }}
                  </option>
                </select>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section v-if="canResearch" class="bw-card overflow-hidden">
        <div class="bw-card-header">
          <div class="flex items-center gap-2">
            <PhBookmarkSimple class="h-5 w-5 text-primary-700" aria-hidden="true" />
            <h2 class="font-semibold text-foreground">Saved research for this case</h2>
          </div>
          <button
            v-if="canCreateResearch"
            type="button"
            class="bw-btn bw-btn-accent bw-btn-sm"
            @click="openFolderModal"
          >
            <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
            New folder
          </button>
        </div>

        <div class="px-6 py-4">
          <div v-if="folders.length" class="mb-4 flex flex-wrap gap-2">
            <span
              v-for="folder in folders"
              :key="folder.id"
              class="bw-badge bw-badge-neutral"
            >
              {{ folder.name }} ({{ folder.items_count ?? 0 }})
            </span>
          </div>

          <EmptyState
            v-if="savedItems.length === 0"
            title="No saved research yet"
            description="Search the library and save authorities to a case folder."
          />

          <ul v-else class="divide-y divide-border rounded-lg border border-border">
            <li v-for="item in savedItems" :key="item.id" class="px-4 py-3">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-medium text-foreground">{{ item.entry?.title }}</p>
                  <p v-if="item.entry?.citation" class="mt-1 text-xs text-muted-foreground">
                    {{ item.entry.citation }}
                  </p>
                  <p v-if="item.folder?.name" class="mt-1 text-xs text-muted-foreground">
                    Folder: {{ item.folder.name }}
                  </p>
                </div>
                <button
                  v-if="can('research.delete')"
                  type="button"
                  class="text-xs font-medium text-destructive hover:underline"
                  @click="removeSavedItem(item)"
                >
                  Remove
                </button>
              </div>
            </li>
          </ul>
        </div>
      </section>

      <BwModal
        :open="showFolderModal && canCreateResearch"
        title="New research folder"
        size="sm"
        @close="closeFolderModal"
      >
        <form id="research-folder-form" @submit.prevent="createCaseFolder">
          <label class="bw-label" for="folder-name">Folder name</label>
          <input id="folder-name" v-model="folderName" class="bw-input" placeholder="Trial prep" required />
        </form>
        <template #footer>
          <button type="button" class="bw-btn bw-btn-outline" @click="closeFolderModal">
            Cancel
          </button>
          <button
            type="submit"
            form="research-folder-form"
            class="bw-btn bw-btn-action"
            :disabled="isSavingFolder || !folderName.trim()"
          >
            {{ isSavingFolder ? 'Saving…' : 'Create folder' }}
          </button>
        </template>
      </BwModal>

      <div v-if="!canUseAi" class="bw-card p-6">
        <EmptyState
          title="AI research assistant unavailable"
          description="You need the ai.use permission to summarize notes and suggest authorities."
        />
      </div>

      <template v-else>
        <AiDisclaimerBanner v-if="aiSettings?.disclaimer" :disclaimer="aiSettings.disclaimer" />

        <div class="grid gap-6 lg:grid-cols-2">
          <section class="bw-card p-6">
            <div class="mb-4 flex items-center gap-2">
              <PhBooks class="h-5 w-5 text-primary-700" aria-hidden="true" />
              <h2 class="font-semibold text-foreground">Summarize research notes</h2>
            </div>
            <p class="mb-4 text-sm text-muted-foreground">
              AI summarizes saved research, strategy, and internal memo notes on this matter.
            </p>
            <p v-if="!hasResearchNotes" class="mb-4 text-sm text-muted-foreground">
              Add notes with type Research summary, Strategy note, or Internal memo to enable
              summarization.
            </p>
            <p v-else class="mb-4 text-sm text-muted-foreground">
              {{ researchNotes.length }} research note(s) available.
            </p>
            <button
              type="button"
              class="bw-btn bw-btn-action inline-flex items-center gap-2"
              :disabled="!hasResearchNotes || isSummarizing"
              @click="summarizeNotes"
            >
              <PhSparkle class="h-4 w-4" aria-hidden="true" />
              {{ isSummarizing ? 'Summarizing…' : 'Summarize notes' }}
            </button>
            <div
              v-if="summaryResult"
              class="mt-6 space-y-3 rounded-lg border border-border bg-surface p-4"
            >
              <AiOutputBadges
                :label="summaryResult.label"
                :requires-review="summaryResult.requires_review"
              />
              <p class="whitespace-pre-wrap text-sm text-foreground">{{ summaryResult.content }}</p>
            </div>
          </section>

          <section class="bw-card p-6">
            <div class="mb-4 flex items-center gap-2">
              <PhMagnifyingGlass class="h-5 w-5 text-primary-700" aria-hidden="true" />
              <h2 class="font-semibold text-foreground">Suggest authorities</h2>
            </div>
            <p class="mb-4 text-sm text-muted-foreground">
              Describe the legal issue. AI suggests potentially relevant authorities with a
              verification warning.
            </p>
            <label class="mb-4 block">
              <span class="bw-label">Legal issue or research question</span>
              <textarea
                v-model="issue"
                class="bw-textarea mt-1 min-h-[96px]"
                placeholder="e.g. Standard for summary judgment on breach of contract claims"
              />
            </label>
            <button
              type="button"
              class="bw-btn bw-btn-action inline-flex items-center gap-2"
              :disabled="!issue.trim() || isSuggesting"
              @click="suggestAuthorities"
            >
              <PhSparkle class="h-4 w-4" aria-hidden="true" />
              {{ isSuggesting ? 'Suggesting…' : 'Suggest authorities' }}
            </button>
            <div
              v-if="authoritiesResult"
              class="mt-6 space-y-4 rounded-lg border border-border bg-surface p-4"
            >
              <AiOutputBadges
                :label="authoritiesResult.label"
                :requires-review="authoritiesResult.requires_review"
              />
              <div
                v-if="authoritiesResult.verification_warning"
                class="rounded-md border border-status-warning-border bg-status-warning px-3 py-2 text-xs text-foreground"
                role="status"
              >
                <p class="font-semibold">Source verification required</p>
                <p class="mt-1">{{ authoritiesResult.verification_warning }}</p>
              </div>
              <p class="whitespace-pre-wrap text-sm text-foreground">
                {{ authoritiesResult.content }}
              </p>
              <ul
                v-if="authoritiesResult.authorities?.length"
                class="space-y-3 border-t border-border pt-4"
              >
                <li
                  v-for="(authority, index) in authoritiesResult.authorities"
                  :key="`${authority.citation}-${index}`"
                  class="rounded-md border border-border bg-surface px-3 py-2 text-sm"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="bw-badge bw-badge-neutral">{{ authority.type }}</span>
                    <span v-if="authority.verified === false" class="bw-badge bw-badge-warning">
                      Unverified
                    </span>
                  </div>
                  <p class="mt-2 font-medium text-foreground">{{ authority.citation }}</p>
                  <p v-if="authority.relevance" class="mt-1 text-muted-foreground">
                    {{ authority.relevance }}
                  </p>
                </li>
              </ul>
            </div>
          </section>
        </div>
      </template>
    </template>
  </div>
</template>
