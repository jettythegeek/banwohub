<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppAvatar from '@/components/common/AppAvatar.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import AiProvidersPanel from '@/components/settings/AiProvidersPanel.vue'
import IntegrationsPanel from '@/components/settings/IntegrationsPanel.vue'
import TwoFactorSecurityPanel from '@/components/settings/TwoFactorSecurityPanel.vue'
import { usePermissions } from '@/composables/usePermissions'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { useAuthStore } from '@/stores/auth'
import type { Organization } from '@/types'

const auth = useAuthStore()
const { can } = usePermissions()
const route = useRoute()

const org = ref<Organization | null>(null)
const orgForm = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
  practice_areas_text: '',
  case_types_text: '',
})
const orgLoading = ref(false)
const orgSaving = ref(false)
const orgError = ref<string | null>(null)
const orgSuccess = ref<string | null>(null)

const canManageOrg = computed(() => can('organization.manage'))
const canManageUsers = computed(() => can('users.manage'))
const canViewAiGovernance = computed(() => can('ai.governance.view'))
const canManageAiProviders = computed(() => can('ai.providers.manage'))
const canViewAudit = computed(() => can('audit.view'))

onMounted(async () => {
  if (!canManageOrg.value) return
  orgLoading.value = true
  try {
    const { data } = await api.get<Organization>('/organization')
    org.value = data
    orgForm.value = {
      name: data.name,
      email: data.email ?? '',
      phone: data.phone ?? '',
      address: data.address ?? '',
      practice_areas_text: (data.practice_areas ?? []).join(', '),
      case_types_text: (data.case_types ?? []).join(', '),
    }
  } catch (err) {
    orgError.value = formatApiError(err)
  } finally {
    orgLoading.value = false
  }
})

function parseList(text: string): string[] {
  return text
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
}

async function saveOrganization() {
  orgSaving.value = true
  orgError.value = null
  orgSuccess.value = null
  try {
    const { data } = await api.put<Organization>('/organization', {
      name: orgForm.value.name,
      email: orgForm.value.email || null,
      phone: orgForm.value.phone || null,
      address: orgForm.value.address || null,
      practice_areas: parseList(orgForm.value.practice_areas_text),
      case_types: parseList(orgForm.value.case_types_text),
    })
    org.value = data
    orgSuccess.value = 'Organization settings saved.'
  } catch (err) {
    orgError.value = formatApiError(err)
  } finally {
    orgSaving.value = false
  }
}

const activeTab = computed(() => {
  const t = route.query.tab
  if (t === 'integrations' && canManageOrg.value) return 'integrations'
  if (t === 'organization' && canManageOrg.value) return 'organization'
  if (t === 'ai-providers' && canManageAiProviders.value) return 'ai-providers'
  if (t === 'audit' && canViewAudit.value) return 'audit'
  if (t === 'security') return 'security'
  return 'account'
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Settings" subtitle="Your account and workspace preferences." />

    <div class="overflow-x-auto border-b border-border">
      <div class="flex min-w-max gap-5">
        <RouterLink
          to="/settings"
          class="bw-tab"
          :class="{ 'bw-tab-active': activeTab === 'account' }"
        >
          My account
        </RouterLink>
        <RouterLink
          to="/settings?tab=security"
          class="bw-tab"
          :class="{ 'bw-tab-active': activeTab === 'security' }"
        >
          Security
        </RouterLink>
        <RouterLink
          v-if="canManageOrg"
          to="/settings?tab=organization"
          class="bw-tab"
          :class="{ 'bw-tab-active': activeTab === 'organization' }"
        >
          Organization
        </RouterLink>
        <RouterLink
          v-if="canManageOrg"
          to="/settings?tab=integrations"
          class="bw-tab"
          :class="{ 'bw-tab-active': activeTab === 'integrations' }"
        >
          Integrations
        </RouterLink>
        <RouterLink
          v-if="canManageAiProviders"
          to="/settings?tab=ai-providers"
          class="bw-tab"
          :class="{ 'bw-tab-active': activeTab === 'ai-providers' }"
        >
          AI providers
        </RouterLink>
        <RouterLink
          v-if="canViewAudit"
          to="/audit"
          class="bw-tab"
          :class="{ 'bw-tab-active': activeTab === 'audit' }"
        >
          Audit
        </RouterLink>
        <RouterLink v-if="canManageUsers" to="/settings/users" class="bw-tab">
          Team
        </RouterLink>
        <RouterLink
          v-if="canViewAiGovernance"
          to="/ai-governance"
          class="bw-tab"
        >
          AI governance
        </RouterLink>
      </div>
    </div>

    <TwoFactorSecurityPanel v-if="activeTab === 'security'" />

    <div v-else-if="activeTab === 'account'" class="bw-card max-w-xl p-6">
      <div class="flex items-center gap-4">
        <AppAvatar :name="auth.user?.name" size="lg" />
        <div class="min-w-0">
          <p class="truncate font-semibold text-foreground">{{ auth.user?.name }}</p>
          <p class="truncate text-sm text-muted-foreground">{{ auth.user?.email }}</p>
        </div>
      </div>
      <dl class="mt-6 grid gap-4 border-t border-border pt-6 text-sm sm:grid-cols-2">
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Name
          </dt>
          <dd class="mt-1 font-medium text-foreground">{{ auth.user?.name }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Email
          </dt>
          <dd class="mt-1 font-medium text-foreground">{{ auth.user?.email }}</dd>
        </div>
        <div v-if="auth.user?.roles?.length">
          <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Role
          </dt>
          <dd class="mt-1 font-medium text-foreground">
            {{ auth.user.roles.join(', ') }}
          </dd>
        </div>
      </dl>
    </div>

    <AiProvidersPanel v-else-if="activeTab === 'ai-providers'" />

    <IntegrationsPanel v-else-if="activeTab === 'integrations'" />

    <div v-else-if="activeTab === 'audit'" class="bw-card max-w-2xl p-6">
      <h2 class="font-semibold text-foreground">Audit trail</h2>
      <p class="mt-2 text-sm text-muted-foreground">
        Review firm-wide activity logs — user logins, case changes, documents, and more.
      </p>
      <RouterLink to="/audit" class="bw-btn bw-btn-primary mt-5 inline-flex">
        Open audit explorer
      </RouterLink>
    </div>

    <template v-else-if="activeTab === 'organization'">
      <Skeleton v-if="orgLoading" variant="form" :rows="5" />
      <form
        v-else
        class="bw-card max-w-2xl space-y-5 p-6"
        @submit.prevent="saveOrganization"
      >
        <h2 class="font-semibold text-foreground">Organization settings</h2>
        <div>
          <label class="bw-label" for="org-name">Display name</label>
          <input id="org-name" v-model="orgForm.name" required class="bw-input" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="org-email">Contact email</label>
            <input id="org-email" v-model="orgForm.email" type="email" class="bw-input" />
          </div>
          <div>
            <label class="bw-label" for="org-phone">Phone</label>
            <input id="org-phone" v-model="orgForm.phone" class="bw-input" />
          </div>
        </div>
        <div>
          <label class="bw-label" for="org-address">Address</label>
          <textarea id="org-address" v-model="orgForm.address" rows="2" class="bw-textarea" />
        </div>
        <div>
          <label class="bw-label" for="org-areas">Practice areas (comma-separated)</label>
          <input id="org-areas" v-model="orgForm.practice_areas_text" class="bw-input" />
        </div>
        <div>
          <label class="bw-label" for="org-types">Case types (comma-separated)</label>
          <input id="org-types" v-model="orgForm.case_types_text" class="bw-input" />
        </div>
        <p v-if="orgError" class="text-sm text-destructive">{{ orgError }}</p>
        <p v-if="orgSuccess" class="text-sm text-success">{{ orgSuccess }}</p>
        <div class="border-t border-border pt-5">
          <button type="submit" class="bw-btn bw-btn-primary" :disabled="orgSaving">
            {{ orgSaving ? 'Saving…' : 'Save settings' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>
