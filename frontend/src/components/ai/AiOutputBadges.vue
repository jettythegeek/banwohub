<script setup lang="ts">
defineProps<{
  label?: string
  requiresReview?: boolean
}>()
</script>

<template>
  <div v-if="label || requiresReview" class="flex flex-wrap items-center gap-2">
    <span
      v-if="label"
      class="bw-badge bw-badge-info"
    >
      {{ label }}
    </span>
    <span
      v-if="requiresReview"
      class="bw-badge bw-badge-warning"
    >
      Requires review
    </span>
  </div>
</template>
