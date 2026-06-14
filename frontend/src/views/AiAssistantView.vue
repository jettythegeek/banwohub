<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AiAssistantPanel from '@/components/ai/AiAssistantPanel.vue'
import PageHeader from '@/components/common/PageHeader.vue'

const route = useRoute()
const initialPrompt = computed(() => {
  const value = route.query.prompt
  return typeof value === 'string' ? value : ''
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="AI assistant"
      subtitle="Staff support for policies, knowledge base topics, and practice workflows."
    />
    <AiAssistantPanel :initial-prompt="initialPrompt" />
  </div>
</template>
