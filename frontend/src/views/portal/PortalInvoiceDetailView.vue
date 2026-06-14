<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { portalInvoicesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import type { Invoice, PaymentGateways } from '@/types'

const route = useRoute()
const invoiceId = Number(route.params.id)

const invoice = ref<Invoice | null>(null)
const gateways = ref<PaymentGateways | null>(null)
const isLoading = ref(true)
const isPaying = ref(false)
const error = ref<string | null>(null)
const payError = ref<string | null>(null)

const canPay = computed(
  () => invoice.value && ['sent', 'partial', 'overdue'].includes(invoice.value.status),
)

const stripeEnabled = computed(() => gateways.value?.stripe?.enabled ?? false)
const paypalEnabled = computed(() => gateways.value?.paypal?.enabled ?? false)
const anyGatewayEnabled = computed(() => stripeEnabled.value || paypalEnabled.value)

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

async function payWithStripe() {
  if (!invoice.value) return
  isPaying.value = true
  payError.value = null
  try {
    const session = await portalInvoicesApi.checkoutStripe(invoice.value.id)
    window.location.href = session.checkout_url
  } catch (err) {
    payError.value = formatApiError(err, 'Could not start Stripe checkout.')
  } finally {
    isPaying.value = false
  }
}

async function payWithPayPal() {
  if (!invoice.value) return
  isPaying.value = true
  payError.value = null
  try {
    const order = await portalInvoicesApi.checkoutPayPal(invoice.value.id)
    window.location.href = order.approval_url
  } catch (err) {
    payError.value = formatApiError(err, 'Could not start PayPal checkout.')
  } finally {
    isPaying.value = false
  }
}

onMounted(async () => {
  isLoading.value = true
  error.value = null
  try {
    const [inv, gw] = await Promise.all([
      portalInvoicesApi.get(invoiceId),
      portalInvoicesApi.paymentGateways().catch(() => null),
    ])
    invoice.value = inv
    gateways.value = gw ?? inv.payment_gateways ?? null
  } catch (err) {
    error.value = formatApiError(err, 'This invoice is not available.')
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div v-if="isLoading">
      <Skeleton class="mb-4 h-8 w-48 rounded-md" />
      <Skeleton class="h-64 rounded-lg" />
    </div>
    <template v-else-if="invoice">
      <PageHeader
        :title="invoice.invoice_number"
        :subtitle="invoice.case?.title || 'Invoice details'"
      >
        <StatusBadge :status="invoice.status" />
      </PageHeader>

      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

      <section class="bw-card p-5 space-y-4">
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-muted-foreground">Issue date</dt>
            <dd class="font-medium">{{ formatDate(invoice.issue_date) }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Due date</dt>
            <dd class="font-medium">{{ formatDate(invoice.due_date) }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Total</dt>
            <dd class="font-medium">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">Balance due</dt>
            <dd class="font-medium">{{ formatCurrency(invoice.balance_due, invoice.currency) }}</dd>
          </div>
        </dl>
        <p v-if="invoice.notes" class="text-sm text-muted-foreground">{{ invoice.notes }}</p>

        <div v-if="canPay && invoice.balance_due > 0" class="border-t border-border pt-4 space-y-3">
          <h2 class="text-sm font-semibold">Pay online</h2>
          <p v-if="payError" class="text-sm text-destructive" role="alert">{{ payError }}</p>

          <div v-if="anyGatewayEnabled" class="flex flex-wrap gap-3">
            <button
              v-if="stripeEnabled"
              type="button"
              class="bw-btn bw-btn-primary"
              :disabled="isPaying"
              @click="payWithStripe"
            >
              Pay with Stripe
            </button>
            <button
              v-if="paypalEnabled"
              type="button"
              class="bw-btn bw-btn-secondary"
              :disabled="isPaying"
              @click="payWithPayPal"
            >
              Pay with PayPal
            </button>
          </div>
          <p v-else class="text-sm text-muted-foreground">
            {{
              gateways?.stripe?.message ||
              gateways?.paypal?.message ||
              'Online payments are not configured yet. Contact your firm to pay by bank transfer or other arrangement.'
            }}
          </p>
        </div>
      </section>

      <section v-if="invoice.line_items?.length" class="bw-card overflow-hidden">
        <div class="border-b border-border px-5 py-4">
          <h2 class="font-semibold">Line items</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="border-b border-border bg-surface text-left text-muted-foreground">
            <tr>
              <th class="px-5 py-3 font-medium">Description</th>
              <th class="px-5 py-3 font-medium text-right">Qty</th>
              <th class="px-5 py-3 font-medium text-right">Unit</th>
              <th class="px-5 py-3 font-medium text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in invoice.line_items"
              :key="item.id ?? item.description"
              class="border-b border-border last:border-0"
            >
              <td class="px-5 py-3">{{ item.description }}</td>
              <td class="px-5 py-3 text-right">{{ item.quantity }}</td>
              <td class="px-5 py-3 text-right">
                {{ formatCurrency(item.unit_price, invoice.currency) }}
              </td>
              <td class="px-5 py-3 text-right font-medium">
                {{ formatCurrency(item.amount, invoice.currency) }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
    <EmptyState
      v-else
      title="Invoice not found"
      :message="error || 'This invoice may not exist or you do not have access.'"
    />
  </div>
</template>
