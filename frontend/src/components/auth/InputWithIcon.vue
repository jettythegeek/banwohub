<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  icon: Component
  id?: string
  type?: string
  placeholder?: string
  modelValue?: string
  required?: boolean
  autocomplete?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="relative">
    <component
      :is="icon"
      class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
      aria-hidden="true"
    />
    <input
      :id="id"
      :type="type ?? 'text'"
      :placeholder="placeholder"
      :value="modelValue"
      :required="required"
      :autocomplete="autocomplete"
      class="bw-focus-ring w-full rounded-md border border-border bg-surface px-3 py-2 pl-9 text-sm text-foreground placeholder:text-muted-foreground"
      @input="
        $emit('update:modelValue', ($event.target as HTMLInputElement).value)
      "
    />
  </div>
</template>
