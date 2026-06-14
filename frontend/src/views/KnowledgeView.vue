<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhBooks, PhMagnifyingGlass, PhPlus, PhSparkle } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { knowledgeArticlesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { KnowledgeArticle } from '@/types'

const { can } = usePermissions()

const articles = ref<KnowledgeArticle[]>([])
const contentTypes = ref<string[]>([])
const categories = ref<string[]>([])
const keyword = ref('')
const categoryFilter = ref('')
const typeFilter = ref('')
const practiceArea = ref('')
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showCreate = ref(false)
const expandedId = ref<number | null>(null)

const articleForm = ref({
  title: '',
  excerpt: '',
  content: '',
  content_type: 'article',
  category: 'practice_guides',
  practice_area: '',
  tags: '',
})

const canCreate = computed(() => can('knowledge.create'))
const canUseAi = computed(() => can('ai.use'))

const practiceAreas = computed(() => {
  const values = new Set<string>()
  articles.value.forEach((article) => {
    if (article.practice_area) values.add(article.practice_area)
  })
  return [...values].sort()
})

function aiPrompt(article: KnowledgeArticle) {
  const summary = article.excerpt || article.content?.slice(0, 200) || ''
  return `Using our firm knowledge base, explain how this resource applies to my work: "${article.title}". ${summary}`
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const filters: {
      keyword?: string
      category?: string
      content_type?: string
      practice_area?: string
    } = {}
    if (keyword.value.trim()) filters.keyword = keyword.value.trim()
    if (categoryFilter.value) filters.category = categoryFilter.value
    if (typeFilter.value) filters.content_type = typeFilter.value
    if (practiceArea.value) filters.practice_area = practiceArea.value

    const result = await knowledgeArticlesApi.list(filters)
    articles.value = result.articles
    contentTypes.value = result.contentTypes
    categories.value = result.categories
  } catch (err) {
    error.value = formatApiError(err, 'Knowledge base is not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function createArticle() {
  if (!canCreate.value || !articleForm.value.title.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const tags = articleForm.value.tags
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean)
    await knowledgeArticlesApi.create({
      title: articleForm.value.title.trim(),
      excerpt: articleForm.value.excerpt.trim() || undefined,
      content: articleForm.value.content.trim() || undefined,
      content_type: articleForm.value.content_type,
      category: articleForm.value.category,
      practice_area: articleForm.value.practice_area.trim() || undefined,
      tags,
    })
    articleForm.value = {
      title: '',
      excerpt: '',
      content: '',
      content_type: 'article',
      category: 'practice_guides',
      practice_area: '',
      tags: '',
    }
    showCreate.value = false
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not create the knowledge article.')
  } finally {
    isSaving.value = false
  }
}

function toggleExpanded(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Knowledge base"
      subtitle="Firm articles, SOPs, clause snippets, and practice guides."
    >
      <template #actions>
        <button
          v-if="canCreate"
          type="button"
          class="bw-btn bw-btn-accent"
          @click="showCreate = true"
        >
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          New article
        </button>
      </template>
    </PageHeader>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
        <div class="relative min-w-[220px] flex-1">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <input
            v-model="keyword"
            type="search"
            class="bw-input pl-9"
            placeholder="Title, excerpt, content, tags…"
            aria-label="Search knowledge articles"
            @keyup.enter="load"
          />
        </div>
        <select v-model="categoryFilter" class="bw-select w-auto" aria-label="Filter by category" @change="load">
          <option value="">All categories</option>
          <option v-for="value in categories" :key="value" :value="value">
            {{ humanize(value) }}
          </option>
        </select>
        <select v-model="typeFilter" class="bw-select w-auto" aria-label="Filter by type" @change="load">
          <option value="">All types</option>
          <option v-for="type in contentTypes" :key="type" :value="type">
            {{ humanize(type) }}
          </option>
        </select>
        <select
          v-model="practiceArea"
          class="bw-select w-auto"
          aria-label="Filter by practice area"
          @change="load"
        >
          <option value="">All practice areas</option>
          <option v-for="value in practiceAreas" :key="value" :value="value">
            {{ value }}
          </option>
        </select>
        <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="load">Search</button>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-if="isLoading" variant="panel" :rows="4" class="p-4" />

      <EmptyState
        v-else-if="articles.length === 0"
        title="No knowledge articles found"
        description="Adjust filters or add a new article, SOP, or clause snippet."
        :icon="PhBooks"
        class="p-8"
      />

      <div v-else class="divide-y divide-border">
        <article
          v-for="article in articles"
          :key="article.id"
          class="px-6 py-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="bw-badge bw-badge-info">
                  <span class="bw-badge-dot" aria-hidden="true" />
                  {{ humanize(article.content_type) }}
                </span>
                <span class="bw-badge bw-badge-neutral">
                  <span class="bw-badge-dot" aria-hidden="true" />
                  {{ humanize(article.category) }}
                </span>
                <span v-if="article.practice_area" class="text-xs text-muted-foreground">
                  {{ article.practice_area }}
                </span>
              </div>
              <h3 class="mt-2 font-medium text-foreground">{{ article.title }}</h3>
              <p v-if="article.excerpt" class="mt-1 text-sm text-muted-foreground">
                {{ article.excerpt }}
              </p>
              <p
                v-if="expandedId === article.id && article.content"
                class="mt-2 whitespace-pre-wrap text-sm text-foreground"
              >
                {{ article.content }}
              </p>
              <div v-if="article.tags?.length" class="mt-3 flex flex-wrap gap-1.5">
                <span
                  v-for="tag in article.tags"
                  :key="`${article.id}-${tag}`"
                  class="rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-muted-foreground"
                >
                  {{ tag }}
                </span>
              </div>
              <p v-if="article.creator?.name" class="mt-2 text-xs text-muted-foreground">
                {{ article.creator.name }}
              </p>
            </div>
            <div class="flex flex-col gap-2">
              <button
                v-if="article.content"
                type="button"
                class="bw-btn bw-btn-secondary text-sm"
                @click="toggleExpanded(article.id)"
              >
                {{ expandedId === article.id ? 'Hide content' : 'View content' }}
              </button>
              <RouterLink
                v-if="canUseAi"
                :to="{ name: 'ai-assistant', query: { prompt: aiPrompt(article) } }"
                class="bw-btn bw-btn-secondary text-sm"
              >
                <PhSparkle class="h-4 w-4" aria-hidden="true" />
                Ask AI
              </RouterLink>
            </div>
          </div>
        </article>
      </div>
    </section>

    <BwModal
      :open="showCreate && canCreate"
      title="Add knowledge article"
      size="lg"
      @close="showCreate = false"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="sm:col-span-2">
          <span class="bw-label">Title</span>
          <input v-model="articleForm.title" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Content type</span>
          <select v-model="articleForm.content_type" class="bw-select mt-1.5">
            <option v-for="type in contentTypes" :key="type" :value="type">
              {{ humanize(type) }}
            </option>
          </select>
        </label>
        <label>
          <span class="bw-label">Category</span>
          <select v-model="articleForm.category" class="bw-select mt-1.5">
            <option v-for="value in categories" :key="value" :value="value">
              {{ humanize(value) }}
            </option>
          </select>
        </label>
        <label>
          <span class="bw-label">Practice area</span>
          <input v-model="articleForm.practice_area" class="bw-input mt-1.5" />
        </label>
        <label>
          <span class="bw-label">Tags (comma-separated)</span>
          <input v-model="articleForm.tags" class="bw-input mt-1.5" />
        </label>
        <label class="sm:col-span-2">
          <span class="bw-label">Excerpt</span>
          <textarea v-model="articleForm.excerpt" class="bw-textarea mt-1.5 min-h-[72px]" />
        </label>
        <label class="sm:col-span-2">
          <span class="bw-label">Content</span>
          <textarea v-model="articleForm.content" class="bw-textarea mt-1.5 min-h-[120px]" />
        </label>
      </div>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showCreate = false">
          Cancel
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-action"
          :disabled="isSaving || !articleForm.title.trim()"
          @click="createArticle"
        >
          {{ isSaving ? 'Saving…' : 'Save article' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
