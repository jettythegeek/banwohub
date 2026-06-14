<script setup lang="ts">
import { computed } from 'vue'
import { humanize } from '@/lib/status'
import type { ConflictCheck } from '@/types'

const props = defineProps<{
  check: ConflictCheck | null
}>()

const emit = defineEmits<{
  close: []
  exportCsv: []
  exportHtml: []
}>()

const matchGroups = computed(() => Object.entries(props.check?.matches ?? {}))
</script>

<template>
  <div
    v-if="check"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
    @click.self="emit('close')"
  >
    <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg border border-border bg-surface">
      <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
        <div>
          <h2 class="text-lg font-semibold text-foreground">Conflict report</h2>
          <p class="text-sm text-muted-foreground">{{ check.search_terms.join(', ') }}</p>
        </div>
        <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="emit('close')">
          Close
        </button>
      </div>

      <div class="space-y-6 px-6 py-5 text-sm">
        <dl class="grid gap-3 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</dt>
            <dd class="mt-1 text-foreground">{{ humanize(check.status) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Decision</dt>
            <dd class="mt-1 text-foreground">{{ check.decision || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Reviewer</dt>
            <dd class="mt-1 text-foreground">{{ check.reviewer?.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Reviewed</dt>
            <dd class="mt-1 text-foreground">
              {{ check.reviewed_at ? new Date(check.reviewed_at).toLocaleString() : '—' }}
            </dd>
          </div>
        </dl>

        <div v-if="check.notes">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notes</p>
          <p class="mt-2 whitespace-pre-wrap text-foreground">{{ check.notes }}</p>
        </div>

        <div v-for="[bucket, items] in matchGroups" :key="bucket" class="space-y-2">
          <h3 class="font-medium text-foreground">
            {{ humanize(bucket) }} ({{ Array.isArray(items) ? items.length : 0 }})
          </h3>
          <div
            v-if="Array.isArray(items) && items.length"
            class="divide-y divide-border rounded-lg border border-border"
          >
            <pre
              v-for="(item, index) in items"
              :key="index"
              class="overflow-x-auto p-3 text-xs text-muted-foreground"
            >{{ JSON.stringify(item, null, 2) }}</pre>
          </div>
          <p v-else class="text-muted-foreground">No matches.</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 border-t border-border px-6 py-4">
        <button type="button" class="bw-btn bw-btn-outline" @click="emit('exportCsv')">
          Export CSV
        </button>
        <button type="button" class="bw-btn bw-btn-outline" @click="emit('exportHtml')">
          Export HTML
        </button>
      </div>
    </section>
  </div>
</template>
