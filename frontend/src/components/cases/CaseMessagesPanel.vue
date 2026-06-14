<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import MessageThreadPanel from '@/components/messages/MessageThreadPanel.vue'

const props = defineProps<{
  caseId: number
  clientId?: number | null
}>()

const route = useRoute()
const initialThreadId = computed(() => {
  const raw = route.query.thread
  const id = typeof raw === 'string' ? Number(raw) : null
  return id && !Number.isNaN(id) ? id : null
})
</script>

<template>
  <MessageThreadPanel
    mode="staff"
    :case-id="caseId"
    :client-id="clientId"
    :initial-thread-id="initialThreadId"
  />
</template>
