<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import MessageThreadPanel from '@/components/messages/MessageThreadPanel.vue'

const route = useRoute()

const initialThreadId = computed(() => {
  const raw = route.query.thread
  const id = typeof raw === 'string' ? Number(raw) : null
  return id && !Number.isNaN(id) ? id : null
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Messages"
      subtitle="Secure messaging with your legal team."
    />
    <div class="bw-card overflow-hidden">
    <MessageThreadPanel
      mode="portal"
      :initial-thread-id="initialThreadId"
    />
    </div>
  </div>
</template>
