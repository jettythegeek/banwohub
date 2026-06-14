<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhListMagnifyingGlass } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { auditApi, usersApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { AuditLog, User } from '@/types'

const logs = ref<AuditLog[]>([])
const users = ref<User[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const userFilter = ref('')
const subjectTypeFilter = ref('')
const actionFilter = ref('')
const fromDate = ref('')
const toDate = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const subjectTypes = [
  { value: '', label: 'All modules' },
  { value: 'case', label: 'Cases' },
  { value: 'client', label: 'Clients' },
  { value: 'document', label: 'Documents' },
  { value: 'auth', label: 'Authentication' },
  { value: 'ai', label: 'AI' },
  { value: 'approval', label: 'Approvals' },
]

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

function formatRecord(log: AuditLog) {
  if (!log.subject_type && !log.subject_id) return '—'
  const type = log.subject_type ?? 'Record'
  const id = log.subject_id ? ` #${log.subject_id}` : ''
  return `${type}${id}`
}

async function loadUsers() {
  try {
    users.value = await usersApi.listActive()
  } catch {
    users.value = []
  }
}

async function loadLogs() {
  isLoading.value = true
  error.value = null
  try {
    const { logs: rows, meta } = await auditApi.list({
      page: page.value,
      per_page: 25,
      user_id: userFilter.value ? Number(userFilter.value) : undefined,
      subject_type: subjectTypeFilter.value || undefined,
      action: actionFilter.value || undefined,
      from_date: fromDate.value || undefined,
      to_date: toDate.value || undefined,
    })
    logs.value = rows
    lastPage.value = meta?.last_page ?? 1
    total.value = meta?.total ?? rows.length
  } catch (err) {
    error.value = formatApiError(err, 'Audit logs are not available.')
    logs.value = []
    total.value = 0
  } finally {
    isLoading.value = false
  }
}

watch([userFilter, subjectTypeFilter, actionFilter, fromDate, toDate], () => {
  page.value = 1
})

watch([page, userFilter, subjectTypeFilter, actionFilter, fromDate, toDate], () => {
  void loadLogs()
})

onMounted(async () => {
  await loadUsers()
  await loadLogs()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Audit trail"
      subtitle="Review important actions across your firm — logins, case changes, documents, and more."
    >
      <template #actions>
        <RouterLink to="/settings?tab=audit" class="bw-btn bw-btn-outline bw-btn-sm">
          Settings
        </RouterLink>
      </template>
    </PageHeader>

    <section class="bw-card overflow-hidden">
      <div class="grid gap-3 border-b border-border p-4 sm:grid-cols-2 lg:grid-cols-5">
        <label class="text-sm">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            User
          </span>
          <select v-model="userFilter" class="bw-input w-full">
            <option value="">All users</option>
            <option v-for="user in users" :key="user.id" :value="String(user.id)">
              {{ user.name }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Module
          </span>
          <select v-model="subjectTypeFilter" class="bw-input w-full">
            <option v-for="type in subjectTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </label>
        <label class="text-sm">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Action
          </span>
          <input
            v-model="actionFilter"
            type="search"
            class="bw-input w-full"
            placeholder="e.g. created, login"
          />
        </label>
        <label class="text-sm">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            From
          </span>
          <input v-model="fromDate" type="date" class="bw-input w-full" />
        </label>
        <label class="text-sm">
          <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            To
          </span>
          <input v-model="toDate" type="date" class="bw-input w-full" />
        </label>
      </div>

      <p v-if="error" class="border-b border-border px-4 py-3 text-sm text-destructive">
        {{ error }}
      </p>

      <Skeleton v-if="isLoading" class="m-4" variant="panel" :rows="6" />

      <EmptyState
        v-else-if="!logs.length"
        title="No audit entries"
        message="Activity will appear here as users work in the system."
        :icon="PhListMagnifyingGlass"
      />

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[880px] text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
              <th class="px-4 py-3 font-semibold">When</th>
              <th class="px-4 py-3 font-semibold">User</th>
              <th class="px-4 py-3 font-semibold">Module</th>
              <th class="px-4 py-3 font-semibold">Action</th>
              <th class="px-4 py-3 font-semibold">Record</th>
              <th class="px-4 py-3 font-semibold">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="log in logs" :key="log.id">
              <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-foreground">{{ log.user?.name ?? '—' }}</p>
                <p v-if="log.user?.email" class="text-xs text-muted-foreground">{{ log.user.email }}</p>
              </td>
              <td class="px-4 py-3 text-muted-foreground">{{ log.module ?? '—' }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-foreground">{{ log.action }}</p>
                <p v-if="log.event" class="text-xs text-muted-foreground">{{ log.event }}</p>
              </td>
              <td class="px-4 py-3 text-muted-foreground">{{ formatRecord(log) }}</td>
              <td class="px-4 py-3 text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
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
