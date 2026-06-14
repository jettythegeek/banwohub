<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  PhBriefcase,
  PhChatCircle,
  PhFileText,
  PhMagnifyingGlass,
  PhNote,
  PhUser,
} from '@phosphor-icons/vue'
import Skeleton from '@/components/common/Skeleton.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { searchApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { SearchResultItem, SearchSection } from '@/types'

const route = useRoute()
const query = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)
const results = ref<{
  cases: SearchResultItem[]
  clients: SearchResultItem[]
  documents: SearchResultItem[]
  notes: SearchResultItem[]
  messages: SearchResultItem[]
} | null>(null)
const sections = ref<SearchSection[]>([])
const total = ref(0)

const hasResults = computed(() => (results.value ? total.value > 0 : false))

const resultSections = computed(() => {
  if (!results.value) return []
  return [
    { key: 'cases', label: 'Cases', items: results.value.cases, icon: PhBriefcase },
    { key: 'clients', label: 'Clients', items: results.value.clients, icon: PhUser },
    { key: 'documents', label: 'Documents', items: results.value.documents, icon: PhFileText },
    { key: 'notes', label: 'Notes', items: results.value.notes, icon: PhNote },
    { key: 'messages', label: 'Messages', items: results.value.messages, icon: PhChatCircle },
  ].filter((section) => section.items.length > 0)
})

async function runSearch(term: string) {
  const trimmed = term.trim()
  query.value = trimmed
  if (trimmed.length < 2) {
    results.value = null
    sections.value = []
    total.value = 0
    error.value = 'Enter at least 2 characters to search.'
    return
  }

  isLoading.value = true
  error.value = null
  try {
    const response = await searchApi.search(trimmed)
    results.value = response.results
    sections.value = response.sections ?? []
    total.value = response.total
  } catch (err) {
    error.value = formatApiError(err, 'Search is not available yet.')
    results.value = null
    sections.value = []
    total.value = 0
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  const q = route.query.q
  if (typeof q === 'string' && q.trim()) {
    void runSearch(q)
  }
})

watch(
  () => route.query.q,
  (value) => {
    if (typeof value === 'string') {
      void runSearch(value)
    }
  },
)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Search"
      subtitle="Find cases, clients, documents, notes, and messages across your firm."
    />

    <section class="bw-card overflow-hidden">
      <form
        class="relative border-b border-border p-4"
        role="search"
        @submit.prevent="runSearch(query)"
      >
        <PhMagnifyingGlass
          class="pointer-events-none absolute left-7 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
        />
        <input
          v-model="query"
          type="search"
          class="bw-input w-full pl-9"
          placeholder="Search cases, clients, documents, notes, messages…"
          aria-label="Search"
          minlength="2"
        />
      </form>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>
      <Skeleton v-if="isLoading" variant="panel" :rows="5" />

      <template v-else-if="results">
        <div
          v-if="sections.length"
          class="flex flex-wrap gap-2 border-b border-border px-4 py-3"
        >
          <span
            v-for="section in sections"
            :key="section.key"
            class="bw-badge bw-badge-neutral tabular-nums"
          >
            {{ section.label }} · {{ section.count }}
          </span>
        </div>

        <p v-if="query" class="px-4 py-3 text-sm text-muted-foreground">
          {{ total }} result{{ total === 1 ? '' : 's' }} for “{{ query }}”
        </p>

        <EmptyState
          v-if="!hasResults"
          :icon="PhMagnifyingGlass"
          title="No matches"
          message="Try a different keyword or check spelling."
          class="p-8"
        />

        <div v-else class="divide-y divide-border">
          <section
            v-for="section in resultSections"
            :key="section.key"
          >
            <h2 class="px-6 py-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
              {{ section.label }}
            </h2>
            <RouterLink
              v-for="item in section.items"
              :key="`${section.key}-${item.id}`"
              :to="item.url"
              class="flex items-start gap-3 px-6 py-4 transition-colors hover:bg-muted/30"
            >
              <component :is="section.icon" class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
              <div class="min-w-0">
                <p class="font-medium text-foreground">{{ item.title }}</p>
                <p v-if="item.subtitle" class="text-sm text-muted-foreground">{{ item.subtitle }}</p>
              </div>
            </RouterLink>
          </section>
        </div>
      </template>
    </section>
  </div>
</template>
