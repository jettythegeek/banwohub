<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  PhCaretDown,
  PhDotsThreeVertical,
  PhDownloadSimple,
  PhEye,
  PhFunnel,
  PhMagnifyingGlass,
  PhPencilSimple,
  PhPlus,
  PhReceipt,
  PhTrash,
} from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { invoicesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { Invoice } from '@/types'

const props = defineProps<{
  caseId: number
  clientId?: number | null
  embedded?: boolean
}>()

const { can } = usePermissions()
const canCreate = computed(() => can('invoices.create'))
const canDelete = computed(() => can('invoices.delete'))

const invoices = ref<Invoice[]>([])
const isLoading = ref(true)
const isGenerating = ref(false)
const showGenerateModal = ref(false)
const showFilter = ref(false)
const error = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const paymentFilter = ref('')
const dateRange = ref('all')
const page = ref(1)
const perPage = ref(10)

const dateRangeOptions = [
  { value: 'all', label: 'All time' },
  { value: 'month', label: 'This month' },
  { value: '30d', label: 'Last 30 days' },
  { value: 'year', label: 'This year' },
] as const

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

function paymentStatus(invoice: Invoice): string {
  if (invoice.status === 'paid' || invoice.balance_due <= 0) return 'paid'
  if (invoice.status === 'overdue') return 'overdue'
  if (
    invoice.due_date &&
    new Date(invoice.due_date) < new Date() &&
    invoice.balance_due > 0
  ) {
    return 'overdue'
  }
  if (invoice.status === 'draft') return 'draft'
  return 'pending'
}

function lineItemsSummary(invoice: Invoice): string {
  const items = invoice.line_items
  if (!items?.length) return invoice.notes?.trim() || '—'
  const first = items[0]?.description?.trim() || 'Line items'
  if (items.length === 1) return first
  return `${first} +${items.length - 1} more`
}

function matchesDateRange(invoice: Invoice): boolean {
  if (dateRange.value === 'all' || !invoice.issue_date) return true
  const issued = new Date(invoice.issue_date)
  const now = new Date()
  if (dateRange.value === 'month') {
    return (
      issued.getFullYear() === now.getFullYear() &&
      issued.getMonth() === now.getMonth()
    )
  }
  if (dateRange.value === '30d') {
    const cutoff = new Date()
    cutoff.setDate(cutoff.getDate() - 30)
    return issued >= cutoff
  }
  if (dateRange.value === 'year') {
    return issued.getFullYear() === now.getFullYear()
  }
  return true
}

const filteredInvoices = computed(() => {
  const needle = search.value.trim().toLowerCase()
  return invoices.value.filter((invoice) => {
    if (statusFilter.value && invoice.status !== statusFilter.value) return false
    if (paymentFilter.value && paymentStatus(invoice) !== paymentFilter.value) return false
    if (!matchesDateRange(invoice)) return false
    if (!needle) return true
    return [
      invoice.invoice_number,
      invoice.client?.name,
      invoice.case?.title,
      invoice.status,
      lineItemsSummary(invoice),
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle))
  })
})

const summaryStats = computed(() => {
  const all = invoices.value
  const total = all.length
  let paid = 0
  let pending = 0
  let overdue = 0
  let totalBilled = 0

  for (const invoice of all) {
    totalBilled += invoice.total_amount
    const payment = paymentStatus(invoice)
    if (payment === 'paid') paid += 1
    else if (payment === 'overdue') overdue += 1
    else pending += 1
  }

  const paidPct = total > 0 ? Math.round((paid / total) * 100) : 0
  return { total, paid, pending, overdue, totalBilled, paidPct }
})

const lastPage = computed(() =>
  Math.max(1, Math.ceil(filteredInvoices.value.length / perPage.value)),
)

const paginatedInvoices = computed(() => {
  const start = (page.value - 1) * perPage.value
  return filteredInvoices.value.slice(start, start + perPage.value)
})

const showingFrom = computed(() =>
  filteredInvoices.value.length ? (page.value - 1) * perPage.value + 1 : 0,
)

const showingTo = computed(() =>
  Math.min(page.value * perPage.value, filteredInvoices.value.length),
)

const pageNumbers = computed(() => {
  const total = lastPage.value
  const current = page.value
  const pages: number[] = []
  const window = 5
  let start = Math.max(1, current - Math.floor(window / 2))
  const end = Math.min(total, start + window - 1)
  start = Math.max(1, end - window + 1)
  for (let i = start; i <= end; i += 1) pages.push(i)
  return pages
})

const summaryCards = computed(() => [
  {
    key: 'total',
    label: 'Total invoices',
    value: summaryStats.value.total,
    subtitle: `Total billed: ${formatCurrency(summaryStats.value.totalBilled)}`,
    hero: true,
    trend: summaryStats.value.paidPct,
  },
  {
    key: 'paid',
    label: 'Paid',
    value: summaryStats.value.paid,
    subtitle: formatCurrency(
      invoices.value
        .filter((i) => paymentStatus(i) === 'paid')
        .reduce((sum, i) => sum + i.total_amount, 0),
    ),
    hero: false,
    trend: null,
  },
  {
    key: 'pending',
    label: 'Pending',
    value: summaryStats.value.pending,
    subtitle: formatCurrency(
      invoices.value
        .filter((i) => paymentStatus(i) === 'pending' || paymentStatus(i) === 'draft')
        .reduce((sum, i) => sum + i.balance_due, 0),
    ),
    hero: false,
    trend: null,
  },
  {
    key: 'overdue',
    label: 'Overdue',
    value: summaryStats.value.overdue,
    subtitle: formatCurrency(
      invoices.value
        .filter((i) => paymentStatus(i) === 'overdue')
        .reduce((sum, i) => sum + i.balance_due, 0),
    ),
    hero: false,
    trend: null,
  },
])

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const { invoices: list } = await invoicesApi.list({ legal_matter_id: props.caseId })
    invoices.value = list
  } catch (err) {
    error.value = formatApiError(err, 'Invoices are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function generateFromTime() {
  if (!props.clientId) {
    error.value = 'This case has no linked client.'
    return
  }
  isGenerating.value = true
  error.value = null
  try {
    const invoice = await invoicesApi.generateFromTimeEntries({
      client_id: props.clientId,
      legal_matter_id: props.caseId,
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

async function removeInvoice(invoice: Invoice) {
  if (!canDelete.value) return
  if (!confirm(`Delete invoice ${invoice.invoice_number}? This cannot be undone.`)) return
  error.value = null
  try {
    await invoicesApi.delete(invoice.id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this invoice.')
  }
}

function exportCsv() {
  const rows = filteredInvoices.value
  if (!rows.length) return
  const header = [
    'Invoice #',
    'Client',
    'Issue date',
    'Description',
    'Total',
    'Payment status',
    'Invoice status',
  ]
  const body = rows.map((invoice) => [
    invoice.invoice_number,
    invoice.client?.name ?? '',
    invoice.issue_date,
    lineItemsSummary(invoice),
    String(invoice.total_amount),
    humanize(paymentStatus(invoice)),
    humanize(invoice.status),
  ])
  const csv = [header, ...body]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `case-${props.caseId}-invoices.csv`
  link.click()
  URL.revokeObjectURL(url)
}

watch([search, statusFilter, paymentFilter, dateRange, perPage], () => {
  page.value = 1
})

watch(page, (value) => {
  if (value > lastPage.value) page.value = lastPage.value
})

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      v-if="!embedded"
      title="Case invoices"
      subtitle="Billing and payment status for this matter."
    />
    <div v-else>
      <h2 class="text-xl font-semibold tracking-tight text-foreground">Case invoices</h2>
      <p class="mt-1 text-sm text-muted-foreground">Billing and payment status for this matter.</p>
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="6" />

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="card in summaryCards"
          :key="card.key"
          class="relative overflow-hidden rounded-xl border shadow-sm"
          :class="
            card.hero
              ? 'border-primary-800 bg-primary-800 text-white'
              : 'border-border bg-surface'
          "
        >
          <button
            type="button"
            class="absolute right-3 top-3 rounded-md p-1 opacity-60 transition-opacity hover:opacity-100"
            :class="card.hero ? 'text-white/80' : 'text-muted-foreground'"
            aria-label="Card options"
            disabled
          >
            <PhDotsThreeVertical class="h-4 w-4" weight="bold" />
          </button>
          <div class="p-5">
            <p
              class="text-sm font-medium"
              :class="card.hero ? 'text-white/80' : 'text-muted-foreground'"
            >
              {{ card.label }}
            </p>
            <div class="mt-2 flex flex-wrap items-end gap-2">
              <p
                class="text-3xl font-bold tabular-nums tracking-tight"
                :class="card.hero ? 'text-white' : 'text-foreground'"
              >
                {{ card.value }}
              </p>
              <span
                v-if="card.hero && card.trend != null"
                class="inline-flex items-center rounded-full bg-accent-gold px-2 py-0.5 text-xs font-semibold text-accent-gold-fg"
              >
                {{ card.trend }}% paid
              </span>
            </div>
            <p
              class="mt-1 text-xs tabular-nums"
              :class="card.hero ? 'text-white/70' : 'text-muted-foreground'"
            >
              {{ card.subtitle }}
            </p>
          </div>
        </div>
      </div>

      <section class="bw-card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-border p-4">
          <div class="relative min-w-[200px] flex-1 sm:max-w-md">
            <PhMagnifyingGlass
              class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
            />
            <input
              v-model="search"
              type="search"
              placeholder="Search invoice #, client, or description…"
              class="bw-input pl-9"
              aria-label="Search case invoices"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <RouterLink
              v-if="canCreate && clientId"
              :to="{
                path: '/invoices/new',
                query: { client_id: clientId, legal_matter_id: caseId },
              }"
              class="bw-btn bw-btn-accent bw-btn-sm"
            >
              <PhPlus class="h-4 w-4" weight="bold" />
              Add new
            </RouterLink>
            <button
              v-if="canCreate && clientId"
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              @click="showGenerateModal = true"
            >
              Generate from time
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :class="showFilter || statusFilter || paymentFilter ? 'border-action-teal text-action-teal' : ''"
              @click="showFilter = !showFilter"
            >
              <PhFunnel class="h-4 w-4" />
              Filter
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-outline bw-btn-sm"
              :disabled="!filteredInvoices.length"
              @click="exportCsv"
            >
              <PhDownloadSimple class="h-4 w-4" />
              Export
            </button>
            <div class="relative">
              <select
                v-model="dateRange"
                class="bw-select w-auto appearance-none pr-8"
                aria-label="Date range"
              >
                <option
                  v-for="option in dateRangeOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
              <PhCaretDown
                class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              />
            </div>
          </div>
        </div>

        <div
          v-if="showFilter"
          class="flex flex-wrap items-center gap-3 border-b border-border bg-surface-muted/40 px-4 py-3"
        >
          <select v-model="statusFilter" class="bw-select w-auto" aria-label="Invoice status">
            <option value="">All invoice statuses</option>
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select v-model="paymentFilter" class="bw-select w-auto" aria-label="Payment status">
            <option value="">All payment statuses</option>
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
            <option value="overdue">Overdue</option>
            <option value="draft">Draft</option>
          </select>
        </div>

        <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

        <div v-if="filteredInvoices.length" class="overflow-x-auto">
          <table class="w-full min-w-[960px] text-sm">
            <thead>
              <tr class="border-b border-border bg-surface-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                <th class="px-5 py-3.5">Invoice #</th>
                <th class="px-5 py-3.5">Client</th>
                <th class="px-5 py-3.5">Issue date</th>
                <th class="px-5 py-3.5">Description</th>
                <th class="px-5 py-3.5 text-right">Total</th>
                <th class="px-5 py-3.5">Payment</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="px-5 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="invoice in paginatedInvoices"
                :key="invoice.id"
                class="transition-colors hover:bg-surface-muted/60"
              >
                <td class="px-5 py-4 font-medium text-foreground">
                  {{ invoice.invoice_number }}
                </td>
                <td class="px-5 py-4 text-foreground">
                  {{ invoice.client?.name || '—' }}
                </td>
                <td class="px-5 py-4 tabular-nums text-muted-foreground">
                  {{ formatDate(invoice.issue_date) }}
                </td>
                <td class="max-w-[220px] truncate px-5 py-4 text-muted-foreground">
                  {{ lineItemsSummary(invoice) }}
                </td>
                <td class="px-5 py-4 text-right font-medium tabular-nums text-foreground">
                  {{ formatCurrency(invoice.total_amount, invoice.currency) }}
                </td>
                <td class="px-5 py-4">
                  <StatusBadge :status="paymentStatus(invoice)" />
                </td>
                <td class="px-5 py-4">
                  <StatusBadge :status="invoice.status" />
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-1">
                    <RouterLink
                      :to="`/invoices/${invoice.id}`"
                      class="bw-btn bw-btn-ghost bw-btn-icon bw-btn-sm text-muted-foreground hover:text-action-teal"
                      aria-label="View invoice"
                      title="View"
                    >
                      <PhEye class="h-4 w-4" />
                    </RouterLink>
                    <RouterLink
                      :to="`/invoices/${invoice.id}`"
                      class="bw-btn bw-btn-ghost bw-btn-icon bw-btn-sm text-muted-foreground hover:text-action-teal"
                      aria-label="Edit invoice"
                      title="Edit"
                    >
                      <PhPencilSimple class="h-4 w-4" />
                    </RouterLink>
                    <button
                      v-if="canDelete"
                      type="button"
                      class="bw-btn bw-btn-ghost bw-btn-icon bw-btn-sm text-muted-foreground hover:text-destructive"
                      aria-label="Delete invoice"
                      title="Delete"
                      @click="removeInvoice(invoice)"
                    >
                      <PhTrash class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :icon="PhReceipt"
          title="No invoices for this case"
          message="Generate an invoice from approved billable time or create one manually."
        />

        <div
          v-if="filteredInvoices.length"
          class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-sm"
        >
          <p class="text-muted-foreground">
            Showing
            <span class="font-medium text-foreground">{{ showingFrom }}–{{ showingTo }}</span>
            out of
            <span class="font-medium text-foreground">{{ filteredInvoices.length }}</span>
          </p>
          <div v-if="lastPage > 1" class="flex items-center gap-1">
            <button
              v-for="n in pageNumbers"
              :key="n"
              type="button"
              class="flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-semibold tabular-nums transition-colors"
              :class="
                n === page
                  ? 'bg-primary-800 text-white'
                  : 'text-muted-foreground hover:bg-surface-muted'
              "
              :aria-current="n === page ? 'page' : undefined"
              @click="page = n"
            >
              {{ n }}
            </button>
          </div>
        </div>
      </section>
    </template>

    <BwModal
      :open="showGenerateModal"
      title="Generate from billable time"
      size="sm"
      @close="showGenerateModal = false"
    >
      <p class="text-sm text-muted-foreground">
        Pull approved, uninvoiced time entries for this case into a new invoice draft.
      </p>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showGenerateModal = false">
          Cancel
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-action"
          :disabled="isGenerating"
          @click="generateFromTime"
        >
          {{ isGenerating ? 'Generating…' : 'Generate invoice' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
