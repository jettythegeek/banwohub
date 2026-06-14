<script setup lang="ts">
import { PhFileArrowUp } from '@phosphor-icons/vue'
import type { IntakeField } from '@/types'

defineProps<{
  field: IntakeField
  preview?: boolean
}>()
</script>

<template>
  <div class="space-y-1.5">
    <label class="bw-label mb-0">
      {{ field.label }}
      <span v-if="field.required" class="text-destructive">*</span>
    </label>

    <textarea
      v-if="field.type === 'long_text'"
      rows="3"
      class="bw-textarea pointer-events-none resize-none"
      :placeholder="preview ? '' : 'Long answer text'"
      disabled
    />

    <div
      v-else-if="field.type === 'file'"
      class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-surface-muted px-4 py-8 text-center"
    >
      <PhFileArrowUp class="h-8 w-8 text-muted-foreground" aria-hidden="true" />
      <p class="text-sm text-muted-foreground">Drag & drop file or browse file</p>
    </div>

    <div v-else-if="field.type === 'radio'" class="flex flex-wrap gap-4">
      <label
        v-for="option in field.options ?? ['Option A', 'Option B']"
        :key="option"
        class="flex items-center gap-2 text-sm text-foreground"
      >
        <span
          class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-border-strong"
          aria-hidden="true"
        />
        {{ option }}
      </label>
    </div>

    <div v-else-if="field.type === 'checkbox'" class="space-y-2">
      <label
        v-for="option in field.options ?? ['Option A', 'Option B']"
        :key="option"
        class="flex items-center gap-2 text-sm text-foreground"
      >
        <span
          class="inline-flex h-4 w-4 shrink-0 rounded border border-border-strong"
          aria-hidden="true"
        />
        {{ option }}
      </label>
    </div>

    <select
      v-else-if="field.type === 'dropdown'"
      class="bw-select pointer-events-none"
      disabled
    >
      <option>Choose…</option>
      <option v-for="option in field.options ?? []" :key="option">{{ option }}</option>
    </select>

    <div
      v-else-if="field.type === 'signature'"
      class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
    >
      Signature pad
    </div>

    <div
      v-else-if="field.type === 'conditional'"
      class="rounded-lg border border-dashed border-border bg-surface-muted px-3 py-2 text-xs text-muted-foreground"
    >
      Shown when
      <span class="font-medium text-foreground">{{ field.conditions?.field || '…' }}</span>
      equals
      <span class="font-medium text-foreground">{{ field.conditions?.equals || '…' }}</span>
    </div>

    <div v-else-if="field.type === 'phone'" class="flex gap-2">
      <div class="bw-input w-24 shrink-0 text-muted-foreground">+1</div>
      <input type="tel" class="bw-input pointer-events-none" placeholder="Phone number" disabled />
    </div>

    <input
      v-else
      class="bw-input pointer-events-none"
      :type="field.type === 'email' ? 'email' : field.type === 'date' ? 'date' : 'text'"
      :placeholder="field.type === 'email' ? 'name@example.com' : ''"
      disabled
    />
  </div>
</template>
