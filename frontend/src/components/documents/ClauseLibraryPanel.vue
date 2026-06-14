<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhPlus, PhTrash } from '@phosphor-icons/vue'
import { documentClausesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { DocumentClause } from '@/types'

const emit = defineEmits<{
  insert: [html: string]
}>()

const clauses = ref<DocumentClause[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const activeCategory = ref<string>('')
const showCreate = ref(false)
const newTitle = ref('')
const newCategory = ref('general')
const newBody = ref('<p>Clause text here.</p>')

const categories = [
  'general',
  'confidentiality',
  'indemnity',
  'termination',
  'governing_law',
  'dispute_resolution',
  'payment',
  'engagement',
  'correspondence',
]

const grouped = computed(() => {
  const map = new Map<string, DocumentClause[]>()
  for (const clause of clauses.value) {
    const list = map.get(clause.category) ?? []
    list.push(clause)
    map.set(clause.category, list)
  }
  return [...map.entries()]
})

const visibleClauses = computed(() => {
  if (!activeCategory.value) return clauses.value
  return clauses.value.filter((clause) => clause.category === activeCategory.value)
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    clauses.value = await documentClausesApi.list()
    if (!activeCategory.value && clauses.value[0]) {
      activeCategory.value = clauses.value[0].category
    }
  } catch (err) {
    error.value = formatApiError(err, 'Clause library is not available.')
    clauses.value = []
  } finally {
    isLoading.value = false
  }
}

function insertClause(clause: DocumentClause) {
  emit('insert', clause.body_html)
}

async function createClause() {
  if (!newTitle.value.trim()) return
  isSaving.value = true
  error.value = null
  try {
    const created = await documentClausesApi.create({
      title: newTitle.value.trim(),
      category: newCategory.value,
      body_html: newBody.value,
    })
    clauses.value = [created, ...clauses.value]
    activeCategory.value = created.category
    newTitle.value = ''
    newBody.value = '<p>Clause text here.</p>'
    showCreate.value = false
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this clause.')
  } finally {
    isSaving.value = false
  }
}

async function deleteClause(clause: DocumentClause) {
  error.value = null
  try {
    await documentClausesApi.remove(clause.id)
    clauses.value = clauses.value.filter((item) => item.id !== clause.id)
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this clause.')
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-3 rounded-lg border border-border bg-surface p-4">
    <div class="flex items-start justify-between gap-2">
      <div>
        <p class="text-sm font-medium text-foreground">Clause library</p>
        <p class="text-xs text-muted-foreground">
          Insert reusable clauses into the editor.
        </p>
      </div>
      <button
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        @click="showCreate = !showCreate"
      >
        <PhPlus class="h-4 w-4" />
      </button>
    </div>

    <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
    <p v-if="isLoading" class="text-xs text-muted-foreground">Loading clauses…</p>

    <template v-else>
      <div v-if="grouped.length" class="flex flex-wrap gap-1.5">
        <button
          v-for="[category] in grouped"
          :key="category"
          type="button"
          class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
          :class="
            activeCategory === category
              ? 'bg-primary-700 text-white'
              : 'bg-surface text-muted-foreground hover:text-foreground'
          "
          @click="activeCategory = category"
        >
          {{ humanize(category) }}
        </button>
      </div>

      <ul
        v-if="visibleClauses.length"
        class="max-h-40 divide-y divide-border overflow-y-auto rounded-lg border border-border bg-surface"
      >
        <li
          v-for="clause in visibleClauses"
          :key="clause.id"
          class="flex items-start justify-between gap-2 px-3 py-2 text-sm"
        >
          <button
            type="button"
            class="min-w-0 flex-1 text-left hover:text-primary-700"
            @click="insertClause(clause)"
          >
            <span class="font-medium text-foreground">{{ clause.title }}</span>
            <span class="mt-0.5 block text-xs text-muted-foreground">
              {{ humanize(clause.category) }}
            </span>
          </button>
          <button
            type="button"
            class="shrink-0 text-muted-foreground hover:text-destructive"
            title="Delete clause"
            @click="deleteClause(clause)"
          >
            <PhTrash class="h-4 w-4" />
          </button>
        </li>
      </ul>
      <p v-else class="text-xs text-muted-foreground">No clauses yet. Add one to get started.</p>

      <form v-if="showCreate" class="space-y-2 border-t border-border pt-3" @submit.prevent="createClause">
        <input v-model="newTitle" class="bw-input" placeholder="Clause title" required />
        <select v-model="newCategory" class="bw-select">
          <option v-for="category in categories" :key="category" :value="category">
            {{ humanize(category) }}
          </option>
        </select>
        <textarea
          v-model="newBody"
          class="bw-input min-h-[72px] font-mono text-xs"
          placeholder="<p>Clause HTML</p>"
        />
        <button type="submit" class="bw-btn bw-btn-outline w-full" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save clause' }}
        </button>
      </form>
    </template>
  </div>
</template>
