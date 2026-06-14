<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'

const route = useRoute()
const invoiceId = Number(route.params.id)
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Payment cancelled" subtitle="No charge was made." />

    <section class="bw-card p-5 space-y-4">
      <p class="text-sm text-muted-foreground">
        You can return to the invoice and try again when ready.
      </p>
      <RouterLink
        :to="{ name: 'portal-invoice-detail', params: { id: invoiceId } }"
        class="bw-btn bw-btn-primary inline-flex"
      >
        Back to invoice
      </RouterLink>
    </section>
  </div>
</template>
