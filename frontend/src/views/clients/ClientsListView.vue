<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  PhDotsThreeVertical,
  PhFunnel,
  PhListBullets,
  PhMagnifyingGlass,
  PhPlus,
  PhSquaresFour,
  PhUsers,
} from '@phosphor-icons/vue'
import ClientCard from '@/components/clients/ClientCard.vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize, statusDotVar } from '@/lib/status'
import type { Client, PaginatedResponse } from '@/types'

const router = useRouter()
const clients = ref<Client[]>([])
const search = ref('')
const statusFilter = ref('')
const viewMode = ref<'grid' | 'list'>('grid')
const page = ref(1)
const perPage = ref(12)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const error = ref<string | null>(null)
const selectedIds = ref<Set<number>>(new Set())
const bulkMenuOpen = ref(false)
const showFilter = ref(false)

const statusOptions = ['active', 'inactive', 'prospect']

const selectedCount = computed(() => selectedIds.value.size)
const pageTitle = computed(() => {
  const n = total.value
  return `${n} Client${n === 1 ? '' : 's'}`
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const { data } = await api.get<PaginatedResponse<Client>>('/clients', {
      params: {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        page: page.value,
        per_page: perPage.value,
      },
    })
    clients.value = data.data
    lastPage.value = data.meta.last_page
    total.value = data.meta.total
    const visible = new Set(data.data.map((c) => c.id))
    selectedIds.value = new Set(
      [...selectedIds.value].filter((id) => visible.has(id)),
    )
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
}

function toggleSelect(id: number) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function isSelected(id: number) {
  return selectedIds.value.has(id)
}

function openClient(id: number) {
  router.push(`/clients/${id}`)
}

function editClient(id: number) {
  router.push(`/clients/${id}/edit`)
}

async function deleteClient(client: Client) {
  if (!confirm(`Delete ${client.name}? This cannot be undone.`)) return
  try {
    await api.delete(`/clients/${client.id}`)
    selectedIds.value.delete(client.id)
    selectedIds.value = new Set(selectedIds.value)
    await load()
  } catch (err) {
    error.value = formatApiError(err)
  }
}

async function bulkDelete() {
  closeBulkMenu()
  if (!selectedCount.value) return
  if (!confirm(`Delete ${selectedCount.value} selected client(s)? This cannot be undone.`)) {
    return
  }
  try {
    const ids = [...selectedIds.value]
    await Promise.all(ids.map((id) => api.delete(`/clients/${id}`)))
    selectedIds.value = new Set()
    await load()
  } catch (err) {
    error.value = formatApiError(err)
  }
}

function contactLine(client: Client) {
  return (
    client.email || client.phone || client.company_name || 'No contact details'
  )
}

function toggleBulkMenu() {
  bulkMenuOpen.value = !bulkMenuOpen.value
  if (bulkMenuOpen.value) {
    document.addEventListener('click', closeBulkMenu)
  }
}

function closeBulkMenu() {
  bulkMenuOpen.value = false
  document.removeEventListener('click', closeBulkMenu)
}

function toggleFilter() {
  showFilter.value = !showFilter.value
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

watch(statusFilter, () => {
  page.value = 1
  load()
})

watch(perPage, () => {
  page.value = 1
  load()
})

watch(page, load)

onMounted(load)
onBeforeUnmount(() => document.removeEventListener('click', closeBulkMenu))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
          {{ pageTitle }}
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          People and organizations you represent.
        </p>
      </div>
      <RouterLink to="/clients/new" class="bw-btn bw-btn-accent">
        <PhPlus class="h-4 w-4" weight="bold" />
        Add client
      </RouterLink>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="bw-btn bw-btn-outline bw-btn-sm"
        :class="showFilter || statusFilter ? 'border-action-teal text-action-teal' : ''"
        @click="toggleFilter"
      >
        <PhFunnel class="h-4 w-4" />
        Filter
      </button>

      <div class="relative min-w-[200px] flex-1 sm:max-w-xs">
        <PhMagnifyingGlass
          class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
        />
        <input
          v-model="search"
          type="search"
          placeholder="Search name, email, company…"
          class="bw-input pl-9"
          aria-label="Search clients"
        />
      </div>

      <div
        v-if="selectedCount > 0"
        class="flex items-center gap-2"
      >
        <span class="text-sm font-medium text-foreground">
          {{ selectedCount }} selected
        </span>
        <div class="relative">
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-icon bw-btn-sm"
            aria-haspopup="menu"
            :aria-expanded="bulkMenuOpen"
            @click.stop="toggleBulkMenu"
          >
            <PhDotsThreeVertical class="h-4 w-4" weight="bold" />
          </button>
          <div
            v-if="bulkMenuOpen"
            class="absolute left-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-border bg-surface py-1 shadow-md"
            role="menu"
          >
            <button
              type="button"
              class="flex w-full px-3 py-2 text-left text-sm text-destructive hover:bg-surface-muted"
              role="menuitem"
              @click="bulkDelete"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <div class="flex items-center gap-1 rounded-lg border border-border bg-surface p-1">
          <button
            type="button"
            class="bw-btn bw-btn-sm"
            :class="viewMode === 'list' ? 'bw-btn-accent' : 'bw-btn-ghost'"
            aria-label="List view"
            @click="viewMode = 'list'"
          >
            <PhListBullets class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-sm"
            :class="viewMode === 'grid' ? 'bw-btn-accent' : 'bw-btn-ghost'"
            aria-label="Grid view"
            @click="viewMode = 'grid'"
          >
            <PhSquaresFour class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="showFilter"
      class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 shadow-sm"
    >
      <label class="text-sm font-medium text-muted-foreground" for="client-status-filter">
        Status
      </label>
      <select
        id="client-status-filter"
        v-model="statusFilter"
        class="bw-select w-auto min-w-[160px]"
        aria-label="Filter by status"
      >
        <option value="">All statuses</option>
        <option v-for="s in statusOptions" :key="s" :value="s">
          {{ humanize(s) }}
        </option>
      </select>
      <button
        v-if="statusFilter"
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        @click="statusFilter = ''"
      >
        Clear
      </button>
    </div>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <Skeleton
      v-else-if="isLoading"
      :variant="viewMode === 'grid' ? 'cards' : 'panel'"
      :rows="6"
      :count="8"
    />

    <template v-else-if="clients.length">
      <div
        v-if="viewMode === 'grid'"
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      >
        <ClientCard
          v-for="c in clients"
          :key="c.id"
          :client="c"
          :selected="isSelected(c.id)"
          @click="openClient(c.id)"
          @toggle-select="toggleSelect(c.id)"
          @view="openClient(c.id)"
          @edit="editClient(c.id)"
          @delete="deleteClient(c)"
        />
      </div>

      <div v-else class="bw-card overflow-hidden">
        <div
          class="hidden grid-cols-[40px_minmax(0,1fr)_180px_120px] gap-4 border-b border-border px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:grid"
        >
          <span aria-hidden="true" />
          <span>Name</span>
          <span>Contact</span>
          <span class="text-right">Status</span>
        </div>
        <div class="divide-y divide-border">
          <div
            v-for="c in clients"
            :key="c.id"
            class="grid w-full grid-cols-1 items-center gap-3 px-5 py-4 sm:grid-cols-[40px_minmax(0,1fr)_180px_120px] sm:gap-4"
          >
            <label class="flex items-center justify-center sm:justify-start">
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-border text-action-teal focus:ring-action-teal"
                :checked="isSelected(c.id)"
                :aria-label="`Select ${c.name}`"
                @change="toggleSelect(c.id)"
              />
            </label>
            <button
              type="button"
              class="flex min-w-0 items-center gap-3 text-left transition-colors hover:opacity-80"
              @click="openClient(c.id)"
            >
              <AppAvatar :name="c.name" size="sm" tone="accent" />
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-foreground">{{ c.name }}</p>
                <p
                  v-if="c.client_number"
                  class="truncate text-xs tabular-nums text-muted-foreground"
                >
                  {{ c.client_number }}
                </p>
                <p class="truncate text-xs text-muted-foreground sm:hidden">
                  {{ contactLine(c) }}
                </p>
              </div>
            </button>
            <span class="hidden truncate text-sm text-muted-foreground sm:block">
              {{ contactLine(c) }}
            </span>
            <span class="inline-flex items-center justify-end gap-2 sm:justify-end">
              <span
                class="h-2.5 w-2.5 shrink-0 rounded-full"
                :style="{ backgroundColor: `var(${statusDotVar(c.status)})` }"
                aria-hidden="true"
              />
              <StatusBadge :status="c.status" :dot="false" />
            </span>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm text-muted-foreground">
          View
          <select v-model.number="perPage" class="bw-select w-auto py-1 text-sm">
            <option :value="12">12</option>
            <option :value="24">24</option>
            <option :value="48">48</option>
          </select>
          per page
        </label>
        <PaginationBar
          v-if="lastPage > 1"
          :page="page"
          :last-page="lastPage"
          :total="total"
          class="!border-0 !px-0"
          @update:page="page = $event"
        />
        <span v-else class="text-sm text-muted-foreground">{{ total }} total</span>
      </div>
    </template>

    <EmptyState
      v-else
      :icon="PhUsers"
      title="No clients found"
      :message="
        search || statusFilter
          ? 'Try adjusting your search or filters.'
          : 'Add your first client to get started.'
      "
    >
      <RouterLink to="/clients/new" class="bw-btn bw-btn-accent bw-btn-sm">
        Add client
      </RouterLink>
    </EmptyState>
  </div>
</template>
