<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhKanban, PhUsersThree } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { legalProjectsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { LegalProjectWorkload, TaskWorkloadBoard } from '@/types'

const workload = ref<LegalProjectWorkload | null>(null)
const taskBoard = ref<TaskWorkloadBoard | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)

const boardColumns = [
  { key: 'not_started', label: 'Not started' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'awaiting_review', label: 'Awaiting review' },
  { key: 'blocked', label: 'Blocked' },
  { key: 'overdue', label: 'Overdue' },
] as const

const maxTaskLoad = computed(() => {
  const rows = workload.value?.by_lawyer ?? []
  return Math.max(...rows.map((row) => row.open_tasks + row.open_matters), 1)
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [workloadData, boardData] = await Promise.all([
      legalProjectsApi.workload(),
      legalProjectsApi.taskBoard(),
    ])
    workload.value = workloadData
    taskBoard.value = boardData
  } catch (err) {
    error.value = formatApiError(err, 'Workload data is not available yet.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Legal projects"
      subtitle="Firm-wide workload, open tasks, and milestone backlog."
    />

    <p v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
      {{ error }}
    </p>

    <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Skeleton v-for="n in 4" :key="n" class="h-24 rounded-xl" />
    </div>

    <template v-else-if="workload">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Open matters</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-foreground">
            {{ workload.totals.open_matters }}
          </p>
        </div>
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Open tasks</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-foreground">
            {{ workload.totals.open_tasks }}
          </p>
        </div>
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Overdue tasks</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-destructive">
            {{ workload.totals.overdue_tasks }}
          </p>
        </div>
        <div class="bw-card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pending milestones</p>
          <p class="mt-2 text-3xl font-semibold tabular-nums text-foreground">
            {{ workload.totals.pending_milestones }}
          </p>
        </div>
      </div>

      <div class="bw-card">
        <div class="flex items-center gap-2 border-b border-border px-6 py-4">
          <PhUsersThree class="h-5 w-5 text-primary-700" />
          <h2 class="font-semibold text-foreground">Workload by lawyer</h2>
        </div>

        <div v-if="workload.by_lawyer.length" class="divide-y divide-border">
          <div
            v-for="row in workload.by_lawyer"
            :key="row.user_id"
            class="grid gap-4 px-6 py-4 sm:grid-cols-[minmax(0,1fr)_auto]"
          >
            <div>
              <p class="font-medium text-foreground">{{ row.name }}</p>
              <p class="mt-1 text-sm text-muted-foreground">
                {{ row.open_matters }} open matters · {{ row.open_tasks }} open tasks
                <span v-if="row.overdue_tasks"> · {{ row.overdue_tasks }} overdue</span>
              </p>
              <p v-if="row.matter_titles.length" class="mt-2 text-xs text-muted-foreground">
                {{ row.matter_titles.join(' · ') }}
              </p>
            </div>
            <div class="flex min-w-[160px] items-center gap-2">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-primary-600"
                  :style="{ width: `${Math.min(100, ((row.open_tasks + row.open_matters) / maxTaskLoad) * 100)}%` }"
                />
              </div>
              <StatusBadge
                :status="row.overdue_tasks > 0 ? 'overdue' : row.open_tasks > 5 ? 'high' : 'normal'"
              />
            </div>
          </div>
        </div>
        <EmptyState
          v-else
          class="py-12"
          title="No workload data"
          description="Assign lead lawyers and tasks to see workload distribution."
        />
      </div>

      <div v-if="taskBoard" class="bw-card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-6 py-4">
          <div class="flex items-center gap-2">
            <PhKanban class="h-5 w-5 text-primary-700" />
            <h2 class="font-semibold text-foreground">Team task board</h2>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ taskBoard.totals.open_tasks }} open · {{ taskBoard.totals.overdue_tasks }} overdue
          </p>
        </div>

        <div class="grid gap-4 overflow-x-auto p-4 lg:grid-cols-5">
          <div
            v-for="column in boardColumns"
            :key="column.key"
            class="min-w-[200px] rounded-lg border border-border bg-surface p-3"
          >
            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ column.label }}
              <span class="ml-1 tabular-nums">
                ({{ (taskBoard.board[column.key] ?? []).length }})
              </span>
            </h3>
            <ul class="mt-3 space-y-2">
              <li
                v-for="task in (taskBoard.board[column.key] ?? []).slice(0, 8)"
                :key="task.id"
                class="rounded-md border border-border bg-card p-3 text-sm"
              >
                <p class="font-medium text-foreground line-clamp-2">{{ task.title }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                  {{ task.assignee?.name ?? 'Unassigned' }}
                  <span v-if="task.case"> · {{ task.case.title }}</span>
                </p>
                <StatusBadge
                  v-if="task.is_overdue"
                  class="mt-2"
                  status="overdue"
                />
              </li>
              <li
                v-if="!(taskBoard.board[column.key] ?? []).length"
                class="py-4 text-center text-xs text-muted-foreground"
              >
                No tasks
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
