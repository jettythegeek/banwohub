<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhMagnifyingGlass, PhShieldCheck } from '@phosphor-icons/vue'
import AiDisclaimerBanner from '@/components/ai/AiDisclaimerBanner.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { aiApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { AiGovernanceLog, AiGovernanceSettings } from '@/types'

const settings = ref<AiGovernanceSettings | null>(null)
const logs = ref<AiGovernanceLog[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const actionFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const actionTypes = [
  'chatbot',
  'case_qa',
  'timeline_summary',
  'document_summarize',
  'draft_assist',
  'intake_summary',
]

const filteredLogs = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return logs.value
  return logs.value.filter((log) =>
    [
      log.action_type,
      log.bot_context,
      log.status,
      log.output_preview,
      log.user?.name,
      log.output_id,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

function actionLabel(type: string) {
  return type.replace(/_/g, ' ')
}

async function loadSettings() {
  try {
    settings.value = await aiApi.governanceSettings()
  } catch (err) {
    error.value = formatApiError(err, 'AI governance settings are not available.')
  }
}

async function loadLogs() {
  isLoading.value = true
  error.value = null
  try {
    const { logs: rows, meta } = await aiApi.governanceLogs({
      page: page.value,
      per_page: 20,
      action_type: actionFilter.value || undefined,
    })
    logs.value = rows
    lastPage.value = meta?.last_page ?? 1
    total.value = meta?.total ?? rows.length
  } catch (err) {
    error.value = formatApiError(err, 'AI governance logs are not available.')
  } finally {
    isLoading.value = false
  }
}

watch([page, actionFilter], loadLogs)
onMounted(async () => {
  await loadSettings()
  await loadLogs()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="AI governance"
      subtitle="Review AI usage, disclaimers, and audit trail for your organization."
    >
      <template #actions>
        <RouterLink
          v-if="settings"
          to="/ai-assistant"
          class="bw-btn bw-btn-outline bw-btn-sm"
        >
          Open AI assistant
        </RouterLink>
      </template>
    </PageHeader>

    <AiDisclaimerBanner
      v-if="settings?.disclaimer"
      :disclaimer="settings.disclaimer"
    />

    <section v-if="settings" class="bw-card p-4 text-sm">
      <div class="flex flex-wrap gap-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Output label
          </p>
          <p class="mt-1 font-medium text-foreground">{{ settings.label }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Lawyer approval required
          </p>
          <p class="mt-1 font-medium text-foreground">
            {{ settings.requires_lawyer_approval ? 'Yes' : 'No' }}
          </p>
        </div>
        <div class="min-w-[200px] flex-1">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Review statuses
          </p>
          <p class="mt-1 text-muted-foreground">
            {{ settings.review_statuses.join(' → ') }}
          </p>
        </div>
      </div>
    </section>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
        <div class="relative min-w-[220px] flex-1">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
          />
          <input
            v-model="search"
            type="search"
            placeholder="Search logs…"
            class="bw-input pl-9"
            aria-label="Search governance logs"
          />
        </div>
        <label class="text-sm">
          <span class="sr-only">Action type</span>
          <select v-model="actionFilter" class="bw-input" @change="page = 1">
            <option value="">All actions</option>
            <option v-for="type in actionTypes" :key="type" :value="type">
              {{ actionLabel(type) }}
            </option>
          </select>
        </label>
      </div>

      <p v-if="error" class="border-b border-border px-4 py-3 text-sm text-destructive">
        {{ error }}
      </p>

      <Skeleton v-if="isLoading" class="m-4" variant="panel" :rows="6" />

      <EmptyState
        v-else-if="!filteredLogs.length"
        title="No AI activity yet"
        message="AI interactions will appear here once staff use the assistant or case tools."
        :icon="PhShieldCheck"
      />

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
              <th class="px-4 py-3 font-semibold">When</th>
              <th class="px-4 py-3 font-semibold">User</th>
              <th class="px-4 py-3 font-semibold">Action</th>
              <th class="px-4 py-3 font-semibold">Context</th>
              <th class="px-4 py-3 font-semibold">Status</th>
              <th class="px-4 py-3 font-semibold">Preview</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="log in filteredLogs" :key="log.id">
              <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-4 py-3">{{ log.user?.name ?? '—' }}</td>
              <td class="px-4 py-3">
                <span class="font-medium">{{ actionLabel(log.action_type) }}</span>
                <p v-if="log.legal_matter_id" class="text-xs text-muted-foreground">
                  Case #{{ log.legal_matter_id }}
                </p>
              </td>
              <td class="px-4 py-3 text-muted-foreground">{{ log.bot_context ?? '—' }}</td>
              <td class="px-4 py-3">
                <StatusBadge :status="log.status" />
              </td>
              <td class="max-w-[240px] truncate px-4 py-3 text-muted-foreground">
                {{ log.output_preview ?? log.output_id ?? '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar
        :page="page"
        :last-page="lastPage"
        :total="total"
        @update:page="page = $event"
      />
    </section>
  </div>
</template>
