<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { PhMagnifyingGlass, PhPlus, PhUsers } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { PaginatedResponse, User } from '@/types'

const STAFF_ROLES = [
  'Firm Admin',
  'Partner',
  'Lawyer',
  'Paralegal',
  'Secretary',
  'Client',
  'Consultant',
]

const users = ref<User[]>([])
const roles = ref<string[]>(STAFF_ROLES)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const error = ref<string | null>(null)
const inviteError = ref<string | null>(null)
const inviteSuccess = ref<string | null>(null)
const tempPassword = ref<string | null>(null)
const submitting = ref(false)
const showInvite = ref(false)

const invite = ref({
  name: '',
  email: '',
  role: 'Lawyer',
  password: '',
})

async function loadUsers() {
  isLoading.value = true
  error.value = null
  try {
    const { data } = await api.get<PaginatedResponse<User>>('/users', {
      params: { search: search.value || undefined, page: page.value },
    })
    users.value = data.data
    lastPage.value = data.meta.last_page
    total.value = data.meta.total
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
}

async function loadRoles() {
  try {
    const { data } = await api.get<{ roles: string[] }>('/users/roles')
    roles.value = data.roles
  } catch {
    roles.value = STAFF_ROLES
  }
}

async function handleInvite() {
  submitting.value = true
  inviteError.value = null
  inviteSuccess.value = null
  tempPassword.value = null
  try {
    const payload = {
      name: invite.value.name,
      email: invite.value.email,
      role: invite.value.role,
      ...(invite.value.password ? { password: invite.value.password } : {}),
    }
    const { data } = await api.post<
      User & { temporary_password?: string; message?: string }
    >('/users', payload)
    inviteSuccess.value = data.message ?? 'User invited.'
    if (data.temporary_password) {
      tempPassword.value = data.temporary_password
    }
    invite.value = { name: '', email: '', role: 'Lawyer', password: '' }
    showInvite.value = false
    await loadUsers()
  } catch (err) {
    inviteError.value = formatApiError(err)
  } finally {
    submitting.value = false
  }
}

async function updateUserRole(user: User, role: string) {
  try {
    await api.patch(`/users/${user.id}`, { role })
    user.roles = [role]
  } catch (err) {
    error.value = formatApiError(err)
  }
}

async function deactivateUser(user: User) {
  if (!confirm(`Deactivate ${user.name}?`)) return
  try {
    await api.delete(`/users/${user.id}`)
    await loadUsers()
  } catch (err) {
    error.value = formatApiError(err)
  }
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadUsers()
  }, 300)
})

watch(page, loadUsers)
onMounted(async () => {
  await loadRoles()
  await loadUsers()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Team" subtitle="Invite colleagues and assign roles.">
      <template #actions>
        <button type="button" class="bw-btn bw-btn-accent" @click="showInvite = true">
          <PhPlus class="h-4 w-4" weight="bold" />
          Invite user
        </button>
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
              placeholder="Search team…"
              class="bw-input pl-9"
              aria-label="Search team"
            />
          </div>
          <span class="text-sm tabular-nums text-muted-foreground">{{ total }} total</span>
        </div>

        <p v-if="error" class="p-4 text-sm text-destructive">{{ error }}</p>

        <Skeleton v-else-if="isLoading" variant="panel" :rows="5" />

        <template v-else-if="users.length">
          <div class="divide-y divide-border">
            <div
              v-for="u in users"
              :key="u.id"
              class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 text-sm"
            >
              <div class="flex min-w-0 items-center gap-3">
                <AppAvatar :name="u.name" size="sm" />
                <div class="min-w-0">
                  <p class="truncate font-medium text-foreground">{{ u.name }}</p>
                  <p class="truncate text-muted-foreground">{{ u.email }}</p>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <select
                  :value="u.roles?.[0] ?? ''"
                  class="bw-select w-auto"
                  aria-label="Role"
                  @change="updateUserRole(u, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
                <StatusBadge
                  :status="u.is_active ? 'active' : 'inactive'"
                  :label="u.is_active ? 'Active' : 'Inactive'"
                />
                <button
                  v-if="u.is_active"
                  type="button"
                  class="text-xs font-medium text-destructive hover:underline"
                  @click="deactivateUser(u)"
                >
                  Deactivate
                </button>
              </div>
            </div>
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
          :icon="PhUsers"
          title="No team members found"
          message="Invite a colleague to get started."
        />
    </div>

    <BwModal :open="showInvite" title="Invite user" @close="showInvite = false">
      <form id="invite-form" class="space-y-4" @submit.prevent="handleInvite">
        <div>
          <label class="bw-label" for="invite-name">Name</label>
          <input id="invite-name" v-model="invite.name" required class="bw-input" />
        </div>
        <div>
          <label class="bw-label" for="invite-email">Email</label>
          <input id="invite-email" v-model="invite.email" type="email" required class="bw-input" />
        </div>
        <div>
          <label class="bw-label" for="invite-role">Role</label>
          <select id="invite-role" v-model="invite.role" class="bw-select">
            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="invite-password">Password (optional)</label>
          <input
            id="invite-password"
            v-model="invite.password"
            type="password"
            placeholder="Leave blank to auto-generate"
            class="bw-input"
          />
        </div>
        <p v-if="inviteError" class="text-sm text-destructive">{{ inviteError }}</p>
        <p v-if="inviteSuccess" class="text-sm text-success">{{ inviteSuccess }}</p>
        <p
          v-if="tempPassword"
          class="rounded-md border border-border bg-surface p-3 text-sm"
        >
          Temporary password: <strong>{{ tempPassword }}</strong>
        </p>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showInvite = false">
          Cancel
        </button>
        <button
          type="submit"
          form="invite-form"
          class="bw-btn bw-btn-action"
          :disabled="submitting"
        >
          {{ submitting ? 'Sending…' : 'Invite user' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
