<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhBookmarkSimple, PhBooks, PhMagnifyingGlass, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import ResearchCommandCenterPanel from '@/components/research/ResearchCommandCenterPanel.vue'
import { usePermissions } from '@/composables/usePermissions'
import { researchEntriesApi, researchFoldersApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { LegalResearchEntry, ResearchFolder } from '@/types'

const activeTab = ref<'library' | 'command-center'>('command-center')

const { can } = usePermissions()

const entries = ref<LegalResearchEntry[]>([])
const folders = ref<ResearchFolder[]>([])
const documentTypes = ref<string[]>([])
const keyword = ref('')
const jurisdiction = ref('')
const typeFilter = ref('')
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showCreateEntry = ref(false)
const showCreateFolder = ref(false)

const entryForm = ref({
  title: '',
  citation: '',
  summary: '',
  jurisdiction: '',
  document_type: 'case',
  tags: '',
})

const folderForm = ref({
  name: '',
  description: '',
  practice_area: '',
  legal_issue: '',
})

const canCreate = computed(() => can('research.create'))

const jurisdictions = computed(() => {
  const values = new Set<string>()
  entries.value.forEach((entry) => {
    if (entry.jurisdiction) values.add(entry.jurisdiction)
  })
  return [...values].sort()
})

async function load() {
  isLoading.value = true
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

    const [entryResult, folderRows] = await Promise.all([
      researchEntriesApi.list(filters),
      researchFoldersApi.list(),
    ])
    entries.value = entryResult.entries
    documentTypes.value = entryResult.documentTypes
    folders.value = folderRows
  } catch (err) {
    error.value = formatApiError(err, 'Legal research is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function createEntry() {
  if (!canCreate.value || !entryForm.value.title.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const tags = entryForm.value.tags
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean)
    await researchEntriesApi.create({
      title: entryForm.value.title.trim(),
      citation: entryForm.value.citation.trim() || undefined,
      summary: entryForm.value.summary.trim() || undefined,
      jurisdiction: entryForm.value.jurisdiction.trim() || undefined,
      document_type: entryForm.value.document_type,
      tags,
    })
    entryForm.value = {
      title: '',
      citation: '',
      summary: '',
      jurisdiction: '',
      document_type: 'case',
      tags: '',
    }
    showCreateEntry.value = false
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the research entry.')
  } finally {
    isSaving.value = false
  }
}

async function createFolder() {
  if (!canCreate.value || !folderForm.value.name.trim()) return
  isSaving.value = true
  error.value = null
  try {
    await researchFoldersApi.create({
      name: folderForm.value.name.trim(),
      description: folderForm.value.description.trim() || undefined,
      practice_area: folderForm.value.practice_area.trim() || undefined,
      legal_issue: folderForm.value.legal_issue.trim() || undefined,
    })
    folderForm.value = { name: '', description: '', practice_area: '', legal_issue: '' }
    showCreateFolder.value = false
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the research folder.')
  } finally {
    isSaving.value = false
  }
}

async function saveToFolder(entry: LegalResearchEntry, folder: ResearchFolder) {
  if (!canCreate.value) return
  error.value = null
  try {
    await researchFoldersApi.saveItem(folder.id, {
      legal_research_entry_id: entry.id,
    })
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this entry to the folder.')
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="AI Legal Research Command Center"
      subtitle="Natural-language research, case search, memos, strategy, workspace projects, and one-click transfer to Brief Writer."
    />

    <nav class="bw-tabs">
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeTab === 'command-center' }"
        @click="activeTab = 'command-center'"
      >
        Command center
      </button>
      <button
        type="button"
        class="bw-tab"
        :class="{ 'bw-tab-active': activeTab === 'library' }"
        @click="activeTab = 'library'"
      >
        Research library
      </button>
    </nav>

    <ResearchCommandCenterPanel v-if="activeTab === 'command-center'" />

    <template v-else>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="relative sm:col-span-2">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <input
            v-model="keyword"
            type="search"
            class="bw-input pl-9"
            placeholder="Keyword, citation, summary…"
            @keyup.enter="load"
          />
        </div>
        <select v-model="jurisdiction" class="bw-input" @change="load">
          <option value="">All jurisdictions</option>
          <option v-for="value in jurisdictions" :key="value" :value="value">
            {{ value }}
          </option>
        </select>
        <select v-model="typeFilter" class="bw-input" @change="load">
          <option value="">All types</option>
          <option v-for="type in documentTypes" :key="type" :value="type">
            {{ humanize(type) }}
          </option>
        </select>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="bw-btn bw-btn-secondary" @click="load">Search</button>
        <button
          v-if="canCreate"
          type="button"
          class="bw-btn bw-btn-accent"
          @click="showCreateEntry = true"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          New entry
        </button>
        <button
          v-if="canCreate"
          type="button"
          class="bw-btn bw-btn-outline"
          @click="showCreateFolder = true"
        >
          <PhBookmarkSimple class="h-4 w-4" aria-hidden="true" />
          New folder
        </button>
      </div>
    </div>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="space-y-3 lg:col-span-2">
        <div class="flex items-center gap-2">
          <PhBooks class="h-5 w-5 text-primary-700" aria-hidden="true" />
          <h2 class="font-semibold text-foreground">Research library</h2>
        </div>

        <Skeleton v-if="isLoading" variant="cards" />

        <EmptyState
          v-else-if="entries.length === 0"
          title="No research entries found"
          description="Adjust filters or add a new internal research entry."
          :icon="PhBooks"
        />

        <div v-else class="space-y-3">
          <article
            v-for="entry in entries"
            :key="entry.id"
            class="bw-card p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="bw-badge bw-badge-neutral">{{ humanize(entry.document_type) }}</span>
                  <span v-if="entry.jurisdiction" class="text-xs text-muted-foreground">
                    {{ entry.jurisdiction }}
                  </span>
                </div>
                <h3 class="mt-2 font-medium text-foreground">{{ entry.title }}</h3>
                <p v-if="entry.citation" class="mt-1 text-sm text-muted-foreground">
                  {{ entry.citation }}
                </p>
                <p v-if="entry.summary" class="mt-2 text-sm text-foreground">{{ entry.summary }}</p>
                <div v-if="entry.tags?.length" class="mt-3 flex flex-wrap gap-1.5">
                  <span
                    v-for="tag in entry.tags"
                    :key="`${entry.id}-${tag}`"
                    class="rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-muted-foreground"
                  >
                    {{ tag }}
                  </span>
                </div>
              </div>
              <div v-if="canCreate && folders.length" class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground">Save to folder</label>
                <select
                  class="bw-input text-sm"
                  @change="(e) => {
                    const folderId = Number((e.target as HTMLSelectElement).value)
                    const folder = folders.find((row) => row.id === folderId)
                    if (folder) void saveToFolder(entry, folder)
                    ;(e.target as HTMLSelectElement).value = ''
                  }"
                >
                  <option value="">Choose…</option>
                  <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                    {{ folder.name }}
                  </option>
                </select>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center gap-2">
          <PhBookmarkSimple class="h-5 w-5 text-primary-700" aria-hidden="true" />
          <h2 class="font-semibold text-foreground">Saved folders</h2>
        </div>

        <Skeleton v-if="isLoading" class="h-24 w-full" />

        <EmptyState
          v-else-if="folders.length === 0"
          title="No folders yet"
          description="Create a folder to organize saved authorities and notes."
          :icon="PhBookmarkSimple"
        />

        <div v-else class="space-y-3">
          <article
            v-for="folder in folders"
            :key="folder.id"
            class="bw-card p-4"
          >
            <h3 class="font-medium text-foreground">{{ folder.name }}</h3>
            <p v-if="folder.legal_issue" class="mt-1 text-sm text-muted-foreground">
              {{ folder.legal_issue }}
            </p>
            <p class="mt-2 text-xs text-muted-foreground">
              {{ folder.items_count ?? 0 }} saved item(s)
              <span v-if="folder.legal_matter?.title"> · {{ folder.legal_matter.title }}</span>
            </p>
          </article>
        </div>
      </section>
    </div>
    </template>

    <BwModal
      :open="showCreateEntry && canCreate"
      title="Add research entry"
      size="lg"
      @close="showCreateEntry = false"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="sm:col-span-2">
          <span class="bw-label">Title</span>
          <input v-model="entryForm.title" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Citation</span>
          <input v-model="entryForm.citation" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Jurisdiction</span>
          <input v-model="entryForm.jurisdiction" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Document type</span>
          <select v-model="entryForm.document_type" class="bw-select mt-1.5">
            <option v-for="type in documentTypes" :key="type" :value="type">
              {{ humanize(type) }}
            </option>
          </select>
        </label>
        <label>
          <span class="bw-label">Tags (comma-separated)</span>
          <input v-model="entryForm.tags" class="bw-input mt-1.5" />
        </label>
        <label class="sm:col-span-2">
          <span class="bw-label">Summary</span>
          <textarea v-model="entryForm.summary" class="bw-textarea mt-1.5 min-h-[96px]" />
        </label>
      </div>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showCreateEntry = false">
          Cancel
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !entryForm.title.trim()"
          @click="createEntry"
        >
          {{ isSaving ? 'Saving…' : 'Save entry' }}
        </button>
      </template>
    </BwModal>

    <BwModal
      :open="showCreateFolder && canCreate"
      title="Create research folder"
      @close="showCreateFolder = false"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="sm:col-span-2">
          <span class="bw-label">Folder name</span>
          <input v-model="folderForm.name" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Practice area</span>
          <input v-model="folderForm.practice_area" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Legal issue</span>
          <input v-model="folderForm.legal_issue" class="bw-input mt-1.5" />
        </label>
        <label class="sm:col-span-2">
          <span class="bw-label">Description</span>
          <textarea v-model="folderForm.description" class="bw-textarea mt-1.5 min-h-[72px]" />
        </label>
      </div>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showCreateFolder = false">
          Cancel
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !folderForm.name.trim()"
          @click="createFolder"
        >
          {{ isSaving ? 'Saving…' : 'Save folder' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
