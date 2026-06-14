<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhBriefcase } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { portalCasesApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import type { LegalMatter } from '@/types'

const cases = ref<LegalMatter[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(async () => {
  isLoading.value = true
  error.value = null
  try {
    cases.value = await portalCasesApi.list()
  } catch (err) {
    error.value = formatApiError(err, 'Cases are not available yet.')
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="My cases" subtitle="Track the status of your legal matters." />

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <div v-if="isLoading" class="space-y-3">
      <Skeleton v-for="n in 4" :key="n" class="h-20 rounded-lg" />
    </div>
    <div v-else-if="cases.length" class="bw-card divide-y divide-border overflow-hidden">
      <RouterLink
        v-for="matter in cases"
        :key="matter.id"
        :to="`/portal/cases/${matter.id}`"
        class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 hover:bg-surface-muted"
      >
        <div class="flex min-w-0 items-start gap-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
            <PhBriefcase class="h-4 w-4" weight="fill" />
          </span>
          <div class="min-w-0">
            <h3 class="truncate font-medium">{{ matter.title }}</h3>
            <p class="text-sm text-muted-foreground">
              {{ matter.matter_number || 'No matter number' }}
              <span v-if="matter.lead_lawyer"> · {{ matter.lead_lawyer.name }}</span>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <StatusBadge :status="matter.status" />
          <span class="text-sm text-muted-foreground">Opened {{ formatDate(matter.opened_at) }}</span>
        </div>
      </RouterLink>
    </div>
    <EmptyState
      v-else
      :icon="PhBriefcase"
      title="No cases yet"
      message="Your active matters will appear here once your firm opens a case for you."
    />
  </div>
</template>
