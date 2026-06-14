<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { PhBriefcase, PhPlus } from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { humanize, statusDotVar } from '@/lib/status'
import type { Client } from '@/types'

defineProps<{
  client: Client
  embedded?: boolean
}>()
</script>

<template>
  <section :class="embedded ? '' : 'bw-card overflow-hidden'">
    <div :class="embedded ? 'mb-4 flex items-start justify-between gap-4' : 'bw-card-header'">
      <div>
        <h2 class="font-semibold text-foreground">Cases</h2>
        <p class="text-sm text-muted-foreground">Matters linked to this client.</p>
      </div>
      <RouterLink
        :to="{ path: '/cases/new', query: { client_id: client.id } }"
        class="bw-btn bw-btn-accent bw-btn-sm"
      >
        <PhPlus class="h-4 w-4" weight="bold" />
        New case
      </RouterLink>
    </div>

    <div
      v-if="client.legal_matters?.length"
      :class="embedded ? 'bw-card divide-y divide-border overflow-hidden' : 'divide-y divide-border'"
    >
      <RouterLink
        v-for="m in client.legal_matters"
        :key="m.id"
        :to="`/cases/${m.id}`"
        class="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-surface-muted"
      >
        <AppAvatar :name="m.title" size="sm" tone="primary" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-foreground">{{ m.title }}</p>
          <p v-if="m.matter_number" class="truncate text-xs tabular-nums text-muted-foreground">
            {{ m.matter_number }}
          </p>
          <p v-if="m.matter_stage" class="truncate text-xs capitalize text-muted-foreground">
            {{ humanize(m.matter_stage) }}
          </p>
        </div>
        <span class="inline-flex items-center gap-2">
          <span
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :style="{ backgroundColor: `var(${statusDotVar(m.status)})` }"
            aria-hidden="true"
          />
          <StatusBadge :status="m.status" :dot="false" />
        </span>
      </RouterLink>
    </div>
    <EmptyState
      v-else
      :icon="PhBriefcase"
      title="No cases linked yet"
      description="Open a new matter for this client to start tracking work."
      :class="embedded ? 'bw-card py-12' : 'py-12'"
    />
  </section>
</template>
