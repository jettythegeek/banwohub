<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import Highlight from '@tiptap/extension-highlight'
import StarterKit from '@tiptap/starter-kit'
import { CommentMark, extractCommentsFromHtml, type EditorComment } from './commentMark'
import { useAuthStore } from '@/stores/auth'

const TRACK_INSERT_COLOR = '#bbf7d0'
const TRACK_DELETE_COLOR = '#fecaca'

const props = withDefaults(
  defineProps<{
    enableComments?: boolean
    enableTrackChanges?: boolean
  }>(),
  {
    enableComments: true,
    enableTrackChanges: true,
  },
)

const model = defineModel<string>({ default: '' })

const auth = useAuthStore()
const commentDraft = ref('')
const activeCommentId = ref<string | null>(null)
const trackChangesMode = ref(false)

const editor = useEditor({
  extensions: [StarterKit, Highlight.configure({ multicolor: true }), CommentMark],
  content: model.value,
  editorProps: {
    attributes: {
      class:
        'min-h-[180px] px-3 py-2 text-sm text-foreground focus:outline-none prose prose-sm max-w-none',
    },
  },
  onUpdate: ({ editor: ed }) => {
    model.value = ed.getHTML()
  },
})

const comments = computed<EditorComment[]>(() => extractCommentsFromHtml(model.value))

watch(model, (value) => {
  if (editor.value && value !== editor.value.getHTML()) {
    editor.value.commands.setContent(value || '', { emitUpdate: false })
  }
})

function addComment() {
  if (!editor.value || !props.enableComments) return
  const text = commentDraft.value.trim()
  if (!text) return

  const id = crypto.randomUUID()
  const author = auth.user?.name ?? 'Staff'

  editor.value
    .chain()
    .focus()
    .setMark('comment', { id, text, author })
    .run()

  commentDraft.value = ''
  activeCommentId.value = id
}

function focusComment(comment: EditorComment) {
  activeCommentId.value = comment.id
}

function markSuggestion(kind: 'insert' | 'delete') {
  if (!editor.value || !trackChangesMode.value) return
  const color = kind === 'insert' ? TRACK_INSERT_COLOR : TRACK_DELETE_COLOR
  editor.value.chain().focus().toggleHighlight({ color }).run()
}

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-border bg-surface">
    <div
      v-if="editor"
      class="flex flex-wrap gap-1 border-b border-border px-2 py-1.5"
    >
      <button
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        :class="{ 'bg-primary-50 text-primary-700': editor.isActive('bold') }"
        @click="editor.chain().focus().toggleBold().run()"
      >
        Bold
      </button>
      <button
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        :class="{ 'bg-primary-50 text-primary-700': editor.isActive('italic') }"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        Italic
      </button>
      <button
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        :class="{ 'bg-primary-50 text-primary-700': editor.isActive('bulletList') }"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        List
      </button>
      <button
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        :class="{ 'bg-primary-50 text-primary-700': editor.isActive('heading', { level: 2 }) }"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        Heading
      </button>
      <button
        v-if="!trackChangesMode"
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        :class="{ 'bg-primary-50 text-primary-700': editor.isActive('highlight') }"
        @click="editor.chain().focus().toggleHighlight().run()"
      >
        Highlight
      </button>

      <span v-if="enableTrackChanges" class="mx-1 hidden h-6 w-px bg-border sm:inline-block" />

      <button
        v-if="enableTrackChanges"
        type="button"
        class="bw-btn bw-btn-ghost bw-btn-sm"
        :class="{ 'bg-accent-50 text-accent-800': trackChangesMode }"
        @click="trackChangesMode = !trackChangesMode"
      >
        Track changes lite
      </button>
      <template v-if="enableTrackChanges && trackChangesMode">
        <button
          type="button"
          class="bw-btn bw-btn-ghost bw-btn-sm track-change-insert-btn"
          :class="{ 'is-active': editor.isActive('highlight', { color: TRACK_INSERT_COLOR }) }"
          @click="markSuggestion('insert')"
        >
          Suggest add
        </button>
        <button
          type="button"
          class="bw-btn bw-btn-ghost bw-btn-sm track-change-delete-btn"
          :class="{ 'is-active': editor.isActive('highlight', { color: TRACK_DELETE_COLOR }) }"
          @click="markSuggestion('delete')"
        >
          Suggest remove
        </button>
      </template>
    </div>

    <div
      class="grid"
      :class="[
        enableComments ? 'lg:grid-cols-[minmax(0,1fr)_220px]' : '',
        trackChangesMode ? 'track-changes-lite' : '',
      ]"
    >
      <EditorContent :editor="editor" />

      <aside
        v-if="enableComments"
        class="border-t border-border bg-surface lg:border-t-0 lg:border-l"
      >
        <div class="border-b border-border px-3 py-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Comments
          </p>
        </div>
        <div class="space-y-2 p-3">
          <textarea
            v-model="commentDraft"
            class="bw-input min-h-[72px] text-sm"
            placeholder="Add a comment on selected text…"
            rows="3"
          />
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm w-full"
            :disabled="!commentDraft.trim()"
            @click="addComment"
          >
            Mark selection
          </button>
          <ul v-if="comments.length" class="max-h-48 space-y-2 overflow-y-auto">
            <li
              v-for="comment in comments"
              :key="comment.id"
              class="cursor-pointer rounded-lg border border-border bg-surface px-2 py-1.5 text-xs"
              :class="{ 'border-primary-300 bg-primary-50': activeCommentId === comment.id }"
              @click="focusComment(comment)"
            >
              <p class="font-medium text-foreground">{{ comment.author }}</p>
              <p class="text-muted-foreground">{{ comment.text }}</p>
            </li>
          </ul>
          <p v-else class="text-xs text-muted-foreground">
            Select text, enter a note, then click Mark selection.
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
:deep(.bw-editor-comment) {
  background-color: rgb(254 243 199);
  border-bottom: 2px solid rgb(245 158 11);
  border-radius: 2px;
  padding: 0 1px;
}

:deep(mark) {
  background-color: rgb(254 249 195);
  border-radius: 2px;
  padding: 0 1px;
}

.track-change-insert-btn.is-active {
  background-color: rgb(220 252 231);
  color: rgb(21 128 61);
}

.track-change-delete-btn.is-active {
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}

:deep(.track-changes-lite mark) {
  border-radius: 2px;
  padding: 0 2px;
}

:deep(.track-changes-lite mark[data-color='#bbf7d0']),
:deep(.track-changes-lite mark[style*='background-color: rgb(187, 247, 208)']) {
  background-color: rgb(187 247 208) !important;
  border-bottom: 2px solid rgb(34 197 94);
}

:deep(.track-changes-lite mark[data-color='#fecaca']),
:deep(.track-changes-lite mark[style*='background-color: rgb(254, 202, 202)']) {
  background-color: rgb(254 202 202) !important;
  border-bottom: 2px solid rgb(239 68 68);
  text-decoration: line-through;
  text-decoration-color: rgb(185 28 28);
}
</style>
