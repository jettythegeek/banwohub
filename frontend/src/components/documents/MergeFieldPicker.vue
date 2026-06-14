<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { caseDocumentsApi } from '@/lib/api'
import type { MergeFieldDefinition } from '@/types'

const emit = defineEmits<{
  insert: [token: string]
}>()

const fields = ref<MergeFieldDefinition[]>([])
const isLoading = ref(true)
const activeGroup = ref<string>('')

const groups = computed(() => {
  const map = new Map<string, MergeFieldDefinition[]>()
  for (const field of fields.value) {
    const list = map.get(field.group) ?? []
    list.push(field)
    map.set(field.group, list)
  }
  return [...map.entries()]
})

onMounted(async () => {
  try {
    fields.value = await caseDocumentsApi.listMergeFields()
    activeGroup.value = fields.value[0]?.group ?? ''
  } catch {
    fields.value = []
  } finally {
    isLoading.value = false
  }
})

function insertField(key: string) {
  emit('insert', `{{${key}}}`)
}
</script>

<template>
  <div class="space-y-3 rounded-lg border border-border bg-surface p-4">
    <div>
      <p class="text-sm font-medium text-foreground">Insert merge field</p>
      <p class="text-xs text-muted-foreground">
        Click a field to insert it at the end of the template body.
      </p>
    </div>

    <p v-if="isLoading" class="text-xs text-muted-foreground">Loading fields…</p>

    <template v-else-if="groups.length">
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="[group] in groups"
          :key="group"
          type="button"
          class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
          :class="
            activeGroup === group
              ? 'bg-primary-700 text-white'
              : 'bg-surface text-muted-foreground hover:text-foreground'
          "
          @click="activeGroup = group"
        >
          {{ group }}
        </button>
      </div>

      <div class="flex max-h-36 flex-wrap gap-2 overflow-y-auto">
        <button
          v-for="field in groups.find(([group]) => group === activeGroup)?.[1] ?? []"
          :key="field.key"
          type="button"
          class="rounded-md border border-border bg-surface px-2.5 py-1.5 text-left text-xs text-foreground transition-colors hover:border-primary-700 hover:text-primary-700"
          :title="field.example ? `Example: ${field.example}` : field.key"
          @click="insertField(field.key)"
        >
          <span class="font-medium">{{ field.label }}</span>
          <span class="mt-0.5 block font-mono text-[10px] text-muted-foreground">{{ field.key }}</span>
        </button>
      </div>
    </template>
  </div>
</template>
