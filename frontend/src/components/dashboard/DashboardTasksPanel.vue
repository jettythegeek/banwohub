<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { PhCheckCircle } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { formatDue } from '@/composables/useDashboard'
import type { DashboardTask } from '@/types'

defineProps<{
  tasks: DashboardTask[]
  isLoading?: boolean
  title?: string
  subtitle?: string
  limit?: number
}>()
</script>

<template>
  <section class="bw-card">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">{{ title ?? 'My tasks' }}</h2>
        <p class="text-sm text-muted-foreground">
          {{ subtitle ?? 'Open work assigned to you, soonest due first.' }}
        </p>
      </div>
      <RouterLink
        to="/legal-projects"
        class="text-sm font-medium text-primary-700 hover:underline"
      >
        View all
      </RouterLink>
    </div>

    <Skeleton v-if="isLoading" variant="panel" :rows="4" />
    <div v-else-if="tasks.length" class="divide-y divide-border">
      <RouterLink
        v-for="task in (limit ? tasks.slice(0, limit) : tasks)"
        :key="task.id"
        :to="task.case ? `/cases/${task.case.id}/tasks` : '/dashboard'"
        class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-surface-muted"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-foreground">
            {{ task.title }}
          </p>
          <p v-if="task.case" class="truncate text-xs text-muted-foreground">
            {{ task.case.title }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <StatusBadge v-if="task.is_overdue" status="overdue" label="Overdue" />
          <StatusBadge :status="task.priority" :dot="false" />
          <span class="hidden text-xs tabular-nums text-muted-foreground sm:inline">
            {{ formatDue(task.due_at) }}
          </span>
        </div>
      </RouterLink>
    </div>
    <EmptyState
      v-else
      :icon="PhCheckCircle"
      title="You're all caught up"
      message="No open tasks are assigned to you right now."
    />
  </section>
</template>
