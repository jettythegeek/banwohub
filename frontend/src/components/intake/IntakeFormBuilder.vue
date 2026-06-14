<script setup lang="ts">
import { ref, watch } from 'vue'
import { VueDraggable, type DraggableEvent } from 'vue-draggable-plus'
import { PhEye, PhPlus } from '@phosphor-icons/vue'
import IntakeFieldCanvasItem from '@/components/intake/IntakeFieldCanvasItem.vue'
import {
  cloneCatalogField,
  paletteFieldTypes,
  suggestedFields,
  syncFieldCounterFromFields,
  type FieldCatalogItem,
} from '@/lib/intake-field-catalog'
import type { IntakeField } from '@/types'

const fields = defineModel<IntakeField[]>({ required: true })

const selectedIndex = ref<number | null>(null)
const isPreviewMode = ref(false)
const paletteSource = ref<FieldCatalogItem[]>([...paletteFieldTypes])
const suggestedSource = ref<FieldCatalogItem[]>([...suggestedFields])

const dragGroup = { name: 'intake-fields', pull: 'clone' as const, put: false }

watch(
  fields,
  (value) => {
    syncFieldCounterFromFields(value)
    if (selectedIndex.value !== null && selectedIndex.value >= value.length) {
      selectedIndex.value = value.length > 0 ? value.length - 1 : null
    }
  },
  { immediate: true, deep: true },
)

function uniqueFieldName(baseName: string) {
  const names = new Set(fields.value.map((field) => field.name))
  if (!names.has(baseName)) return baseName
  let index = 2
  while (names.has(`${baseName}_${index}`)) index += 1
  return `${baseName}_${index}`
}

function cloneFromCatalog(item: FieldCatalogItem) {
  const field = cloneCatalogField(item)
  field.name = uniqueFieldName(field.name)
  return field
}

function onCanvasAdd(event: DraggableEvent) {
  const newIndex = event.newIndex ?? fields.value.length - 1
  selectedIndex.value = newIndex
}

function selectField(index: number) {
  selectedIndex.value = index
}

function deselectField() {
  selectedIndex.value = null
}

function updateField(index: number, patch: Partial<IntakeField>) {
  fields.value = fields.value.map((field, i) =>
    i === index ? { ...field, ...patch } : field,
  )
}

function removeField(index: number) {
  fields.value = fields.value.filter((_, i) => i !== index)
  if (selectedIndex.value === index) {
    selectedIndex.value = fields.value.length ? Math.min(index, fields.value.length - 1) : null
  } else if (selectedIndex.value !== null && selectedIndex.value > index) {
    selectedIndex.value -= 1
  }
}

function addEmptyRow() {
  const field = cloneFromCatalog(paletteFieldTypes[1]!)
  fields.value = [...fields.value, field]
  selectedIndex.value = fields.value.length - 1
}

function onCanvasClick(event: MouseEvent) {
  const target = event.target as HTMLElement
  if (target.closest('.intake-field-card')) return
  selectedIndex.value = null
}
</script>

<template>
  <div class="intake-form-builder grid min-h-[520px] gap-0 lg:grid-cols-[minmax(0,1fr)_280px]">
    <!-- Canvas -->
    <section class="flex min-h-0 flex-col border-b border-border lg:border-b-0 lg:border-r">
      <header class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
        <div>
          <h3 class="text-sm font-semibold text-foreground">Customize your application form</h3>
          <p class="text-xs text-muted-foreground">
            Click a field to edit it directly on the form. Drag from the panel to add more.
          </p>
        </div>
        <button
          type="button"
          class="bw-btn bw-btn-outline bw-btn-sm"
          :class="{ 'border-accent text-accent': isPreviewMode }"
          @click="isPreviewMode = !isPreviewMode"
        >
          <PhEye class="h-4 w-4" aria-hidden="true" />
          {{ isPreviewMode ? 'Edit' : 'Preview' }}
        </button>
      </header>

      <div
        class="min-h-0 flex-1 overflow-y-auto bg-surface-muted/40 p-5"
        @click="onCanvasClick"
      >
        <div class="mx-auto max-w-2xl rounded-xl border border-border bg-surface p-6 shadow-sm">
          <VueDraggable
            v-model="fields"
            group="intake-fields"
            :animation="200"
            ghost-class="intake-field-ghost"
            handle=".intake-field-handle"
            class="min-h-[200px] space-y-4"
            :disabled="isPreviewMode"
            @add="onCanvasAdd"
          >
            <div
              v-for="(field, index) in fields"
              :key="`${field.name}-${index}`"
              class="intake-field-slot"
            >
              <IntakeFieldCanvasItem
                :field="field"
                :selected="selectedIndex === index"
                :preview="isPreviewMode"
                @select="selectField(index)"
                @deselect="deselectField"
                @remove="removeField(index)"
                @update="updateField(index, $event)"
              />
            </div>
          </VueDraggable>

          <button
            v-if="!isPreviewMode"
            type="button"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-border px-4 py-6 text-sm text-muted-foreground transition-colors hover:border-accent hover:text-accent"
            @click="addEmptyRow"
          >
            <PhPlus class="h-4 w-4" />
            Drop a field here or click to add
          </button>

          <p
            v-if="!fields.length"
            class="py-12 text-center text-sm text-muted-foreground"
          >
            Your form is empty. Drag elements from the Configure panel to get started.
          </p>
        </div>
      </div>
    </section>

    <!-- Field palette -->
    <aside v-if="!isPreviewMode" class="flex min-h-0 flex-col bg-surface">
      <header class="border-b border-border px-4 py-4">
        <h3 class="text-sm font-semibold text-foreground">Configure</h3>
        <p class="mt-1 text-xs text-muted-foreground">Drag fields onto the form canvas.</p>
      </header>

      <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4">
        <div>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Drag & drop field
          </p>
          <VueDraggable
            v-model="paletteSource"
            :group="dragGroup"
            :sort="false"
            :clone="cloneFromCatalog"
            item-key="id"
            class="grid grid-cols-2 gap-2"
          >
            <div
              v-for="item in paletteSource"
              :key="item.id"
              class="flex cursor-grab flex-col items-center gap-1.5 rounded-lg border border-border bg-surface-muted px-2 py-3 text-center text-xs font-medium text-foreground transition-colors hover:border-accent hover:bg-accent/5 active:cursor-grabbing"
            >
              <component :is="item.icon" class="h-5 w-5 text-muted-foreground" aria-hidden="true" />
              {{ item.label }}
            </div>
          </VueDraggable>
        </div>

        <div>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Suggested field
          </p>
          <VueDraggable
            v-model="suggestedSource"
            :group="dragGroup"
            :sort="false"
            :clone="cloneFromCatalog"
            item-key="id"
            class="grid grid-cols-2 gap-2"
          >
            <div
              v-for="item in suggestedSource"
              :key="item.id"
              class="flex cursor-grab flex-col items-center gap-1.5 rounded-lg border border-border bg-surface-muted px-2 py-3 text-center text-xs font-medium text-foreground transition-colors hover:border-accent hover:bg-accent/5 active:cursor-grabbing"
            >
              <component :is="item.icon" class="h-5 w-5 text-muted-foreground" aria-hidden="true" />
              {{ item.label }}
            </div>
          </VueDraggable>
        </div>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.intake-field-ghost {
  opacity: 0.5;
  background: var(--accent);
  border-radius: var(--radius-lg);
}
</style>
