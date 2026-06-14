<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhBooks, PhMagnifyingGlass, PhPlus, PhSparkle } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { knowledgeArticlesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { KnowledgeArticle } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()

const articles = ref<KnowledgeArticle[]>([])
const contentTypes = ref<string[]>([])
const categories = ref<string[]>([])
const keyword = ref('')
const categoryFilter = ref('')
const typeFilter = ref('')
const isLoading = ref(true)
const isSearching = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showCreate = ref(false)

const noteForm = ref({
  title: '',
  excerpt: '',
  content: '',
  tags: '',
})

const canView = computed(() => can('knowledge.view'))
const canCreate = computed(() => can('knowledge.create'))
const canUseAi = computed(() => can('ai.use'))

function aiPrompt(article: KnowledgeArticle) {
  const summary = article.excerpt || article.content?.slice(0, 200) || ''
  return `For this case, explain how this firm knowledge resource applies: "${article.title}". ${summary}`
}

async function load() {
  if (!canView.value) return
  isLoading.value = true
  error.value = null
  try {
    const result = await knowledgeArticlesApi.list({ legal_matter_id: props.caseId })
    articles.value = result.articles
    contentTypes.value = result.contentTypes
    categories.value = result.categories
  } catch (err) {
    error.value = formatApiError(err, 'Knowledge base is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function search() {
  if (!canView.value) return
  isSearching.value = true
  error.value = null
  try {
    const filters: {
      legal_matter_id: number
      keyword?: string
      category?: string
      content_type?: string
    } = { legal_matter_id: props.caseId }
    if (keyword.value.trim()) filters.keyword = keyword.value.trim()
    if (categoryFilter.value) filters.category = categoryFilter.value
    if (typeFilter.value) filters.content_type = typeFilter.value
    const result = await knowledgeArticlesApi.list(filters)
    articles.value = result.articles
    contentTypes.value = result.contentTypes
    categories.value = result.categories
  } catch (err) {
    error.value = formatApiError(err, 'We could not search the knowledge base.')
  } finally {
    isSearching.value = false
  }
}

function resetForm() {
  noteForm.value = { title: '', excerpt: '', content: '', tags: '' }
}

function openCreate() {
  resetForm()
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
  resetForm()
}

async function createCaseNote() {
  if (!canCreate.value || !noteForm.value.title.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const tags = noteForm.value.tags
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean)
    await knowledgeArticlesApi.create({
      legal_matter_id: props.caseId,
      title: noteForm.value.title.trim(),
      excerpt: noteForm.value.excerpt.trim() || undefined,
      content: noteForm.value.content.trim() || undefined,
      content_type: 'article',
      category: 'case_strategy',
      tags,
    })
    closeCreate()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save the case knowledge note.')
  } finally {
    isSaving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="bw-card overflow-hidden">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">Knowledge base</h2>
        <p class="text-sm text-muted-foreground">
          Firm-wide resources plus case-specific strategy notes.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          v-if="canView"
          :to="{ name: 'knowledge' }"
          class="bw-btn bw-btn-outline bw-btn-sm"
        >
          <PhBooks class="h-4 w-4" aria-hidden="true" />
          Full knowledge base
        </RouterLink>
        <button
          v-if="canCreate"
          type="button"
          class="bw-btn bw-btn-accent bw-btn-sm"
          @click="openCreate"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          Case note
        </button>
      </div>
    </div>

    <EmptyState
      v-if="!canView"
      title="Knowledge base unavailable"
      description="You need the knowledge.view permission to browse firm resources."
      :icon="PhBooks"
    />

    <template v-else>
      <div class="space-y-4 border-b border-border px-6 py-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="relative sm:col-span-2">
            <PhMagnifyingGlass
              class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            />
            <input
              v-model="keyword"
              type="search"
              class="bw-input pl-9"
              placeholder="Search articles, SOPs, clauses…"
              @keyup.enter="search"
            />
          </div>
          <select v-model="categoryFilter" class="bw-select" @change="search">
            <option value="">All categories</option>
            <option v-for="value in categories" :key="value" :value="value">
              {{ humanize(value) }}
            </option>
          </select>
          <select v-model="typeFilter" class="bw-select" @change="search">
            <option value="">All types</option>
            <option v-for="type in contentTypes" :key="type" :value="type">
              {{ humanize(type) }}
            </option>
          </select>
        </div>
        <button
          type="button"
          class="bw-btn bw-btn-outline bw-btn-sm"
          :disabled="isSearching"
          @click="search"
        >
          {{ isSearching ? 'Searching…' : 'Search' }}
        </button>
      </div>

      <p v-if="error" class="px-6 pt-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-if="isLoading" variant="panel" :rows="3" class="p-6" />

      <EmptyState
        v-else-if="articles.length === 0"
        title="No knowledge resources found"
        description="Try different filters or add a case-specific strategy note."
        :icon="PhBooks"
      />

      <div v-else class="divide-y divide-border">
        <article v-for="article in articles" :key="article.id" class="px-6 py-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="bw-badge bw-badge-neutral">{{ humanize(article.content_type) }}</span>
                <span v-if="article.legal_matter_id === caseId" class="bw-badge bw-badge-info">
                  Case note
                </span>
              </div>
              <h3 class="mt-2 font-medium text-foreground">{{ article.title }}</h3>
              <p v-if="article.excerpt" class="mt-1 text-sm text-muted-foreground">
                {{ article.excerpt }}
              </p>
              <div v-if="article.tags?.length" class="mt-2 flex flex-wrap gap-1.5">
                <span
                  v-for="tag in article.tags"
                  :key="`${article.id}-${tag}`"
                  class="bw-badge bw-badge-neutral"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
            <RouterLink
              v-if="canUseAi"
              :to="{ name: 'ai-assistant', query: { prompt: aiPrompt(article) } }"
              class="bw-btn bw-btn-outline bw-btn-sm"
            >
              <PhSparkle class="h-4 w-4" aria-hidden="true" />
              Ask AI
            </RouterLink>
          </div>
        </article>
      </div>
    </template>

    <BwModal
      :open="showCreate && canCreate"
      title="Add case strategy note"
      size="md"
      @close="closeCreate"
    >
      <form id="knowledge-note-form" class="space-y-4" @submit.prevent="createCaseNote">
        <div>
          <label class="bw-label" for="note-title">Title</label>
          <input id="note-title" v-model="noteForm.title" class="bw-input" placeholder="Title" required />
        </div>
        <div>
          <label class="bw-label" for="note-excerpt">Summary</label>
          <textarea
            id="note-excerpt"
            v-model="noteForm.excerpt"
            class="bw-textarea"
            rows="2"
            placeholder="Short summary"
          />
        </div>
        <div>
          <label class="bw-label" for="note-content">Detailed notes</label>
          <textarea
            id="note-content"
            v-model="noteForm.content"
            class="bw-textarea"
            rows="4"
            placeholder="Detailed notes"
          />
        </div>
        <div>
          <label class="bw-label" for="note-tags">Tags</label>
          <input
            id="note-tags"
            v-model="noteForm.tags"
            class="bw-input"
            placeholder="Tags (comma-separated)"
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeCreate">Cancel</button>
        <button
          type="submit"
          form="knowledge-note-form"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !noteForm.title.trim()"
        >
          {{ isSaving ? 'Saving…' : 'Save note' }}
        </button>
      </template>
    </BwModal>
  </section>
</template>
