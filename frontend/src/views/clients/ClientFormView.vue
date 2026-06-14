<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { PhCaretRight, PhHouse } from '@phosphor-icons/vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ClientProfileSidebar from '@/components/clients/ClientProfileSidebar.vue'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { Client } from '@/types'

type PortalPasswordOption = 'manual' | 'email'

const route = useRoute()
const router = useRouter()
const clientId = computed(() =>
  route.params.id && route.params.id !== 'new' ? Number(route.params.id) : null,
)
const isEdit = computed(() => clientId.value !== null)

const clientSnapshot = ref<Client | null>(null)
const clientNumber = ref<string | null>(null)
const portalStatus = ref<Client['portal']>(undefined)
const form = ref({
  type: 'individual',
  name: '',
  email: '',
  phone: '',
  company_name: '',
  address: '',
  status: 'active',
  notes: '',
})
const createPortalAccount = ref(false)
const resetPortalPassword = ref(false)
const portalPasswordOption = ref<PortalPasswordOption>('email')
const portalPassword = ref('')
const isLoading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)

const hasPortalAccount = computed(() => portalStatus.value?.has_account === true)
const showPortalSetup = computed(
  () => (!isEdit.value || !hasPortalAccount.value) && createPortalAccount.value,
)
const showPortalReset = computed(() => isEdit.value && hasPortalAccount.value && resetPortalPassword.value)
const emailRequiredForPortal = computed(
  () => createPortalAccount.value || showPortalReset.value,
)

const sidebarClient = computed<Client | null>(() => {
  if (!isEdit.value) return null
  return {
    ...(clientSnapshot.value ?? { id: clientId.value! }),
    id: clientId.value!,
    type: form.value.type,
    name: form.value.name || clientSnapshot.value?.name || 'Client',
    email: form.value.email,
    phone: form.value.phone,
    company_name: form.value.company_name,
    address: form.value.address,
    status: form.value.status,
    notes: form.value.notes,
    client_number: clientNumber.value,
    legal_matters_count: clientSnapshot.value?.legal_matters_count,
    open_legal_matters_count: clientSnapshot.value?.open_legal_matters_count,
    invoices_count: clientSnapshot.value?.invoices_count,
    contacts_count: clientSnapshot.value?.contacts_count,
    communication_logs_count: clientSnapshot.value?.communication_logs_count,
  }
})

watch(createPortalAccount, (enabled) => {
  if (!enabled) {
    portalPassword.value = ''
  }
})

watch(resetPortalPassword, (enabled) => {
  if (!enabled) {
    portalPassword.value = ''
  }
})

onMounted(async () => {
  if (!isEdit.value) return
  isLoading.value = true
  try {
    const { data } = await api.get<Client>(`/clients/${clientId.value}`)
    clientSnapshot.value = data
    clientNumber.value = data.client_number ?? null
    portalStatus.value = data.portal
    form.value = {
      type: data.type,
      name: data.name,
      email: data.email ?? '',
      phone: data.phone ?? '',
      company_name: data.company_name ?? '',
      address: data.address ?? '',
      status: data.status,
      notes: data.notes ?? '',
    }
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
})

function buildPayload() {
  const payload: Record<string, unknown> = { ...form.value }

  if (isEdit.value && hasPortalAccount.value) {
    if (resetPortalPassword.value) {
      payload.reset_portal_password = true
      payload.portal_password_option = portalPasswordOption.value
      if (portalPasswordOption.value === 'manual') {
        payload.portal_password = portalPassword.value
      }
    }
  } else if (createPortalAccount.value) {
    payload.create_portal_account = true
    payload.portal_password_option = portalPasswordOption.value
    if (portalPasswordOption.value === 'manual') {
      payload.portal_password = portalPassword.value
    }
  }

  return payload
}

async function handleSubmit() {
  if (createPortalAccount.value && !form.value.email.trim()) {
    error.value = 'An email address is required to create a portal account.'
    return
  }

  if (portalPasswordOption.value === 'manual') {
    const needsPassword =
      createPortalAccount.value || (isEdit.value && resetPortalPassword.value)
    if (needsPassword && !portalPassword.value.trim()) {
      error.value = 'Enter a portal password or choose to send it by email.'
      return
    }
  }

  submitting.value = true
  error.value = null
  try {
    const payload = buildPayload()
    if (isEdit.value) {
      await api.patch(`/clients/${clientId.value}`, payload)
      await router.push(`/clients/${clientId.value}`)
    } else {
      const { data } = await api.post<Client>('/clients', payload)
      await router.push(`/clients/${data.id}`)
    }
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <template v-if="isEdit">
      <nav class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
        <RouterLink to="/dashboard" class="inline-flex items-center gap-1 hover:text-foreground">
          <PhHouse class="h-3.5 w-3.5" />
          Home
        </RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <RouterLink to="/clients" class="hover:text-foreground">Clients</RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <RouterLink :to="`/clients/${clientId}`" class="hover:text-foreground">
          {{ form.name || 'Client' }}
        </RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <span class="text-foreground">Edit client</span>
      </nav>

      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
              {{ form.name || 'Edit client' }}
            </h1>
            <StatusBadge :status="form.status" />
          </div>
          <p v-if="clientNumber" class="mt-1 text-sm tabular-nums text-muted-foreground">
            ID: {{ clientNumber }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="submit"
            form="client-edit-form"
            class="bw-btn bw-btn-action"
            :disabled="submitting || isLoading"
          >
            {{ submitting ? 'Saving…' : 'Save changes' }}
          </button>
          <RouterLink :to="`/clients/${clientId}`" class="bw-btn bw-btn-outline">
            Cancel
          </RouterLink>
        </div>
      </div>
    </template>

    <template v-else>
      <nav class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
        <RouterLink to="/dashboard" class="inline-flex items-center gap-1 hover:text-foreground">
          <PhHouse class="h-3.5 w-3.5" />
          Home
        </RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <RouterLink to="/clients" class="hover:text-foreground">Clients</RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <span class="text-foreground">New client</span>
      </nav>

      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight text-foreground">New client</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Keep contact details up to date for your team.
          </p>
        </div>
        <div class="flex gap-2">
          <button
            type="submit"
            form="client-edit-form"
            class="bw-btn bw-btn-action"
            :disabled="submitting"
          >
            {{ submitting ? 'Saving…' : 'Save client' }}
          </button>
          <RouterLink to="/clients" class="bw-btn bw-btn-outline">Cancel</RouterLink>
        </div>
      </div>
    </template>

    <Skeleton v-if="isLoading" variant="form" :rows="6" />

    <form
      v-else
      id="client-edit-form"
      class="grid gap-6"
      :class="isEdit ? 'lg:grid-cols-3' : 'mx-auto max-w-2xl'"
      @submit.prevent="handleSubmit"
    >
      <div class="space-y-6" :class="isEdit ? 'lg:col-span-2' : ''">
        <section class="bw-card p-6">
          <h2 class="text-base font-semibold text-foreground">Client information</h2>

          <div v-if="clientNumber" class="mt-4 rounded-lg border border-border bg-surface px-4 py-3 text-sm">
            <span class="text-muted-foreground">Client number</span>
            <span class="ml-2 font-medium tabular-nums text-foreground">{{ clientNumber }}</span>
          </div>

          <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
              <label class="bw-label" for="client-type">Type</label>
              <select id="client-type" v-model="form.type" class="bw-select">
                <option value="individual">Individual</option>
                <option value="company">Company</option>
              </select>
            </div>
            <div>
              <label class="bw-label" for="client-status">Status</label>
              <select id="client-status" v-model="form.status" class="bw-select">
                <option value="active">Active</option>
                <option value="prospect">Prospect</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="bw-label" for="client-name">Full name</label>
              <input id="client-name" v-model="form.name" required class="bw-input" />
            </div>
            <div>
              <label class="bw-label" for="client-email">
                Email
                <span v-if="emailRequiredForPortal" class="text-destructive">*</span>
              </label>
              <input
                id="client-email"
                v-model="form.email"
                type="email"
                class="bw-input"
                :required="emailRequiredForPortal"
              />
            </div>
            <div>
              <label class="bw-label" for="client-phone">Phone</label>
              <input id="client-phone" v-model="form.phone" class="bw-input" />
            </div>
            <div class="sm:col-span-2">
              <label class="bw-label" for="client-company">Company</label>
              <input id="client-company" v-model="form.company_name" class="bw-input" />
            </div>
            <div class="sm:col-span-2">
              <label class="bw-label" for="client-address">Address</label>
              <textarea id="client-address" v-model="form.address" rows="2" class="bw-textarea" />
            </div>
          </div>
        </section>

        <section class="bw-card p-6">
          <h2 class="text-base font-semibold text-foreground">Notes</h2>
          <div class="mt-4">
            <label class="bw-label" for="client-notes">Internal notes</label>
            <textarea id="client-notes" v-model="form.notes" rows="4" class="bw-textarea" />
          </div>
        </section>

        <section class="bw-card space-y-4 p-6">
          <div>
            <h2 class="text-base font-semibold text-foreground">Client portal</h2>
            <p class="mt-1 text-xs text-muted-foreground">
              Clients sign in at <span class="font-medium">/portal/login</span> with their email and password.
            </p>
          </div>

          <div
            v-if="hasPortalAccount"
            class="rounded-lg border border-border bg-surface px-4 py-3 text-sm"
          >
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium text-foreground">Portal active</span>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="portalStatus?.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ portalStatus?.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <p class="mt-2 text-muted-foreground">
              Login email:
              <span class="font-medium text-foreground">{{ portalStatus?.login_email }}</span>
            </p>
          </div>

          <label v-if="!hasPortalAccount" class="flex items-center gap-2 text-sm">
            <input v-model="createPortalAccount" type="checkbox" class="bw-checkbox" />
            Create portal account
          </label>

          <label v-else class="flex items-center gap-2 text-sm">
            <input v-model="resetPortalPassword" type="checkbox" class="bw-checkbox" />
            Reset portal password
          </label>

          <div v-if="showPortalSetup || showPortalReset" class="space-y-4 rounded-lg border border-border bg-surface/50 p-4">
            <fieldset class="space-y-2">
              <legend class="bw-label mb-2">Password</legend>
              <label class="flex items-center gap-2 text-sm">
                <input
                  v-model="portalPasswordOption"
                  type="radio"
                  value="manual"
                  name="portal-password-option"
                  class="border-border text-primary"
                />
                Admin sets password
              </label>
              <label class="flex items-center gap-2 text-sm">
                <input
                  v-model="portalPasswordOption"
                  type="radio"
                  value="email"
                  name="portal-password-option"
                  class="border-border text-primary"
                />
                Send password to client by email
              </label>
            </fieldset>

            <div v-if="portalPasswordOption === 'manual'">
              <label class="bw-label" for="portal-password">Portal password</label>
              <input
                id="portal-password"
                v-model="portalPassword"
                type="password"
                autocomplete="new-password"
                class="bw-input"
                minlength="8"
              />
            </div>

            <p v-else class="text-xs text-muted-foreground">
              A secure password will be generated and emailed to the client with portal login instructions.
            </p>
          </div>
        </section>

        <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      </div>

      <ClientProfileSidebar
        v-if="isEdit && sidebarClient"
        :client="sidebarClient"
        edit-mode
      />
    </form>
  </div>
</template>
