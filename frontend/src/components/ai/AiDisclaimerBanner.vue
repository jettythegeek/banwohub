<script setup lang="ts">
import { PhWarningCircle } from '@phosphor-icons/vue'

defineProps<{
  disclaimer: string
  compact?: boolean
}>()
</script>

<template>
  <div
    class="flex gap-3 rounded-lg border border-status-warning-border bg-status-warning px-4 py-3 text-sm text-foreground"
    role="status"
    aria-live="polite"
  >
    <PhWarningCircle
      class="mt-0.5 h-5 w-5 shrink-0 text-warning"
      weight="fill"
      aria-hidden="true"
    />
    <div class="min-w-0 space-y-1">
      <p class="font-semibold text-foreground">AI assistance disclaimer</p>
      <p :class="compact ? 'text-xs' : ''">{{ disclaimer }}</p>
    </div>
  </div>
</template>
