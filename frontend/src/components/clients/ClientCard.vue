<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import {
  PhDotsThreeVertical,
  PhEnvelopeSimple,
  PhPhone,
} from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { humanize } from '@/lib/status'
import type { Client } from '@/types'

const props = defineProps<{
  client: Client
  selected?: boolean
}>()

const emit = defineEmits<{
  click: []
  'toggle-select': []
  view: []
  edit: []
  delete: []
}>()

const menuOpen = ref(false)

const accentBarClass = computed(() =>
  props.client.type === 'company' ? 'bg-accent-gold' : 'bg-action-teal',
)

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function clientSubtitle(client: Client) {
  if (client.type === 'company' && client.company_name) {
    return client.company_name
  }
  return humanize(client.type)
}

function locationLine(client: Client) {
  if (client.address) {
    const line = client.address.split('\n')[0]?.trim()
    if (line) return line
  }
  return client.company_name || '—'
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  if (menuOpen.value) {
    document.addEventListener('click', closeMenu)
  }
}

function closeMenu() {
  menuOpen.value = false
  document.removeEventListener('click', closeMenu)
}

onBeforeUnmount(() => document.removeEventListener('click', closeMenu))
</script>

<template>
  <article
    class="bw-card group flex cursor-pointer flex-col overflow-hidden transition-shadow hover:shadow-md"
    :class="selected ? 'ring-2 ring-action-teal ring-offset-2' : ''"
    @click="emit('click')"
  >
    <div class="h-1 shrink-0" :class="accentBarClass" aria-hidden="true" />

    <div class="relative flex flex-1 flex-col px-4 pb-4 pt-3">
      <div class="mb-4 flex items-start justify-between gap-2">
        <label
          class="flex cursor-pointer items-center"
          @click.stop
        >
          <input
            type="checkbox"
            class="h-4 w-4 rounded border-border text-action-teal focus:ring-action-teal"
            :checked="selected"
            :aria-label="`Select ${client.name}`"
            @change="emit('toggle-select')"
          />
        </label>

        <div class="flex items-center gap-1.5">
          <StatusBadge :status="client.status" :dot="false" />
          <div class="relative" @click.stop>
            <button
              type="button"
              class="bw-btn bw-btn-ghost bw-btn-icon rounded-full text-muted-foreground"
              :aria-expanded="menuOpen"
              aria-haspopup="menu"
              :aria-label="`Actions for ${client.name}`"
              @click.stop="toggleMenu"
            >
              <PhDotsThreeVertical class="h-5 w-5" weight="bold" />
            </button>
            <div
              v-if="menuOpen"
              class="absolute right-0 z-10 mt-1 w-36 overflow-hidden rounded-lg border border-border bg-surface py-1 shadow-md"
              role="menu"
            >
              <button
                type="button"
                class="flex w-full px-3 py-2 text-left text-sm text-foreground hover:bg-surface-muted"
                role="menuitem"
                @click="closeMenu(); emit('view')"
              >
                View
              </button>
              <button
                type="button"
                class="flex w-full px-3 py-2 text-left text-sm text-action-teal hover:bg-primary-50"
                role="menuitem"
                @click="closeMenu(); emit('edit')"
              >
                Edit
              </button>
              <button
                type="button"
                class="flex w-full px-3 py-2 text-left text-sm text-destructive hover:bg-surface-muted"
                role="menuitem"
                @click="closeMenu(); emit('delete')"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-col items-center text-center">
        <AppAvatar :name="client.name" size="xl" tone="accent" />
        <p class="mt-3 line-clamp-2 text-sm font-semibold text-foreground">
          {{ client.name }}
        </p>
        <p class="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
          {{ clientSubtitle(client) }}
        </p>
        <p
          v-if="client.client_number"
          class="mt-1 text-[11px] tabular-nums text-muted-foreground"
        >
          {{ client.client_number }}
        </p>
      </div>
    </div>

    <div class="border-t border-border bg-primary-50/50 px-4 py-3">
      <div class="grid grid-cols-2 gap-3 text-xs">
        <div>
          <p class="font-medium uppercase tracking-wide text-muted-foreground">Cases</p>
          <p class="mt-0.5 font-medium tabular-nums text-foreground">
            {{ client.legal_matters_count ?? 0 }}
          </p>
        </div>
        <div>
          <p class="font-medium uppercase tracking-wide text-muted-foreground">Since</p>
          <p class="mt-0.5 font-medium tabular-nums text-foreground">
            {{ formatDate(client.created_at) }}
          </p>
        </div>
        <div class="col-span-2">
          <p class="font-medium uppercase tracking-wide text-muted-foreground">Location</p>
          <p class="mt-0.5 line-clamp-1 font-medium text-foreground">
            {{ locationLine(client) }}
          </p>
        </div>
      </div>

      <div class="mt-3 space-y-1.5 border-t border-border/70 pt-3 text-xs text-muted-foreground">
        <div v-if="client.email" class="flex items-center gap-2">
          <PhEnvelopeSimple class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          <span class="truncate">{{ client.email }}</span>
        </div>
        <div v-else class="flex items-center gap-2 text-muted-foreground/70">
          <PhEnvelopeSimple class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          <span>No email</span>
        </div>
        <div v-if="client.phone" class="flex items-center gap-2">
          <PhPhone class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          <span class="truncate">{{ client.phone }}</span>
        </div>
        <div v-else class="flex items-center gap-2 text-muted-foreground/70">
          <PhPhone class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          <span>No phone</span>
        </div>
      </div>
    </div>
  </article>
</template>
