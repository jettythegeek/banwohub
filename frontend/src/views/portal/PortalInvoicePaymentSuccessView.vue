<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { portalInvoicesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'

const route = useRoute()
const invoiceId = Number(route.params.id)
const gateway = (route.query.gateway as string) || 'stripe'
const token = route.query.token as string | undefined

const message = ref('Confirming your payment…')
const isLoading = ref(true)
const isPaid = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  if (gateway === 'paypal' && token) {
    try {
      const result = await portalInvoicesApi.capturePayPal(token)
      isPaid.value = result.captured
      message.value = result.captured
        ? 'Thank you — your PayPal payment was received.'
        : 'Payment is still processing. Refresh the invoice in a moment.'
    } catch (err) {
      error.value = formatApiError(err, 'We could not confirm your PayPal payment.')
      message.value = 'Payment confirmation failed.'
    } finally {
      isLoading.value = false
    }
    return
  }

  isLoading.value = false
  message.value =
    gateway === 'stripe'
      ? 'Thank you — if your card payment succeeded, your invoice will update shortly.'
      : 'Thank you — your payment is being processed.'
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Payment submitted" subtitle="Return to your invoice for the latest status." />

    <section class="bw-card p-5 space-y-4">
      <div v-if="isLoading">
        <Skeleton class="h-5 w-full max-w-md rounded-md" />
      </div>
      <template v-else>
        <p class="text-sm" :class="error ? 'text-destructive' : 'text-foreground'">
          {{ error || message }}
        </p>
        <p v-if="isPaid" class="text-sm text-muted-foreground">
          Your invoice balance has been updated.
        </p>
        <RouterLink
          :to="{ name: 'portal-invoice-detail', params: { id: invoiceId } }"
          class="bw-btn bw-btn-primary inline-flex"
        >
          View invoice
        </RouterLink>
      </template>
    </section>
  </div>
</template>
