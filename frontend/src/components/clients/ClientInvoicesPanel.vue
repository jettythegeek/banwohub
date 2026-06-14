<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhReceipt } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { invoicesApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import type { Invoice } from '@/types'

const props = defineProps<{
  clientId: number
  embedded?: boolean
}>()

const invoices = ref<Invoice[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(async () => {
  try {
    const { invoices: list } = await invoicesApi.list({ client_id: props.clientId })
    invoices.value = list
  } catch (err) {
    error.value = formatApiError(err, 'Invoices are not available yet.')
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <section :class="embedded ? '' : 'bw-card overflow-hidden'">
    <div :class="embedded ? 'mb-4 flex items-start justify-between gap-4' : 'bw-card-header'">
      <div>
        <h2 class="font-semibold text-foreground">Invoices</h2>
        <p class="text-sm text-muted-foreground">Billing history for this client.</p>
      </div>
      <RouterLink
        :to="{ path: '/invoices/new', query: { client_id: clientId } }"
        class="bw-btn bw-btn-outline bw-btn-sm"
      >
        New invoice
      </RouterLink>
    </div>

    <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>
    <Skeleton v-else-if="isLoading" variant="panel" :rows="3" />

    <div
      v-else-if="invoices.length"
      :class="embedded ? 'bw-card divide-y divide-border overflow-hidden' : 'divide-y divide-border'"
    >
      <RouterLink
        v-for="invoice in invoices"
        :key="invoice.id"
        :to="`/invoices/${invoice.id}`"
        class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-surface-muted"
      >
        <div>
          <p class="font-medium text-foreground">{{ invoice.invoice_number }}</p>
          <p class="text-xs text-muted-foreground">
            {{ invoice.case?.title || 'General' }}
            · Issued {{ formatDate(invoice.issue_date) }}
          </p>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <span class="font-medium tabular-nums">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</span>
          <StatusBadge :status="invoice.status" />
        </div>
      </RouterLink>
    </div>

    <EmptyState
      v-else
      :icon="PhReceipt"
      title="No invoices yet"
      description="Create an invoice for this client from the Invoices workspace."
      :class="embedded ? 'bw-card' : ''"
    />
  </section>
</template>
