<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PhBriefcase, PhListBullets, PhMagnifyingGlass, PhPlus, PhSquaresFour } from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { humanize, statusDotVar } from '@/lib/status'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { LegalMatter, PaginatedResponse } from '@/types'

const router = useRouter()
const cases = ref<LegalMatter[]>([])
const search = ref('')
const statusFilter = ref('')
const viewMode = ref<'list' | 'cards'>('list')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const error = ref<string | null>(null)

const statusOptions = [
  'new',
  'active',
  'in_court',
  'awaiting_client_response',
  'closed',
  'archived',
]

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const { data } = await api.get<PaginatedResponse<LegalMatter>>('/cases', {
      params: {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        page: page.value,
      },
    })
    cases.value = data.data
    lastPage.value = data.meta.last_page
    total.value = data.meta.total
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

watch([statusFilter, page], load)
onMounted(load)

function openCase(id: number) {
  router.push(`/cases/${id}`)
}

function clientName(m: LegalMatter) {
  if (m.client && 'name' in m.client) return m.client.name
  return '—'
}

function matterStatus(m: LegalMatter) {
  return m.stage ?? m.status
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Cases" subtitle="Matters your team is working on.">
      <template #actions>
        <RouterLink to="/cases/new" class="bw-btn bw-btn-accent">
          <PhPlus class="h-4 w-4" weight="bold" />
          New case
        </RouterLink>
      </template>
    </PageHeader>

    <div class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
        <div class="relative min-w-[220px] flex-1">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
          />
          <input
            v-model="search"
            type="search"
            placeholder="Search title or matter number…"
            class="bw-input pl-9"
            aria-label="Search cases"
          />
        </div>
        <select v-model="statusFilter" class="bw-select w-auto" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option v-for="s in statusOptions" :key="s" :value="s">
            {{ humanize(s) }}
          </option>
        </select>
        <div class="flex items-center gap-1 rounded-lg border border-border bg-surface p-1">
          <button
            type="button"
            class="bw-btn bw-btn-sm"
            :class="viewMode === 'list' ? 'bw-btn-primary' : 'bw-btn-ghost'"
            aria-label="List view"
            @click="viewMode = 'list'"
          >
            <PhListBullets class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-sm"
            :class="viewMode === 'cards' ? 'bw-btn-primary' : 'bw-btn-ghost'"
            aria-label="Card view"
            @click="viewMode = 'cards'"
          >
            <PhSquaresFour class="h-4 w-4" />
          </button>
        </div>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-else-if="isLoading" :variant="viewMode === 'cards' ? 'cards' : 'panel'" :rows="6" />

      <template v-else-if="cases.length">
        <!-- List view -->
        <template v-if="viewMode === 'list'">
          <div
            class="hidden grid-cols-[minmax(0,1fr)_200px_140px] gap-4 border-b border-border px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:grid"
          >
            <span>Matter</span>
            <span>Client</span>
            <span class="text-right">Status</span>
          </div>
          <div class="divide-y divide-border">
            <button
              v-for="m in cases"
              :key="m.id"
              type="button"
              class="grid w-full grid-cols-1 items-center gap-3 px-5 py-3.5 text-left transition-colors hover:bg-surface-muted sm:grid-cols-[minmax(0,1fr)_200px_140px] sm:gap-4"
              @click="openCase(m.id)"
            >
              <div class="flex min-w-0 items-center gap-3">
                <AppAvatar :name="m.title" size="sm" tone="primary" />
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-foreground">{{ m.title }}</p>
                  <p class="truncate text-xs text-muted-foreground">
                    <span v-if="m.matter_number" class="tabular-nums">{{ m.matter_number }}</span>
                    <span v-if="m.matter_number && m.lead_lawyer"> · </span>
                    <span v-if="m.lead_lawyer">{{ m.lead_lawyer.name }}</span>
                  </p>
                </div>
              </div>
              <span class="hidden truncate text-sm text-muted-foreground sm:block">
                {{ clientName(m) }}
              </span>
              <span class="sm:text-right">
                <StatusBadge :status="matterStatus(m)" />
              </span>
            </button>
          </div>
        </template>

        <!-- Card view (recent-matters pattern) -->
        <div v-else class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
          <button
            v-for="m in cases"
            :key="m.id"
            type="button"
            class="group flex min-h-[148px] flex-col rounded-xl border border-border bg-surface p-5 text-left transition-colors hover:border-primary-200 hover:bg-primary-50/30"
            @click="openCase(m.id)"
          >
            <div class="flex items-start gap-3">
              <AppAvatar :name="m.title" size="md" tone="primary" />
              <div class="min-w-0 flex-1">
                <p class="line-clamp-2 text-sm font-semibold text-foreground group-hover:text-primary-800">
                  {{ m.title }}
                </p>
                <p
                  v-if="m.matter_number"
                  class="mt-0.5 text-xs font-medium tabular-nums text-primary-700"
                >
                  {{ m.matter_number }}
                </p>
              </div>
            </div>

            <div class="mt-4 space-y-2">
              <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span
                  class="h-2 w-2 shrink-0 rounded-full"
                  :style="{ backgroundColor: `var(${statusDotVar(matterStatus(m))})` }"
                />
                <StatusBadge :status="matterStatus(m)" :dot="false" />
              </div>
              <p class="truncate text-xs text-muted-foreground">
                <span class="font-medium text-foreground/80">{{ clientName(m) }}</span>
                <span v-if="m.lead_lawyer"> · {{ m.lead_lawyer.name }}</span>
              </p>
              <p v-if="m.matter_stage" class="truncate text-xs capitalize text-muted-foreground">
                {{ m.matter_stage.replace(/_/g, ' ') }}
              </p>
            </div>
          </button>
        </div>

        <PaginationBar
          :page="page"
          :last-page="lastPage"
          :total="total"
          @update:page="page = $event"
        />
      </template>

      <EmptyState
        v-else
        :icon="PhBriefcase"
        title="No cases found"
        :message="
          search || statusFilter
            ? 'Try adjusting your search or filters.'
            : 'Open your first case to get started.'
        "
      >
        <RouterLink to="/cases/new" class="bw-btn bw-btn-primary bw-btn-sm">
          New case
        </RouterLink>
      </EmptyState>
    </div>
  </div>
</template>
