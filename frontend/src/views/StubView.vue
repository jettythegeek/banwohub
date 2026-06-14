<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'

const route = useRoute()
const title = computed(() => (route.meta.title as string) ?? 'Page')
</script>

<template>
  <PageHeader :title="title" subtitle="Coming soon." />
</template>
