<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  PhCaretRight,
  PhDotsThree,
  PhHouse,
  PhPencilSimple,
} from '@phosphor-icons/vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ClientCasesPanel from '@/components/clients/ClientCasesPanel.vue'
import ClientCommunicationPanel from '@/components/clients/ClientCommunicationPanel.vue'
import ClientContactsPanel from '@/components/clients/ClientContactsPanel.vue'
import ClientInvoicesPanel from '@/components/clients/ClientInvoicesPanel.vue'
import ClientProfileSidebar from '@/components/clients/ClientProfileSidebar.vue'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { Client } from '@/types'

const workspaceTabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'cases', label: 'Cases' },
  { key: 'invoices', label: 'Invoices' },
  { key: 'contacts', label: 'Contacts' },
  { key: 'communication', label: 'Communication' },
  { key: 'notes', label: 'Notes' },
] as const

type WorkspaceTab = (typeof workspaceTabs)[number]['key']

const route = useRoute()
const router = useRouter()
const client = ref<Client | null>(null)
const tab = ref<WorkspaceTab>(currentRouteTab())
const isLoading = ref(true)
const error = ref<string | null>(null)
const menuOpen = ref(false)

function isWorkspaceTab(value: unknown): value is WorkspaceTab {
  return typeof value === 'string' && workspaceTabs.some((item) => item.key === value)
}

function currentRouteTab(): WorkspaceTab {
  if (isWorkspaceTab(route.query.tab)) return route.query.tab
  return 'overview'
}

function setTab(nextTab: WorkspaceTab) {
  tab.value = nextTab
  void router.replace({
    path: `/clients/${route.params.id}`,
    query: nextTab === 'overview' ? {} : { tab: nextTab },
  })
}

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  if (menuOpen.value) {
    document.addEventListener('click', closeMenu)
  }
}

function closeMenu() {
  menuOpen.value = false
  document.removeEventListener('click', closeMenu)
}

async function loadClient() {
  isLoading.value = true
  error.value = null
  try {
    const { data } = await api.get<Client>(`/clients/${route.params.id}`)
    client.value = data
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
}

onMounted(loadClient)

onBeforeUnmount(closeMenu)

watch(
  () => route.query.tab,
  () => {
    tab.value = currentRouteTab()
  },
)
</script>

<template>
  <div class="space-y-5">
    <Skeleton v-if="isLoading" variant="detail" />

    <p v-else-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <template v-else-if="client">
      <nav class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
        <RouterLink to="/dashboard" class="inline-flex items-center gap-1 hover:text-foreground">
          <PhHouse class="h-3.5 w-3.5" />
          Home
        </RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <RouterLink to="/clients" class="hover:text-foreground">Clients</RouterLink>
        <PhCaretRight class="h-3.5 w-3.5 shrink-0" />
        <span class="text-foreground">View client</span>
      </nav>

      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
              {{ client.name }}
            </h1>
            <StatusBadge :status="client.status" />
          </div>
          <p v-if="client.client_number" class="mt-1 text-sm tabular-nums text-muted-foreground">
            ID: {{ client.client_number }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            :to="`/clients/${client.id}/edit`"
            class="bw-btn bw-btn-action"
          >
            <PhPencilSimple class="h-4 w-4" />
            Edit profile
          </RouterLink>
          <div class="relative">
            <button
              type="button"
              class="bw-btn bw-btn-outline"
              aria-haspopup="menu"
              :aria-expanded="menuOpen"
              @click.stop="toggleMenu"
            >
              More
              <PhDotsThree class="h-4 w-4" weight="bold" />
            </button>
            <div
              v-if="menuOpen"
              class="absolute right-0 z-20 mt-2 min-w-[11rem] rounded-lg border border-border bg-surface py-1 shadow-md"
              role="menu"
              @click.stop
            >
              <RouterLink
                :to="{ path: '/cases/new', query: { client_id: client.id } }"
                class="block px-4 py-2 text-sm text-foreground hover:bg-surface-muted"
                role="menuitem"
                @click="closeMenu"
              >
                New case
              </RouterLink>
              <RouterLink
                :to="{ path: '/invoices/new', query: { client_id: client.id } }"
                class="block px-4 py-2 text-sm text-foreground hover:bg-surface-muted"
                role="menuitem"
                @click="closeMenu"
              >
                New invoice
              </RouterLink>
              <button
                type="button"
                class="block w-full px-4 py-2 text-left text-sm text-foreground hover:bg-surface-muted"
                role="menuitem"
                @click="setTab('communication'); closeMenu()"
              >
                Log communication
              </button>
            </div>
          </div>
        </div>
      </div>

      <nav class="bw-tabs overflow-x-auto">
        <button
          v-for="t in workspaceTabs"
          :key="t.key"
          type="button"
          class="bw-tab"
          :class="{ 'bw-tab-active': tab === t.key }"
          @click="setTab(t.key)"
        >
          {{ t.label.toUpperCase() }}
        </button>
      </nav>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <template v-if="tab === 'overview'">
            <section class="bw-card p-6">
              <h2 class="text-base font-semibold text-foreground">Client information</h2>
              <dl class="bw-detail-grid mt-5">
                <div class="bw-detail-field">
                  <dt>Full name</dt>
                  <dd>{{ client.name }}</dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Type</dt>
                  <dd>{{ humanize(client.type) }}</dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Email</dt>
                  <dd>{{ client.email || '—' }}</dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Phone</dt>
                  <dd>{{ client.phone || '—' }}</dd>
                </div>
                <div v-if="client.company_name" class="bw-detail-field">
                  <dt>Company</dt>
                  <dd>{{ client.company_name }}</dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Status</dt>
                  <dd>{{ humanize(client.status) }}</dd>
                </div>
                <div class="bw-detail-field sm:col-span-2">
                  <dt>Address</dt>
                  <dd class="whitespace-pre-wrap font-medium">{{ client.address || '—' }}</dd>
                </div>
              </dl>
            </section>

            <section class="bw-card p-6">
              <h2 class="text-base font-semibold text-foreground">Account details</h2>
              <dl class="bw-detail-grid mt-5">
                <div class="bw-detail-field">
                  <dt>Client number</dt>
                  <dd class="tabular-nums">{{ client.client_number || '—' }}</dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Created</dt>
                  <dd>{{ formatDate(client.created_at) }}</dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Portal account</dt>
                  <dd>
                    {{
                      client.portal?.has_account
                        ? client.portal.is_active
                          ? 'Active'
                          : 'Inactive'
                        : 'Not set up'
                    }}
                  </dd>
                </div>
                <div class="bw-detail-field">
                  <dt>Total cases</dt>
                  <dd class="tabular-nums">{{ client.legal_matters_count ?? 0 }}</dd>
                </div>
              </dl>
            </section>
          </template>

          <ClientCasesPanel v-else-if="tab === 'cases'" :client="client" embedded />
          <ClientInvoicesPanel v-else-if="tab === 'invoices'" :client-id="client.id" embedded />
          <ClientContactsPanel v-else-if="tab === 'contacts'" :client-id="client.id" embedded />
          <ClientCommunicationPanel
            v-else-if="tab === 'communication'"
            :client-id="client.id"
            embedded
          />

          <section v-else-if="tab === 'notes'" class="bw-card p-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-base font-semibold text-foreground">Notes</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                  Internal notes visible to your team.
                </p>
              </div>
              <RouterLink
                :to="`/clients/${client.id}/edit`"
                class="bw-btn bw-btn-outline bw-btn-sm"
              >
                <PhPencilSimple class="h-3.5 w-3.5" />
                Edit notes
              </RouterLink>
            </div>
            <p
              v-if="client.notes"
              class="mt-5 whitespace-pre-wrap text-sm leading-relaxed text-foreground"
            >
              {{ client.notes }}
            </p>
            <p v-else class="mt-5 text-sm text-muted-foreground">
              No notes recorded for this client yet.
            </p>
          </section>
        </div>

        <ClientProfileSidebar :client="client" :active-tab="tab" @change-tab="setTab" />
      </div>
    </template>
  </div>
</template>
