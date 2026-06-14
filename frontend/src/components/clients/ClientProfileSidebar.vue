<script setup lang="ts">
import { RouterLink } from 'vue-router'
import {
  PhBriefcase,
  PhChatCircle,
  PhFolderOpen,
  PhPencilSimple,
  PhReceipt,
  PhUsersThree,
} from '@phosphor-icons/vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { initialsOf } from '@/lib/status'
import type { Client } from '@/types'

const props = withDefaults(
  defineProps<{
    client: Client
    editMode?: boolean
    activeTab?: string
  }>(),
  { editMode: false },
)

const emit = defineEmits<{
  'change-tab': [tab: string]
}>()

function kpiValue(value?: number) {
  return value ?? 0
}
</script>

<template>
  <aside class="space-y-6 lg:col-span-1">
    <section class="bw-card overflow-hidden p-6">
      <div class="flex flex-col items-center text-center">
        <div
          class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-xl bg-primary-50 text-2xl font-semibold text-primary-700"
          aria-hidden="true"
        >
          {{ initialsOf(client.name) }}
        </div>
        <button
          v-if="editMode"
          type="button"
          class="mt-3 text-sm font-medium text-action-teal hover:underline"
          disabled
          title="Photo upload coming soon"
        >
          Change photo
        </button>

        <p class="mt-4 text-base font-semibold text-foreground">{{ client.name }}</p>
        <p v-if="client.client_number" class="mt-0.5 text-xs tabular-nums text-muted-foreground">
          {{ client.client_number }}
        </p>
        <div class="mt-2">
          <StatusBadge :status="client.status" />
        </div>
      </div>

      <div v-if="!editMode" class="mt-6 space-y-2">
        <RouterLink
          :to="`/clients/${client.id}/edit`"
          class="bw-btn bw-btn-action w-full"
        >
          <PhPencilSimple class="h-4 w-4" />
          Edit profile
        </RouterLink>
        <button
          type="button"
          class="bw-btn bw-btn-outline w-full"
          @click="emit('change-tab', 'cases')"
        >
          <PhFolderOpen class="h-4 w-4" />
          View cases
        </button>
        <RouterLink
          :to="{ path: '/cases/new', query: { client_id: client.id } }"
          class="bw-btn bw-btn-accent w-full"
        >
          <PhBriefcase class="h-4 w-4" weight="bold" />
          New case
        </RouterLink>
      </div>

      <div class="mt-6 grid grid-cols-2 gap-3">
        <div class="rounded-lg border border-border bg-surface px-3 py-3 text-center">
          <PhFolderOpen class="mx-auto h-5 w-5 text-muted-foreground" />
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ kpiValue(client.legal_matters_count) }}
          </p>
          <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
            Total cases
          </p>
        </div>
        <div class="rounded-lg border border-border bg-surface px-3 py-3 text-center">
          <PhBriefcase class="mx-auto h-5 w-5 text-muted-foreground" />
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ kpiValue(client.open_legal_matters_count) }}
          </p>
          <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
            Open cases
          </p>
        </div>
        <div class="rounded-lg border border-border bg-surface px-3 py-3 text-center">
          <PhReceipt class="mx-auto h-5 w-5 text-muted-foreground" />
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ kpiValue(client.invoices_count) }}
          </p>
          <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
            Invoices
          </p>
        </div>
        <div class="rounded-lg border border-border bg-surface px-3 py-3 text-center">
          <PhUsersThree class="mx-auto h-5 w-5 text-muted-foreground" />
          <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">
            {{ kpiValue(client.contacts_count) }}
          </p>
          <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
            Contacts
          </p>
        </div>
      </div>

      <div
        v-if="client.communication_logs_count"
        class="mt-4 flex items-center justify-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-muted-foreground"
      >
        <PhChatCircle class="h-4 w-4" />
        {{ client.communication_logs_count }} communication log{{ client.communication_logs_count === 1 ? '' : 's' }}
      </div>
    </section>
  </aside>
</template>
