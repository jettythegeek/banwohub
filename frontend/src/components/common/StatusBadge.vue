<script setup lang="ts">
import { computed } from 'vue'
import { humanize, statusVariant } from '@/lib/status'

const props = withDefaults(
  defineProps<{
    status?: string | null
    label?: string
    dot?: boolean
  }>(),
  { dot: true },
)

const variant = computed(() => statusVariant(props.status))
const text = computed(() => props.label ?? humanize(props.status))
</script>

<template>
  <span class="bw-badge" :class="`bw-badge-${variant}`">
    <span v-if="dot" class="bw-badge-dot" aria-hidden="true" />
    {{ text }}
  </span>
</template>
