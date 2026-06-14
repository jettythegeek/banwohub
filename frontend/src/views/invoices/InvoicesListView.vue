<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PhMagnifyingGlass, PhPlus, PhReceipt } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { api, invoicesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/composables/usePermissions'
import type { Client, Invoice, InvoiceAgingSummary, InvoiceSummary, LegalMatter } from '@/types'

const { can } = usePermissions()
const canCreate = computed(() => can('invoices.create'))

const invoices = ref<Invoice[]>([])
const summary = ref<InvoiceSummary | null>(null)
const agingSummary = ref<InvoiceAgingSummary | null>(null)
const clients = ref<Client[]>([])
const cases = ref<LegalMatter[]>([])
const statusFilter = ref('')
const clientFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const isGenerating = ref(false)
const showGenerateModal = ref(false)
const error = ref<string | null>(null)

const summaryAccents = ['#0A4F5E', '#4A7FD4', '#E07A5F'] as const

const generateForm = ref({
  client_id: '' as string | number,
  legal_matter_id: '' as string | number,
  tax_rate: 0,
  due_date: '',
})

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

const filteredInvoices = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return invoices.value
  return invoices.value.filter((invoice) =>
    [invoice.invoice_number, invoice.client?.name, invoice.case?.title, invoice.status]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

const agingTotal = computed(() => {
  const buckets = agingSummary.value?.buckets ?? []
  return buckets.reduce((sum, bucket) => sum + bucket.amount, 0) || 1
})

const agingSegments = computed(() => {
  const colors = [
    'var(--status-invoice-paid)',
    'var(--status-invoice-pending)',
    'var(--color-accent-700)',
    'var(--status-invoice-overdue)',
  ]
  return (agingSummary.value?.buckets ?? []).map((bucket, index) => ({
    ...bucket,
    width: (bucket.amount / agingTotal.value) * 100,
    color: colors[index] ?? 'var(--status-neutral-fg)',
  }))
})

const footerOutstanding = computed(
  () => agingSummary.value?.total_outstanding ?? summary.value?.outstanding_balance ?? 0,
)

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const filters: Record<string, string | number> = {}
    if (statusFilter.value) filters.status = statusFilter.value
    if (clientFilter.value) filters.client_id = Number(clientFilter.value)
    const result = await invoicesApi.list(filters)
    invoices.value = result.invoices
    summary.value = result.summary
    try {
      agingSummary.value = await invoicesApi.agingSummary()
    } catch {
      agingSummary.value = null
    }
  } catch (err) {
    error.value = formatApiError(err, 'Invoices are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function loadClients() {
  try {
    const { data } = await api.get<{ data: Client[] }>('/clients', { params: { per_page: 100 } })
    clients.value = data.data
  } catch {
    clients.value = []
  }
}

async function loadCases(clientId?: number) {
  if (!clientId) {
    cases.value = []
    return
  }
  try {
    const { data } = await api.get<{ data: LegalMatter[] }>('/cases', {
      params: { per_page: 100, client_id: clientId },
    })
    cases.value = data.data
  } catch {
    cases.value = []
  }
}

async function generateFromTime() {
  if (!generateForm.value.client_id) return
  isGenerating.value = true
  error.value = null
  try {
    const invoice = await invoicesApi.generateFromTimeEntries({
      client_id: Number(generateForm.value.client_id),
      legal_matter_id: generateForm.value.legal_matter_id
        ? Number(generateForm.value.legal_matter_id)
        : null,
      tax_rate: generateForm.value.tax_rate || 0,
      due_date: generateForm.value.due_date || null,
    })
    showGenerateModal.value = false
    await load()
    window.location.href = `/invoices/${invoice.id}`
  } catch (err) {
    error.value = formatApiError(err, 'We could not generate an invoice from time entries.')
  } finally {
    isGenerating.value = false
  }
}

watch(statusFilter, load)
watch(clientFilter, load)
watch(
  () => generateForm.value.client_id,
  (value) => {
    generateForm.value.legal_matter_id = ''
    void loadCases(value ? Number(value) : undefined)
  },
)

onMounted(async () => {
  await Promise.all([load(), loadClients()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Invoices"
      subtitle="Create invoices from billable time, send to clients, and record payments."
    >
      <template #actions>
        <button
          v-if="canCreate"
          type="button"
          class="bw-btn bw-btn-outline"
          @click="showGenerateModal = true"
        >
          Generate from time
        </button>
        <RouterLink v-if="canCreate" to="/invoices/new" class="bw-btn bw-btn-accent">
          <PhPlus class="h-4 w-4" weight="bold" aria-hidden="true" />
          New invoice
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="summary" class="grid gap-4 sm:grid-cols-3">
      <div
        v-for="(card, index) in [
          { label: 'Outstanding', value: formatCurrency(summary.outstanding_balance) },
          { label: 'Unpaid', value: summary.unpaid_count },
          { label: 'Total billed', value: formatCurrency(summary.total_billed) },
        ]"
        :key="card.label"
        class="bw-card overflow-hidden"
      >
        <div class="h-1" :style="{ background: summaryAccents[index] }" aria-hidden="true" />
        <div class="p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {{ card.label }}
          </p>
          <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ card.value }}</p>
        </div>
      </div>
    </div>

    <section v-if="agingSummary" class="bw-card p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 class="font-semibold text-foreground">Aging summary</h2>
          <p class="text-sm text-muted-foreground">
            Outstanding balances by days past due.
          </p>
        </div>
        <p class="text-lg font-semibold tabular-nums text-foreground">
          {{ formatCurrency(agingSummary.total_outstanding) }}
        </p>
      </div>
      <div class="mt-4 h-3 overflow-hidden rounded-full bg-muted">
        <div class="flex h-full w-full">
          <div
            v-for="segment in agingSegments"
            :key="segment.label"
            class="h-full transition-all"
            :style="{ width: `${segment.width}%`, backgroundColor: segment.color }"
            :title="`${segment.label}: ${formatCurrency(segment.amount)}`"
          />
        </div>
      </div>
      <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="(segment, index) in agingSegments"
          :key="segment.label"
          class="rounded-lg border border-border bg-surface px-3 py-2.5"
        >
          <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-full"
              :style="{ backgroundColor: segment.color }"
            />
            {{ segment.label }}
          </div>
          <p class="mt-1 text-sm font-semibold tabular-nums text-foreground">
            {{ formatCurrency(segment.amount) }}
          </p>
          <p class="text-xs tabular-nums text-muted-foreground">{{ segment.count }} invoices</p>
        </div>
      </div>
    </section>

    <BwModal
      :open="showGenerateModal"
      title="Generate from billable time"
      size="md"
      @close="showGenerateModal = false"
    >
      <p class="mb-4 text-sm text-muted-foreground">
        Pull approved, uninvoiced time entries for a client or case.
      </p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="bw-label" for="gen-client">Client</label>
          <select id="gen-client" v-model="generateForm.client_id" class="bw-select">
            <option value="">Select client</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">
              {{ client.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="gen-case">Case (optional)</label>
          <select
            id="gen-case"
            v-model="generateForm.legal_matter_id"
            class="bw-select"
            :disabled="!generateForm.client_id"
          >
            <option value="">All cases</option>
            <option v-for="matter in cases" :key="matter.id" :value="matter.id">
              {{ matter.title }}
            </option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="gen-tax">Tax rate (%)</label>
          <input id="gen-tax" v-model.number="generateForm.tax_rate" type="number" min="0" class="bw-input" />
        </div>
        <div>
          <label class="bw-label" for="gen-due">Due date</label>
          <input id="gen-due" v-model="generateForm.due_date" type="date" class="bw-input" />
        </div>
      </div>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showGenerateModal = false">
          Cancel
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-action"
          :disabled="!generateForm.client_id || isGenerating"
          @click="generateFromTime"
        >
          {{ isGenerating ? 'Generating…' : 'Generate invoice' }}
        </button>
      </template>
    </BwModal>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
        <div class="relative min-w-[220px] flex-1">
          <PhMagnifyingGlass
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
          />
          <input
            v-model="search"
            type="search"
            placeholder="Search invoice number, client, or case…"
            class="bw-input pl-9"
            aria-label="Search invoices"
          />
        </div>
        <select v-model="statusFilter" class="bw-select w-auto" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="sent">Sent</option>
          <option value="partial">Partial</option>
          <option value="paid">Paid</option>
          <option value="overdue">Overdue</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="clientFilter" class="bw-select w-auto" aria-label="Filter by client">
          <option value="">All clients</option>
          <option v-for="client in clients" :key="client.id" :value="client.id">
            {{ client.name }}
          </option>
        </select>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>
      <Skeleton v-else-if="isLoading" variant="panel" :rows="4" />

      <div v-else-if="filteredInvoices.length" class="divide-y divide-border">
        <RouterLink
          v-for="invoice in filteredInvoices"
          :key="invoice.id"
          :to="`/invoices/${invoice.id}`"
          class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 transition-colors hover:bg-surface-muted"
        >
          <div class="min-w-0">
            <p class="font-medium text-foreground">{{ invoice.invoice_number }}</p>
            <p class="text-sm text-muted-foreground">
              {{ invoice.client?.name || 'Client' }}
              <span v-if="invoice.case"> · {{ invoice.case.title }}</span>
            </p>
            <p class="text-xs text-muted-foreground">
              Issued {{ formatDate(invoice.issue_date) }}
              <span v-if="invoice.due_date"> · Due {{ formatDate(invoice.due_date) }}</span>
            </p>
          </div>
          <div class="flex items-center gap-4 text-sm">
            <div class="text-right">
              <p class="font-medium tabular-nums text-foreground">
                {{ formatCurrency(invoice.total_amount, invoice.currency) }}
              </p>
              <p v-if="invoice.balance_due > 0" class="text-xs tabular-nums text-muted-foreground">
                Due {{ formatCurrency(invoice.balance_due, invoice.currency) }}
              </p>
            </div>
            <StatusBadge :status="invoice.status" />
          </div>
        </RouterLink>
      </div>

      <EmptyState
        v-else
        :icon="PhReceipt"
        title="No invoices yet"
        description="Create a manual invoice or generate one from approved billable time."
      />

      <div
        v-if="filteredInvoices.length"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface px-6 py-3 text-sm"
      >
        <p class="text-muted-foreground">
          <span class="font-medium text-foreground">{{ filteredInvoices.length }}</span>
          invoice{{ filteredInvoices.length === 1 ? '' : 's' }}
          ·
          <span class="tabular-nums">{{ formatCurrency(footerOutstanding) }}</span>
          outstanding
        </p>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" disabled>
            Send reminders
          </button>
          <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" disabled>
            Record payment
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
