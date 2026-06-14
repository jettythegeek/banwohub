<script setup lang="ts">
const props = defineProps<{
  page: number
  lastPage: number
  total: number
}>()

const emit = defineEmits<{
  'update:page': [page: number]
}>()
</script>

<template>
  <div
    v-if="lastPage > 1"
    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-sm"
  >
    <span class="text-muted-foreground">{{ total }} total</span>
    <div class="flex items-center gap-2">
      <button
        type="button"
        class="bw-btn bw-btn-outline bw-btn-sm"
        :disabled="page <= 1"
        @click="emit('update:page', page - 1)"
      >
        Previous
      </button>
      <span
        class="flex h-8 min-w-8 items-center justify-center rounded-md bg-[var(--accent-gold)] px-2 text-xs font-semibold tabular-nums text-[var(--accent-gold-fg)]"
        aria-current="page"
      >
        {{ page }}
      </span>
      <span class="text-xs text-muted-foreground tabular-nums">of {{ lastPage }}</span>
      <button
        type="button"
        class="bw-btn bw-btn-outline bw-btn-sm"
        :disabled="page >= lastPage"
        @click="emit('update:page', page + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>
