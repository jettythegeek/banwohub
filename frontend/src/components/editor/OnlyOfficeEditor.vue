<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { caseDocumentsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { OnlyOfficeEditorConfig } from '@/types'

declare global {
  interface Window {
    DocsAPI?: {
      DocEditor: new (
        placeholderId: string,
        config: Record<string, unknown>,
      ) => { destroyEditor?: () => void }
    }
  }
}

const props = defineProps<{
  documentId: number
}>()

const emit = defineEmits<{
  saved: []
  close: []
}>()

const editorConfig = ref<OnlyOfficeEditorConfig | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)
const placeholderId = `onlyoffice-editor-${Math.random().toString(36).slice(2)}`

let docEditor: { destroyEditor?: () => void } | null = null
let loadedScriptUrl: string | null = null

function loadScript(src: string): Promise<void> {
  if (loadedScriptUrl === src && window.DocsAPI) {
    return Promise.resolve()
  }

  const existing = document.querySelector<HTMLScriptElement>(`script[data-onlyoffice="${src}"]`)
  if (existing && window.DocsAPI) {
    loadedScriptUrl = src
    return Promise.resolve()
  }

  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.dataset.onlyoffice = src
    script.onload = () => {
      loadedScriptUrl = src
      resolve()
    }
    script.onerror = () => reject(new Error('OnlyOffice editor script failed to load.'))
    document.head.appendChild(script)
  })
}

function destroyEditor() {
  docEditor?.destroyEditor?.()
  docEditor = null
}

async function initEditor() {
  destroyEditor()
  isLoading.value = true
  error.value = null
  editorConfig.value = null

  try {
    const response = await caseDocumentsApi.onlyOfficeConfig(props.documentId)
    if (!response.configured || !response.available || !response.config || !response.editor_url) {
      error.value = 'OnlyOffice is not available for this document.'
      return
    }

    editorConfig.value = response
    await loadScript(response.editor_url)

    if (!window.DocsAPI) {
      throw new Error('OnlyOffice DocsAPI is unavailable.')
    }

    docEditor = new window.DocsAPI.DocEditor(placeholderId, {
      ...response.config,
      events: {
        onDocumentStateChange: (event: { data?: boolean }) => {
          if (event.data === false) {
            emit('saved')
          }
        },
        onRequestClose: () => emit('close'),
      },
    })
  } catch (err) {
    error.value = formatApiError(err, 'OnlyOffice editor could not be loaded.')
  } finally {
    isLoading.value = false
  }
}

onMounted(initEditor)
watch(() => props.documentId, initEditor)
onBeforeUnmount(destroyEditor)
</script>

<template>
  <div class="space-y-3">
    <Skeleton v-if="isLoading" variant="panel" :rows="6" />
    <p v-else-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive" role="alert">
      {{ error }}
    </p>
    <div
      v-show="!isLoading && !error"
      :id="placeholderId"
      class="min-h-[640px] w-full overflow-hidden rounded-lg border border-border bg-background"
    />
  </div>
</template>
