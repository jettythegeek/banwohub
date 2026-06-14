<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhCheckCircle, PhKanban, PhListBullets, PhPaperclip, PhPlus, PhX } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import { VueDraggable, type DraggableEvent } from 'vue-draggable-plus'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { caseTasksApi, usersApi, type CaseTaskPayload } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { statusDotVar } from '@/lib/status'
import { useAuthStore } from '@/stores/auth'
import type { CaseTask, TaskAttachment, TaskChecklistItem, TaskComment, User } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const viewMode = ref<'list' | 'kanban'>('kanban')
const tasks = ref<CaseTask[]>([])
const users = ref<User[]>([])
const auth = useAuthStore()
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const selectedTask = ref<CaseTask | null>(null)
const detailLoading = ref(false)
const attachments = ref<TaskAttachment[]>([])
const comments = ref<TaskComment[]>([])
const commentBody = ref('')
const checklistDraft = ref('')
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const isCommenting = ref(false)
const showCreateModal = ref(false)
const form = ref<CaseTaskPayload>({
  title: '',
  description: '',
  assignee_id: null,
  due_at: '',
  priority: 'normal',
  status: 'not_started',
})

const kanbanColumns = [
  { key: 'not_started', label: 'Not started' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'awaiting_review', label: 'Awaiting review' },
  { key: 'blocked', label: 'Blocked' },
  { key: 'completed', label: 'Completed' },
] as const

type KanbanColumnKey = (typeof kanbanColumns)[number]['key']

const columnTasks = ref<Record<KanbanColumnKey, CaseTask[]>>({
  not_started: [],
  in_progress: [],
  awaiting_review: [],
  blocked: [],
  completed: [],
})

const userOptions = computed(() => {
  const currentUser = auth.user
  if (!currentUser) return users.value
  if (users.value.some((user) => user.id === currentUser.id)) return users.value
  return [currentUser, ...users.value]
})

const orderedTasks = computed(() =>
  [...tasks.value].sort((a, b) => {
    const left = a.due_at ? new Date(a.due_at).getTime() : Number.MAX_SAFE_INTEGER
    const right = b.due_at ? new Date(b.due_at).getTime() : Number.MAX_SAFE_INTEGER
    return left - right
  }),
)

const detailChecklist = computed<TaskChecklistItem[]>(() => selectedTask.value?.checklist ?? [])

function isOverdue(task: CaseTask) {
  if (!task.due_at || task.status === 'completed') return false
  return new Date(task.due_at).getTime() < Date.now()
}

function syncColumnLists() {
  for (const column of kanbanColumns) {
    columnTasks.value[column.key] = tasks.value.filter((task) => task.status === column.key)
  }
}

function replaceTask(updated: CaseTask) {
  tasks.value = tasks.value.map((item) => (item.id === updated.id ? updated : item))
  if (selectedTask.value?.id === updated.id) {
    selectedTask.value = { ...selectedTask.value, ...updated }
  }
  syncColumnLists()
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [taskList, userList] = await Promise.all([
      caseTasksApi.list(props.caseId),
      usersApi.listActive().catch(() => []),
    ])
    tasks.value = taskList
    syncColumnLists()
    users.value = userList
    if (!form.value.assignee_id && auth.user?.id) {
      form.value.assignee_id = auth.user.id
    }
  } catch (err) {
    error.value = formatApiError(err, 'Tasks are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function openTaskDetail(task: CaseTask) {
  selectedTask.value = task
  detailLoading.value = true
  error.value = null
  try {
    const [full, attachmentList, commentList] = await Promise.all([
      caseTasksApi.get(task.id),
      caseTasksApi.listAttachments(task.id),
      caseTasksApi.listComments(task.id),
    ])
    selectedTask.value = full
    attachments.value = attachmentList
    comments.value = commentList
    replaceTask(full)
  } catch (err) {
    error.value = formatApiError(err, 'We could not load this task.')
  } finally {
    detailLoading.value = false
  }
}

function closeTaskDetail() {
  selectedTask.value = null
  attachments.value = []
  comments.value = []
  commentBody.value = ''
  checklistDraft.value = ''
  selectedFile.value = null
}

function resetForm() {
  form.value = {
    title: '',
    description: '',
    assignee_id: auth.user?.id ?? null,
    due_at: '',
    priority: 'normal',
    status: 'not_started',
  }
}

async function createTask() {
  if (!form.value.assignee_id) {
    error.value = 'Choose an assignee before saving this task.'
    return
  }
  isSaving.value = true
  error.value = null
  try {
    const created = await caseTasksApi.create(props.caseId, {
      ...form.value,
      description: form.value.description || null,
      due_at: form.value.due_at || null,
      assignee_id: Number(form.value.assignee_id),
    })
    tasks.value = [created, ...tasks.value]
    syncColumnLists()
    resetForm()
    showCreateModal.value = false
  } catch (err) {
    error.value = formatApiError(err, 'We could not save this task.')
  } finally {
    isSaving.value = false
  }
}

async function updateStatus(task: CaseTask, status: string) {
  if (task.status === status) return
  error.value = null
  const previous = task.status
  task.status = status
  try {
    const updated = await caseTasksApi.update(task.id, { status })
    replaceTask(updated)
  } catch (err) {
    task.status = previous
    syncColumnLists()
    error.value = formatApiError(err, 'We could not update this task.')
  }
}

async function onTaskDropped(columnKey: KanbanColumnKey, event: DraggableEvent<CaseTask>) {
  const list = columnTasks.value[columnKey]
  const task = event.data ?? list[event.newIndex ?? 0]
  if (!task) return
  await updateStatus(task, columnKey)
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
}

async function uploadAttachment() {
  if (!selectedTask.value || !selectedFile.value) return
  isUploading.value = true
  error.value = null
  try {
    const uploaded = await caseTasksApi.uploadAttachment(selectedTask.value.id, selectedFile.value)
    attachments.value = [uploaded, ...attachments.value]
    selectedFile.value = null
  } catch (err) {
    error.value = formatApiError(err, 'We could not upload this attachment.')
  } finally {
    isUploading.value = false
  }
}

async function removeAttachment(attachment: TaskAttachment) {
  if (!selectedTask.value) return
  error.value = null
  try {
    await caseTasksApi.deleteAttachment(selectedTask.value.id, attachment.id)
    attachments.value = attachments.value.filter((item) => item.id !== attachment.id)
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete this attachment.')
  }
}

async function downloadAttachment(attachment: TaskAttachment) {
  if (!selectedTask.value) return
  error.value = null
  try {
    await caseTasksApi.downloadAttachment(selectedTask.value.id, attachment.id, attachment.name)
  } catch (err) {
    error.value = formatApiError(err, 'We could not download this attachment.')
  }
}

async function submitComment() {
  if (!selectedTask.value || !commentBody.value.trim()) return
  isCommenting.value = true
  error.value = null
  try {
    const comment = await caseTasksApi.addComment(selectedTask.value.id, commentBody.value.trim())
    comments.value = [...comments.value, comment]
    commentBody.value = ''
  } catch (err) {
    error.value = formatApiError(err, 'We could not add this comment.')
  } finally {
    isCommenting.value = false
  }
}

async function saveChecklist(next: TaskChecklistItem[]) {
  if (!selectedTask.value) return
  error.value = null
  try {
    const updated = await caseTasksApi.update(selectedTask.value.id, { checklist: next })
    replaceTask(updated)
    selectedTask.value = { ...selectedTask.value, checklist: updated.checklist ?? next }
  } catch (err) {
    error.value = formatApiError(err, 'We could not update the checklist.')
  }
}

async function addChecklistItem() {
  const label = checklistDraft.value.trim()
  if (!label || !selectedTask.value) return
  const next: TaskChecklistItem[] = [
    ...detailChecklist.value,
    { id: crypto.randomUUID(), label, done: false },
  ]
  checklistDraft.value = ''
  await saveChecklist(next)
}

async function toggleChecklistItem(item: TaskChecklistItem) {
  const next = detailChecklist.value.map((entry) =>
    entry.id === item.id ? { ...entry, done: !entry.done } : entry,
  )
  await saveChecklist(next)
}

function formatDate(iso?: string | null) {
  if (!iso) return 'No due date'
  return new Date(iso).toLocaleString()
}

function formatDueShort(iso?: string | null) {
  if (!iso) return 'No due date'
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

function formatSize(bytes?: number) {
  if (!bytes) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function columnAccentStyle(status: string) {
  return { '--column-accent': `var(${statusDotVar(status)})` }
}

onMounted(load)
</script>

<template>
  <div
    class="flex min-h-[calc(100vh-16rem)] flex-col"
    :class="selectedTask ? 'xl:grid xl:min-h-0 xl:grid-cols-[minmax(0,1fr)_360px]' : ''"
  >
    <div class="flex min-h-0 min-w-0 flex-1 flex-col">
      <div
        class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-6"
      >
        <div>
          <h2 class="font-semibold text-foreground">Case tasks</h2>
          <p class="text-sm text-muted-foreground">
            Drag cards between columns or switch to list view.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <div
            class="flex items-center gap-0.5 rounded-lg border border-border bg-surface p-1"
            role="group"
            aria-label="Task view mode"
          >
            <button
              type="button"
              class="bw-btn bw-btn-sm"
              :class="viewMode === 'kanban' ? 'bw-btn-accent' : 'bw-btn-ghost'"
              aria-label="Kanban view"
              title="Kanban view"
              @click="viewMode = 'kanban'"
            >
              <PhKanban class="h-4 w-4" weight="bold" />
            </button>
            <button
              type="button"
              class="bw-btn bw-btn-sm"
              :class="viewMode === 'list' ? 'bw-btn-accent' : 'bw-btn-ghost'"
              aria-label="List view"
              title="List view"
              @click="viewMode = 'list'"
            >
              <PhListBullets class="h-4 w-4" weight="bold" />
            </button>
          </div>
          <button type="button" class="bw-btn bw-btn-accent bw-btn-sm" @click="showCreateModal = true">
            <PhPlus class="h-4 w-4" weight="bold" />
            Add task
          </button>
        </div>
      </div>

      <Skeleton v-if="isLoading" variant="panel" :rows="4" class="p-6" />
      <p v-else-if="error && !selectedTask" class="p-6 text-sm text-destructive" role="alert">
        {{ error }}
      </p>

      <div
        v-else-if="viewMode === 'kanban'"
        class="min-h-0 flex-1 overflow-x-auto bg-surface-muted/40 p-3 sm:p-4"
      >
        <div class="flex h-full min-h-[calc(100vh-20rem)] min-w-0 gap-3 lg:grid lg:grid-cols-5 lg:gap-3">
          <div
            v-for="column in kanbanColumns"
            :key="column.key"
            class="bw-kanban-column flex w-[min(100%,14rem)] shrink-0 flex-col lg:w-auto lg:min-w-0"
            :style="columnAccentStyle(column.key)"
          >
            <div class="mb-2 flex items-center gap-2 px-0.5">
              <h3 class="flex-1 truncate text-sm font-semibold text-foreground">
                {{ column.label }}
              </h3>
              <span
                class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-muted px-1.5 text-[11px] font-semibold tabular-nums text-muted-foreground"
              >
                {{ columnTasks[column.key].length }}
              </span>
            </div>
            <VueDraggable
              v-model="columnTasks[column.key]"
              group="case-tasks"
              :animation="200"
              ghost-class="bw-kanban-ghost"
              class="bw-kanban-column-body min-h-[8rem] flex-1 space-y-2 overflow-y-auto pr-0.5"
              @add="onTaskDropped(column.key, $event)"
            >
              <article
                v-for="task in columnTasks[column.key]"
                :key="task.id"
                class="bw-kanban-card cursor-pointer rounded-lg border border-border/70 bg-white p-2.5 text-sm shadow-sm transition-shadow hover:shadow-md"
                :class="{ 'ring-2 ring-[var(--action-teal)] ring-offset-1': selectedTask?.id === task.id }"
                @click="openTaskDetail(task)"
              >
                <p class="line-clamp-2 font-medium leading-snug text-foreground">{{ task.title }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-1">
                  <StatusBadge :status="task.priority" :dot="false" />
                  <span v-if="isOverdue(task)" class="bw-badge bw-badge-danger text-[10px]">
                    Overdue
                  </span>
                </div>
                <div class="mt-2.5 flex items-center justify-between gap-2">
                  <div class="flex min-w-0 items-center gap-1.5">
                    <AppAvatar
                      :name="task.assignee?.name || 'Unassigned'"
                      size="sm"
                      tone="primary"
                    />
                    <span class="truncate text-xs text-muted-foreground">
                      {{ task.assignee?.name || 'Unassigned' }}
                    </span>
                  </div>
                  <span
                    class="shrink-0 text-[11px] tabular-nums"
                    :class="isOverdue(task) ? 'font-medium text-destructive' : 'text-muted-foreground'"
                  >
                    {{ formatDueShort(task.due_at) }}
                  </span>
                </div>
              </article>
            </VueDraggable>
          </div>
        </div>
      </div>

      <div v-else-if="orderedTasks.length" class="divide-y divide-border">
        <article
          v-for="task in orderedTasks"
          :key="task.id"
          class="flex cursor-pointer flex-wrap items-start justify-between gap-4 px-6 py-4 hover:bg-surface-muted"
          :class="{ 'bw-row-selected': selectedTask?.id === task.id }"
          @click="openTaskDetail(task)"
        >
          <div class="min-w-[220px] flex-1 space-y-2">
            <div>
              <h3 class="font-medium text-foreground">{{ task.title }}</h3>
              <p v-if="task.description" class="text-sm text-muted-foreground">
                {{ task.description }}
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <StatusBadge :status="task.status" />
              <StatusBadge :status="task.priority" :dot="false" />
              <span v-if="isOverdue(task)" class="bw-badge bw-badge-danger">Overdue</span>
              <span class="inline-flex items-center gap-1.5 bw-badge bw-badge-neutral">
                <AppAvatar :name="task.assignee?.name || 'Unassigned'" size="sm" tone="primary" />
                {{ task.assignee?.name || 'Unassigned' }}
              </span>
              <span class="bw-badge bw-badge-neutral tabular-nums">
                {{ formatDate(task.due_at) }}
              </span>
            </div>
          </div>

          <select
            :value="task.status"
            class="bw-select w-auto"
            aria-label="Task status"
            @click.stop
            @change="updateStatus(task, ($event.target as HTMLSelectElement).value)"
          >
            <option value="not_started">Not started</option>
            <option value="in_progress">In progress</option>
            <option value="awaiting_review">Awaiting review</option>
            <option value="blocked">Blocked</option>
            <option value="completed">Completed</option>
          </select>
        </article>
      </div>
      <EmptyState
        v-else
        :icon="PhCheckCircle"
        title="No tasks yet"
        message="Add one so the next step does not get lost."
        class="p-6"
      />
    </div>

    <section
      v-if="selectedTask"
      class="border-t border-border bg-surface p-6 xl:border-l xl:border-t-0"
    >
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold text-foreground">{{ selectedTask.title }}</h2>
          <p v-if="selectedTask.description" class="mt-1 text-sm text-muted-foreground">
            {{ selectedTask.description }}
          </p>
        </div>
        <button
          type="button"
          class="bw-btn bw-btn-sm bw-btn-outline"
          aria-label="Close task detail"
          @click="closeTaskDetail"
        >
          <PhX :size="16" />
        </button>
      </div>

      <div class="flex flex-wrap gap-2 text-xs">
        <StatusBadge :status="selectedTask.status" />
        <StatusBadge :status="selectedTask.priority" :dot="false" />
        <span class="bw-badge bw-badge-neutral">
          {{ selectedTask.assignee?.name || 'Unassigned' }}
        </span>
      </div>

      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <Skeleton v-if="detailLoading" variant="panel" :rows="3" />

      <template v-else>
        <div class="space-y-3 border-t border-border pt-4">
          <h3 class="text-sm font-semibold text-foreground">Checklist</h3>
          <ul v-if="detailChecklist.length" class="space-y-2">
            <li
              v-for="item in detailChecklist"
              :key="item.id"
              class="flex items-center gap-2 text-sm"
            >
              <input
                :id="`check-${item.id}`"
                type="checkbox"
                class="bw-checkbox"
                :checked="item.done"
                @change="toggleChecklistItem(item)"
              />
              <label
                :for="`check-${item.id}`"
                class="flex-1"
                :class="item.done ? 'text-muted-foreground line-through' : 'text-foreground'"
              >
                {{ item.label }}
              </label>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">No checklist items yet.</p>
          <form class="flex gap-2" @submit.prevent="addChecklistItem">
            <input
              v-model="checklistDraft"
              class="bw-input flex-1"
              placeholder="Add checklist item…"
            />
            <button type="submit" class="bw-btn bw-btn-outline bw-btn-sm" :disabled="!checklistDraft.trim()">
              Add
            </button>
          </form>
        </div>

        <div class="space-y-3 border-t border-border pt-4">
          <div class="flex items-center gap-2">
            <PhPaperclip :size="18" class="text-muted-foreground" />
            <h3 class="text-sm font-semibold text-foreground">Attachments</h3>
          </div>
          <ul v-if="attachments.length" class="divide-y divide-border rounded-lg border border-border">
            <li
              v-for="attachment in attachments"
              :key="attachment.id"
              class="flex items-center justify-between gap-2 px-3 py-2 text-sm"
            >
              <button
                type="button"
                class="truncate text-left text-primary-700 hover:underline"
                @click="downloadAttachment(attachment)"
              >
                {{ attachment.name }}
              </button>
              <div class="flex shrink-0 items-center gap-2">
                <span class="text-xs text-muted-foreground">{{ formatSize(attachment.size) }}</span>
                <button
                  type="button"
                  class="bw-btn bw-btn-sm bw-btn-outline"
                  @click="removeAttachment(attachment)"
                >
                  Remove
                </button>
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">No attachments yet.</p>
          <form class="space-y-2" @submit.prevent="uploadAttachment">
            <input type="file" class="bw-input" @change="handleFileChange" />
            <button
              type="submit"
              class="bw-btn bw-btn-outline w-full"
              :disabled="!selectedFile || isUploading"
            >
              {{ isUploading ? 'Uploading…' : 'Upload attachment' }}
            </button>
          </form>
        </div>

        <div class="space-y-3 border-t border-border pt-4">
          <h3 class="text-sm font-semibold text-foreground">Comments</h3>
          <ul v-if="comments.length" class="max-h-56 space-y-3 overflow-y-auto">
            <li
              v-for="comment in comments"
              :key="comment.id"
              class="rounded-lg border border-border bg-white p-3 text-sm"
            >
              <p class="font-medium text-foreground">{{ comment.user?.name || 'Staff' }}</p>
              <p class="mt-1 text-muted-foreground">{{ comment.body }}</p>
              <p class="mt-2 text-xs text-muted-foreground">{{ formatDate(comment.created_at) }}</p>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">No comments yet.</p>
          <form class="space-y-2" @submit.prevent="submitComment">
            <textarea
              v-model="commentBody"
              rows="3"
              class="bw-textarea"
              placeholder="Add a comment…"
            />
            <button
              type="submit"
              class="bw-btn bw-btn-action w-full"
              :disabled="!commentBody.trim() || isCommenting"
            >
              {{ isCommenting ? 'Posting…' : 'Post comment' }}
            </button>
          </form>
        </div>
      </template>
    </section>

    <BwModal
      :open="showCreateModal"
      title="New task"
      size="md"
      @close="showCreateModal = false"
    >
      <form id="task-form" class="space-y-4" @submit.prevent="createTask">
        <div>
          <label class="bw-label" for="task-title">Title</label>
          <input
            id="task-title"
            v-model="form.title"
            required
            class="bw-input"
            placeholder="Prepare hearing bundle"
          />
        </div>
        <div>
          <label class="bw-label" for="task-description">Description</label>
          <textarea
            id="task-description"
            v-model="form.description"
            rows="3"
            class="bw-textarea"
            placeholder="Helpful context for the assignee."
          />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="task-assignee">Assignee</label>
            <select id="task-assignee" v-model="form.assignee_id" class="bw-select">
              <option :value="null">Choose assignee</option>
              <option v-for="user in userOptions" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="bw-label" for="task-due">Due date</label>
            <input id="task-due" v-model="form.due_at" type="datetime-local" class="bw-input" />
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="task-priority">Priority</label>
            <select id="task-priority" v-model="form.priority" class="bw-select">
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <label class="bw-label" for="task-status">Status</label>
            <select id="task-status" v-model="form.status" class="bw-select">
              <option value="not_started">Not started</option>
              <option value="in_progress">In progress</option>
              <option value="awaiting_review">Awaiting review</option>
              <option value="blocked">Blocked</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showCreateModal = false">
          Cancel
        </button>
        <button type="submit" form="task-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Add task' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
