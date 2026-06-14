<script setup lang="ts">
import { RouterLink } from 'vue-router'
import {
  PhArrowRight,
  PhBriefcase,
  PhDotsThree,
  PhFolderOpen,
} from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { humanize } from '@/lib/status'
import type { DashboardData } from '@/types'

defineProps<{
  cases: DashboardData['recent_cases']
  title?: string
  subtitle?: string
  limit?: number
}>()
</script>

<template>
  <section class="bw-card">
    <div class="bw-card-header">
      <div>
        <h2 class="font-semibold text-foreground">{{ title ?? 'Your recent matters' }}</h2>
        <p class="text-sm text-muted-foreground">
          {{ subtitle ?? 'Latest cases across the practice.' }}
        </p>
      </div>
      <RouterLink
        to="/cases"
        class="inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline"
      >
        All cases <PhArrowRight class="h-3.5 w-3.5" />
      </RouterLink>
    </div>
    <div
      v-if="cases.length"
      class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3"
    >
      <RouterLink
        v-for="c in (limit ? cases.slice(0, limit) : cases)"
        :key="c.id"
        :to="`/cases/${c.id}`"
        class="bw-card overflow-hidden transition-colors hover:border-border-strong"
      >
        <div class="h-1 bg-action-teal" aria-hidden="true" />
        <div class="flex items-start justify-between gap-2 p-4">
          <div class="flex min-w-0 items-center gap-3">
            <AppAvatar :name="c.title" size="sm" tone="primary" />
            <p class="truncate text-sm font-semibold text-foreground">{{ c.title }}</p>
          </div>
          <PhDotsThree class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
        </div>
        <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
          <StatusBadge :status="c.status" />
          <span
            class="rounded-full border border-border bg-surface px-2 py-0.5 text-xs text-muted-foreground"
          >
            Case #{{ c.id }}
          </span>
        </div>
        <div class="flex divide-x divide-border border-t border-border text-xs text-muted-foreground">
          <div class="flex flex-1 items-center gap-1.5 px-4 py-2.5">
            <PhFolderOpen class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">Matter</span>
          </div>
          <div class="flex flex-1 items-center gap-1.5 px-4 py-2.5">
            <PhBriefcase class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ humanize(c.status) }}</span>
          </div>
        </div>
      </RouterLink>
    </div>
    <EmptyState
      v-else
      :icon="PhBriefcase"
      title="No cases yet"
      message="Assigned cases will show up here."
      class="border-0 shadow-none"
    />
  </section>
</template>
