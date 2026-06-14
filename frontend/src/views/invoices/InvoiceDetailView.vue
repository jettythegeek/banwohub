<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { PhCaretRight } from '@phosphor-icons/vue'
import ApprovalWorkflowPanel from '@/components/approvals/ApprovalWorkflowPanel.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { api, invoicesApi, serviceItemsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import { PAYMENT_METHODS, paymentMethodLabel } from '@/lib/enums'
import { usePermissions } from '@/composables/usePermissions'
import type { Client, Invoice, LegalMatter, ServiceItem } from '@/types'

const route = useRoute()
const router = useRouter()
const { can } = usePermissions()

const isNew = computed(() => route.name === 'invoice-new')
const invoice = ref<Invoice | null>(null)
const clients = ref<Client[]>([])
const cases = ref<LegalMatter[]>([])
const serviceItems = ref<ServiceItem[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showPayment = ref(false)

const canSend = computed(() => can('invoices.send'))
const canRecordPayment = computed(() => can('invoices.record-payment'))
const canDelete = computed(() => can('invoices.delete'))

const form = ref({
  client_id: '' as string | number,
  legal_matter_id: '' as string | number,
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  tax_rate: 0,
  discount_amount: 0,
  notes: '',
  line_items: [
    {
      description: '',
      quantity: 1,
      unit_price: 0,
      line_type: 'service',
      service_item_id: null as number | null,
    },
  ],
})

const paymentForm = ref({
  amount: 0,
  payment_method: 'bank_transfer',
  notes: '',
})

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

async function loadInvoice() {
  if (isNew.value) return
  const { data } = await api.get<Invoice>(`/invoices/${route.params.id}`)
  invoice.value = data
}

async function loadClients() {
  const { data } = await api.get<{ data: Client[] }>('/clients', { params: { per_page: 100 } })
  clients.value = data.data
}

async function loadCases(clientId?: number) {
  if (!clientId) {
    cases.value = []
    return
  }
  const { data } = await api.get<{ data: LegalMatter[] }>('/cases', {
    params: { per_page: 100, client_id: clientId },
  })
  cases.value = data.data
}

function addLine() {
  form.value.line_items.push({
    description: '',
    quantity: 1,
    unit_price: 0,
    line_type: 'service',
    service_item_id: null,
  })
}

async function loadServiceItems() {
  try {
    serviceItems.value = await serviceItemsApi.list()
  } catch {
    serviceItems.value = []
  }
}

function applyServiceItem(index: number, serviceItemId: string) {
  const id = serviceItemId ? Number(serviceItemId) : null
  const line = form.value.line_items[index]
  line.service_item_id = id
  if (!id) return
  const item = serviceItems.value.find((row) => row.id === id)
  if (!item) return
  line.description = item.description ? `${item.name} — ${item.description}` : item.name
  line.unit_price = item.default_rate
}

function removeLine(index: number) {
  if (form.value.line_items.length <= 1) return
  form.value.line_items.splice(index, 1)
}

async function saveInvoice() {
  if (!form.value.client_id) return
  isSaving.value = true
  error.value = null
  try {
    const payload = {
      client_id: Number(form.value.client_id),
      legal_matter_id: form.value.legal_matter_id ? Number(form.value.legal_matter_id) : null,
      issue_date: form.value.issue_date,
      due_date: form.value.due_date || null,
      tax_rate: form.value.tax_rate || 0,
      discount_amount: form.value.discount_amount || 0,
      notes: form.value.notes || null,
      line_items: form.value.line_items.map((item) => ({
        description: item.description,
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
        line_type: item.line_type,
        service_item_id: item.service_item_id,
      })),
    }
    const created = await invoicesApi.create(payload)
    await router.replace(`/invoices/${created.id}`)
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this invoice.')
  } finally {
    isSaving.value = false
  }
}

async function markSent() {
  if (!invoice.value) return
  error.value = null
  try {
    invoice.value = await invoicesApi.markSent(invoice.value.id)
  } catch (err) {
    error.value = formatApiError(err, 'We could not mark this invoice as sent.')
  }
}

async function recordPayment() {
  if (!invoice.value || paymentForm.value.amount <= 0) return
  error.value = null
  try {
    invoice.value = await invoicesApi.recordPayment(invoice.value.id, {
      amount: paymentForm.value.amount,
      payment_method: paymentForm.value.payment_method,
      notes: paymentForm.value.notes || undefined,
    })
    showPayment.value = false
    paymentForm.value.amount = 0
    paymentForm.value.notes = ''
  } catch (err) {
    error.value = formatApiError(err, 'We could not record this payment.')
  }
}

async function exportPdf() {
  if (!invoice.value) return
  try {
    await invoicesApi.exportPdf(invoice.value.id, invoice.value.invoice_number)
  } catch (err) {
    error.value = formatApiError(err, 'We could not export this invoice.')
  }
}

async function deleteInvoice() {
  if (!invoice.value || !confirm('Delete this draft invoice?')) return
  error.value = null
  try {
    await invoicesApi.delete(invoice.value.id)
    await router.push('/invoices')
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this invoice.')
  }
}

onMounted(async () => {
  isLoading.value = true
  try {
    await loadClients()
    if (isNew.value) {
      await loadServiceItems()
      const clientId = route.query.client_id
      const caseId = route.query.legal_matter_id
      if (clientId) form.value.client_id = Number(clientId)
      if (caseId) form.value.legal_matter_id = Number(caseId)
      if (form.value.client_id) await loadCases(Number(form.value.client_id))
      return
    }
    await loadInvoice()
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <Skeleton v-if="isLoading" variant="detail" />
    <p v-else-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <template v-else-if="isNew">
      <PageHeader title="New invoice" eyebrow="Billing" />
      <section class="bw-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-6 py-4">
          <p class="text-sm text-muted-foreground">Complete billing details and line items.</p>
          <div class="flex gap-2">
            <RouterLink to="/invoices" class="bw-btn bw-btn-outline bw-btn-sm">Cancel</RouterLink>
            <button
              type="button"
              class="bw-btn bw-btn-action bw-btn-sm"
              :disabled="!form.client_id || isSaving"
              @click="saveInvoice"
            >
              {{ isSaving ? 'Saving…' : 'Create invoice' }}
            </button>
          </div>
        </div>
        <div class="space-y-6 p-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Client</span>
            <select
              v-model="form.client_id"
              class="bw-select w-full"
              @change="loadCases(form.client_id ? Number(form.client_id) : undefined)"
            >
              <option value="">Select client</option>
              <option v-for="client in clients" :key="client.id" :value="client.id">
                {{ client.name }}
              </option>
            </select>
          </label>
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Case (optional)</span>
            <select v-model="form.legal_matter_id" class="bw-select w-full" :disabled="!form.client_id">
              <option value="">None</option>
              <option v-for="matter in cases" :key="matter.id" :value="matter.id">
                {{ matter.title }}
              </option>
            </select>
          </label>
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Issue date</span>
            <input v-model="form.issue_date" type="date" class="bw-input w-full" />
          </label>
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Due date</span>
            <input v-model="form.due_date" type="date" class="bw-input w-full" />
          </label>
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Tax rate (%)</span>
            <input v-model.number="form.tax_rate" type="number" min="0" class="bw-input w-full" />
          </label>
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Discount</span>
            <input v-model.number="form.discount_amount" type="number" min="0" class="bw-input w-full" />
          </label>
        </div>

        <div>
          <div class="mb-3 flex items-center justify-between">
            <h2 class="font-semibold text-foreground">Line items</h2>
            <button type="button" class="bw-btn bw-btn-outline bw-btn-sm" @click="addLine">Add line</button>
          </div>
          <div class="space-y-3">
            <div
              v-for="(line, index) in form.line_items"
              :key="index"
              class="grid gap-3 border border-border p-3 sm:grid-cols-12"
            >
              <select
                class="bw-select sm:col-span-3"
                :value="line.service_item_id ?? ''"
                @change="applyServiceItem(index, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">Catalog item…</option>
                <option v-for="item in serviceItems" :key="item.id" :value="item.id">
                  {{ item.name }} ({{ formatCurrency(item.default_rate) }})
                </option>
              </select>
              <input
                v-model="line.description"
                class="bw-input sm:col-span-4"
                placeholder="Description"
              />
              <input
                v-model.number="line.quantity"
                type="number"
                min="0"
                step="0.01"
                class="bw-input sm:col-span-2"
                placeholder="Qty"
              />
              <input
                v-model.number="line.unit_price"
                type="number"
                min="0"
                step="0.01"
                class="bw-input sm:col-span-2"
                placeholder="Rate"
              />
              <div class="flex items-center justify-between sm:col-span-3">
                <span class="text-sm tabular-nums text-muted-foreground">
                  {{ formatCurrency(line.quantity * line.unit_price) }}
                </span>
                <button
                  type="button"
                  class="text-sm text-destructive"
                  :disabled="form.line_items.length <= 1"
                  @click="removeLine(index)"
                >
                  Remove
                </button>
              </div>
            </div>
          </div>
        </div>

        <label class="block space-y-1 text-sm">
          <span class="text-muted-foreground">Notes</span>
          <textarea v-model="form.notes" rows="3" class="bw-input w-full" />
        </label>
        </div>
      </section>
    </template>

    <template v-else-if="invoice">
      <nav class="flex items-center gap-1 text-sm text-muted-foreground">
        <RouterLink to="/invoices" class="hover:text-foreground">Invoices</RouterLink>
        <PhCaretRight class="h-3.5 w-3.5" />
        <span class="text-foreground">{{ invoice.invoice_number }}</span>
      </nav>

      <PageHeader :title="invoice.invoice_number" eyebrow="Invoice">
        <template #actions>
          <button type="button" class="bw-btn bw-btn-outline" @click="exportPdf">Export PDF</button>
          <button
            v-if="canSend && ['draft', 'sent'].includes(invoice.status)"
            type="button"
            class="bw-btn bw-btn-outline"
            @click="markSent"
          >
            Mark sent
          </button>
          <button
            v-if="canRecordPayment && invoice.balance_due > 0"
            type="button"
            class="bw-btn bw-btn-action"
            @click="showPayment = !showPayment"
          >
            Record payment
          </button>
          <button
            v-if="canDelete && invoice.status === 'draft'"
            type="button"
            class="bw-btn bw-btn-outline text-destructive"
            @click="deleteInvoice"
          >
            Delete
          </button>
        </template>
      </PageHeader>

      <div class="flex flex-wrap items-center gap-3">
        <StatusBadge :status="invoice.status" />
        <span class="text-sm text-muted-foreground">
          Issued {{ formatDate(invoice.issue_date) }}
          <span v-if="invoice.due_date"> · Due {{ formatDate(invoice.due_date) }}</span>
        </span>
      </div>

      <ApprovalWorkflowPanel
        subject-type="invoice"
        :subject-id="invoice.id"
        :requires-approval="invoice.requires_approval"
        @updated="loadInvoice"
      />

      <section v-if="showPayment && canRecordPayment" class="bw-card p-5">
        <h2 class="font-semibold text-foreground">Record payment</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Amount</span>
            <input v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="bw-input w-full" />
          </label>
          <label class="space-y-1 text-sm">
            <span class="text-muted-foreground">Method</span>
            <select v-model="paymentForm.payment_method" class="bw-select w-full">
              <option v-for="method in PAYMENT_METHODS" :key="method" :value="method">
                {{ paymentMethodLabel(method) }}
              </option>
            </select>
          </label>
          <label class="space-y-1 text-sm sm:col-span-1">
            <span class="text-muted-foreground">Notes</span>
            <input v-model="paymentForm.notes" class="bw-input w-full" />
          </label>
        </div>
        <button type="button" class="bw-btn bw-btn-action mt-4" @click="recordPayment">
          Save payment
        </button>
      </section>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="bw-card p-6 lg:col-span-2">
          <h2 class="mb-4 font-semibold text-foreground">Line items</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th class="pb-2 pr-4">Description</th>
                  <th class="pb-2 pr-4 text-right">Qty</th>
                  <th class="pb-2 pr-4 text-right">Rate</th>
                  <th class="pb-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in invoice.line_items"
                  :key="item.id"
                  class="border-b border-border"
                >
                  <td class="py-3 pr-4">{{ item.description }}</td>
                  <td class="py-3 pr-4 text-right tabular-nums">{{ item.quantity }}</td>
                  <td class="py-3 pr-4 text-right tabular-nums">
                    {{ formatCurrency(item.unit_price, invoice.currency) }}
                  </td>
                  <td class="py-3 text-right tabular-nums">
                    {{ formatCurrency(item.amount, invoice.currency) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="invoice.notes" class="mt-6 border-t border-border pt-4 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notes</p>
            <p class="mt-2 whitespace-pre-wrap">{{ invoice.notes }}</p>
          </div>
        </div>

        <div class="bw-card p-6 space-y-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Client</p>
            <RouterLink
              v-if="invoice.client"
              :to="`/clients/${invoice.client.id}`"
              class="mt-1 block font-medium text-primary-700 hover:underline"
            >
              {{ invoice.client.name }}
            </RouterLink>
          </div>
          <div v-if="invoice.case">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Case</p>
            <RouterLink
              :to="`/cases/${invoice.case.id}`"
              class="mt-1 block font-medium text-primary-700 hover:underline"
            >
              {{ invoice.case.title }}
            </RouterLink>
          </div>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-muted-foreground">Subtotal</dt>
              <dd class="tabular-nums">{{ formatCurrency(invoice.subtotal, invoice.currency) }}</dd>
            </div>
            <div v-if="invoice.tax_amount > 0" class="flex justify-between">
              <dt class="text-muted-foreground">Tax</dt>
              <dd class="tabular-nums">{{ formatCurrency(invoice.tax_amount, invoice.currency) }}</dd>
            </div>
            <div v-if="invoice.discount_amount > 0" class="flex justify-between">
              <dt class="text-muted-foreground">Discount</dt>
              <dd class="tabular-nums">-{{ formatCurrency(invoice.discount_amount, invoice.currency) }}</dd>
            </div>
            <div class="flex justify-between border-t border-border pt-2 font-semibold">
              <dt>Total</dt>
              <dd class="tabular-nums">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</dd>
            </div>
            <div v-if="invoice.amount_paid > 0" class="flex justify-between">
              <dt class="text-muted-foreground">Paid</dt>
              <dd class="tabular-nums">{{ formatCurrency(invoice.amount_paid, invoice.currency) }}</dd>
            </div>
            <div v-if="invoice.balance_due > 0" class="flex justify-between font-medium text-foreground">
              <dt>Balance due</dt>
              <dd class="tabular-nums">{{ formatCurrency(invoice.balance_due, invoice.currency) }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </template>
  </div>
</template>
