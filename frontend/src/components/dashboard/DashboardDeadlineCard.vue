<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { PhCalendarBlank } from '@phosphor-icons/vue'
import { formatDeadlineDate } from '@/composables/useDashboard'
import type { CalendarHubItem } from '@/types'

const props = defineProps<{
  deadline: CalendarHubItem | null
  isLoading?: boolean
  daysUntil: number | null
  ringProgress: number
}>()

const ariaLabel = computed(() =>
  props.deadline
    ? `Next deadline in ${props.daysUntil ?? 0} days`
    : 'No upcoming deadlines',
)
</script>

<template>
  <section class="bw-card p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          Next deadline
        </p>
        <template v-if="isLoading">
          <p class="mt-2 text-lg font-semibold text-foreground">…</p>
        </template>
        <template v-else-if="deadline">
          <p class="mt-2 line-clamp-2 text-sm font-semibold text-foreground">
            {{ deadline.title }}
          </p>
          <p class="mt-1 text-xs tabular-nums text-muted-foreground">
            {{ formatDeadlineDate(deadline.starts_at) }}
            <span v-if="daysUntil !== null">
              · {{ daysUntil === 0 ? 'Today' : `${daysUntil}d` }}
            </span>
          </p>
          <RouterLink
            v-if="deadline.case"
            :to="`/cases/${deadline.case.id}/calendar`"
            class="mt-2 inline-block text-xs font-medium text-primary-700 hover:underline"
          >
            {{ deadline.case.title }}
          </RouterLink>
        </template>
        <p v-else class="mt-2 text-sm text-muted-foreground">No upcoming deadlines</p>
      </div>
      <div
        class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-accent-700"
        :style="{
          background: deadline
            ? `conic-gradient(var(--color-accent-700) ${ringProgress}%, var(--color-muted) 0)`
            : 'conic-gradient(var(--color-muted) 0% 100%)',
        }"
        role="img"
        :aria-label="ariaLabel"
      >
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-surface">
          <PhCalendarBlank
            class="h-5 w-5"
            :class="deadline ? 'text-accent-700' : 'text-muted-foreground'"
            weight="fill"
          />
        </span>
      </div>
    </div>
  </section>
</template>
