<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  title: string
  message?: string
  description?: string
  icon?: Component
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
    <div
      v-if="icon"
      class="flex h-14 w-14 items-center justify-center rounded-full border border-border bg-surface shadow-sm text-muted-foreground"
    >
      <component :is="icon" class="h-6 w-6" />
    </div>
    <div class="max-w-sm">
      <p class="font-medium text-foreground">{{ title }}</p>
      <p v-if="message || description" class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
        {{ message || description }}
      </p>
    </div>
    <div v-if="$slots.default" class="mt-2">
      <slot />
    </div>
  </div>
</template>
