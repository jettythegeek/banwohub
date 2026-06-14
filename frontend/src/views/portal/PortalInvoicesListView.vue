<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhReceipt } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { portalInvoicesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import type { Invoice } from '@/types'

const invoices = ref<Invoice[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(async () => {
  isLoading.value = true
  error.value = null
  try {
    invoices.value = await portalInvoicesApi.list()
  } catch (err) {
    error.value = formatApiError(err, 'Invoices are not available yet.')
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Invoices" subtitle="View billing and payment status." />

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <div v-if="isLoading" class="space-y-3">
      <Skeleton v-for="n in 4" :key="n" class="h-16 rounded-lg" />
    </div>
    <div v-else-if="invoices.length" class="bw-card divide-y divide-border overflow-hidden">
      <RouterLink
        v-for="invoice in invoices"
        :key="invoice.id"
        :to="`/portal/invoices/${invoice.id}`"
        class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 hover:bg-surface-muted"
      >
        <div class="flex items-start gap-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
            <PhReceipt class="h-4 w-4" weight="fill" />
          </span>
          <div>
            <p class="font-medium">{{ invoice.invoice_number }}</p>
            <p class="text-sm text-muted-foreground">
              Issued {{ formatDate(invoice.issue_date) }}
              <span v-if="invoice.case"> · {{ invoice.case.title }}</span>
            </p>
          </div>
        </div>
        <div class="text-right">
          <StatusBadge :status="invoice.status" />
          <p class="mt-1 text-sm font-medium">
            {{ formatCurrency(invoice.balance_due, invoice.currency) }} due
          </p>
        </div>
      </RouterLink>
    </div>
    <EmptyState
      v-else
      :icon="PhReceipt"
      title="No invoices yet"
      message="Invoices sent by your firm will appear here."
    />
  </div>
</template>
