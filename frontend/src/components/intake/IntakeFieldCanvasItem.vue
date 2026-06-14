<script setup lang="ts">
import { PhDotsSixVertical, PhTrash, PhX } from '@phosphor-icons/vue'
import IntakeFieldPreview from '@/components/intake/IntakeFieldPreview.vue'
import { fieldNeedsOptions } from '@/lib/intake-field-catalog'
import type { IntakeField } from '@/types'

const props = defineProps<{
  field: IntakeField
  selected: boolean
  preview: boolean
}>()

const emit = defineEmits<{
  select: []
  deselect: []
  remove: []
  update: [patch: Partial<IntakeField>]
}>()

function patch(partial: Partial<IntakeField>) {
  emit('update', partial)
}

function patchConditions(key: string, value: string) {
  emit('update', {
    conditions: { ...props.field.conditions, [key]: value },
  })
}
</script>

<template>
  <div
    class="intake-field-card group relative rounded-lg border transition-colors"
    :class="
      selected && !preview
        ? 'border-accent bg-accent/5 ring-1 ring-accent/30'
        : 'cursor-pointer border-border bg-surface hover:border-border-strong'
    "
    @click="emit('select')"
  >
    <div
      v-if="!preview"
      class="absolute -left-3 top-1/2 z-10 flex -translate-y-1/2 flex-col gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
      :class="{ 'opacity-100': selected }"
    >
      <button
        type="button"
        class="intake-field-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-md border border-border bg-surface text-muted-foreground shadow-sm active:cursor-grabbing"
        aria-label="Drag to reorder"
        @click.stop
      >
        <PhDotsSixVertical class="h-4 w-4" weight="bold" />
      </button>
    </div>

    <div class="p-4" :class="{ 'pr-10': !preview }">
      <template v-if="selected && !preview">
        <div class="mb-4 space-y-3 rounded-lg border border-border bg-surface p-3" @click.stop>
          <div class="flex items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Field settings
            </p>
            <button
              type="button"
              class="bw-btn bw-btn-ghost bw-btn-icon h-6 w-6 text-muted-foreground"
              aria-label="Close field settings"
              @click="emit('deselect')"
            >
              <PhX class="h-3.5 w-3.5" />
            </button>
          </div>

          <div>
            <label class="bw-label">Label</label>
            <input
              :value="field.label"
              class="bw-input"
              placeholder="Field label"
              @input="patch({ label: ($event.target as HTMLInputElement).value })"
            />
          </div>

          <div>
            <label class="bw-label">Field key</label>
            <input
              :value="field.name"
              class="bw-input font-mono text-xs"
              placeholder="field_key"
              @input="patch({ name: ($event.target as HTMLInputElement).value })"
            />
          </div>

          <label class="flex items-center gap-2 text-sm text-muted-foreground">
            <input
              type="checkbox"
              :checked="field.required"
              class="bw-focus-ring h-4 w-4 rounded border-border-strong"
              @change="patch({ required: ($event.target as HTMLInputElement).checked })"
            />
            Required field
          </label>

          <div v-if="fieldNeedsOptions(field.type)">
            <label class="bw-label">Options (comma-separated)</label>
            <input
              :value="(field.options ?? []).join(', ')"
              class="bw-input"
              placeholder="Option A, Option B"
              @input="
                patch({
                  options: ($event.target as HTMLInputElement).value
                    .split(',')
                    .map((v) => v.trim())
                    .filter(Boolean),
                })
              "
            />
          </div>

          <div v-if="field.type === 'conditional'" class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="bw-label">Show when field</label>
              <input
                :value="String(field.conditions?.field ?? '')"
                class="bw-input"
                placeholder="other_field_name"
                @input="patchConditions('field', ($event.target as HTMLInputElement).value)"
              />
            </div>
            <div>
              <label class="bw-label">Equals value</label>
              <input
                :value="String(field.conditions?.equals ?? '')"
                class="bw-input"
                @input="patchConditions('equals', ($event.target as HTMLInputElement).value)"
              />
            </div>
          </div>
        </div>
      </template>

      <IntakeFieldPreview :field="field" :preview="preview" />
    </div>

    <button
      v-if="!preview"
      type="button"
      class="absolute right-2 top-2 bw-btn bw-btn-ghost bw-btn-icon h-7 w-7 text-destructive opacity-0 transition-opacity group-hover:opacity-100"
      :class="{ 'opacity-100': selected }"
      aria-label="Remove field"
      @click.stop="emit('remove')"
    >
      <PhTrash class="h-3.5 w-3.5" />
    </button>
  </div>
</template>

<style scoped>
.intake-field-handle {
  touch-action: none;
}
</style>
