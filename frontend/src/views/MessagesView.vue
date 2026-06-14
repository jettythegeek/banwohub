<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { PhChatCircle, PhMagnifyingGlass, PhPlus } from '@phosphor-icons/vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import MessageThreadPanel from '@/components/messages/MessageThreadPanel.vue'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { Client, LegalMatter } from '@/types'

const route = useRoute()
const clients = ref<Client[]>([])
const cases = ref<LegalMatter[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

const selectedClientId = ref<number | null>(null)
const selectedCaseId = ref<number | null>(null)
const clientSearch = ref('')

const initialThreadId = computed(() => {
  const raw = route.query.thread
  const id = typeof raw === 'string' ? Number(raw) : null
  return id && !Number.isNaN(id) ? id : null
})

onMounted(async () => {
  isLoading.value = true
  error.value = null
  try {
    const [clientsRes, casesRes] = await Promise.all([
      api.get<{ data: Client[] }>('/clients', { params: { per_page: 200 } }),
      api.get<{ data: LegalMatter[] }>('/cases', { params: { per_page: 200 } }),
    ])
    clients.value = clientsRes.data.data ?? (clientsRes.data as unknown as Client[])
    cases.value = casesRes.data.data ?? (casesRes.data as unknown as LegalMatter[])

    if (initialThreadId.value) {
      const { messageThreadsApi } = await import('@/lib/api')
      const thread = await messageThreadsApi.get(initialThreadId.value)
      selectedClientId.value = thread.client_id
      selectedCaseId.value = thread.legal_matter_id ?? null
    }
  } catch (err) {
    error.value = formatApiError(err, 'Messages are not available yet.')
  } finally {
    isLoading.value = false
  }
})

const filteredClients = computed(() => {
  const needle = clientSearch.value.trim().toLowerCase()
  if (!needle) return clients.value
  return clients.value.filter((client) => client.name.toLowerCase().includes(needle))
})

const filteredCases = computed(() =>
  selectedClientId.value
    ? cases.value.filter((c) => {
        const client = c.client as { id?: number } | undefined
        return client?.id === selectedClientId.value
      })
    : cases.value,
)
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Messages" subtitle="Secure client communication linked to cases." />

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
        <div class="relative min-w-[220px] flex-1">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <input
            v-model="clientSearch"
            type="search"
            placeholder="Filter clients…"
            class="bw-input pl-9"
            aria-label="Filter clients"
          />
        </div>
        <select
          v-model="selectedClientId"
          class="bw-select min-w-[200px]"
          aria-label="Filter by client"
        >
          <option :value="null">All clients</option>
          <option v-for="client in filteredClients" :key="client.id" :value="client.id">
            {{ client.name }}
          </option>
        </select>
        <select
          v-model="selectedCaseId"
          class="bw-select min-w-[200px]"
          aria-label="Filter by case"
        >
          <option :value="null">All cases</option>
          <option v-for="matter in filteredCases" :key="matter.id" :value="matter.id">
            {{ matter.title }}
          </option>
        </select>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-if="isLoading" variant="panel" :rows="6" class="p-4" />

      <MessageThreadPanel
        v-else
        mode="staff"
        :client-id="selectedClientId"
        :case-id="selectedCaseId"
        :initial-thread-id="initialThreadId"
      />
    </section>
  </div>
</template>
