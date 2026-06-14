<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhMagnifyingGlass, PhTray } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { ediscoveryApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import type { EdiscoveryDocument } from '@/types'

const documents = ref<EdiscoveryDocument[]>([])
const privileges = ref<string[]>([])
const relevances = ref<string[]>([])
const reviewStatuses = ref<string[]>([])
const privilegeFilter = ref('')
const relevanceFilter = ref('')
const statusFilter = ref('')
const tagFilter = ref('')
const search = ref('')
const isLoading = ref(true)
const error = ref<string | null>(null)

const filteredDocuments = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return documents.value
  return documents.value.filter((doc) =>
    [
      doc.title,
      doc.privilege,
      doc.relevance,
      doc.review_status,
      doc.sender,
      doc.recipient,
      doc.legal_matter?.title,
      ...(doc.custom_tags ?? []),
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(needle)),
  )
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await ediscoveryApi.listDocuments({
      privilege: privilegeFilter.value || undefined,
      relevance: relevanceFilter.value || undefined,
      review_status: statusFilter.value || undefined,
      tag: tagFilter.value || undefined,
    })
    documents.value = result.documents
    privileges.value = result.privileges
    relevances.value = result.relevances
    reviewStatuses.value = result.reviewStatuses
  } catch (err) {
    error.value = formatApiError(err, 'E-discovery is not available yet.')
  } finally {
    isLoading.value = false
  }
}

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="E-discovery"
      subtitle="Review discovery collections, tag documents for privilege and relevance, and track reviewer progress."
    />

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div class="relative max-w-md flex-1">
        <PhMagnifyingGlass
          class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <input
          v-model="search"
          type="search"
          class="bw-input pl-9"
          placeholder="Search documents, tags, cases…"
        />
      </div>
      <div class="flex flex-wrap gap-2">
        <select v-model="privilegeFilter" class="bw-input w-full sm:w-40" @change="load">
          <option value="">All privilege</option>
          <option v-for="value in privileges" :key="value" :value="value">
            {{ humanize(value) }}
          </option>
        </select>
        <select v-model="relevanceFilter" class="bw-input w-full sm:w-40" @change="load">
          <option value="">All relevance</option>
          <option v-for="value in relevances" :key="value" :value="value">
            {{ humanize(value) }}
          </option>
        </select>
        <select v-model="statusFilter" class="bw-input w-full sm:w-40" @change="load">
          <option value="">All statuses</option>
          <option v-for="value in reviewStatuses" :key="value" :value="value">
            {{ humanize(value) }}
          </option>
        </select>
        <input
          v-model="tagFilter"
          class="bw-input w-full sm:w-36"
          placeholder="Tag filter"
          @change="load"
        />
      </div>
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

    <div v-if="isLoading" class="space-y-3">
      <Skeleton v-for="n in 4" :key="n" class="h-16 w-full" />
    </div>

    <EmptyState
      v-else-if="filteredDocuments.length === 0"
      title="No discovery documents yet"
      description="Create a collection and bulk-upload documents from a case workspace E-discovery tab."
      :icon="PhTray"
    />

    <div v-else class="bw-card overflow-hidden">
      <table class="bw-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Case</th>
            <th>Collection</th>
            <th>Privilege</th>
            <th>Relevance</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in filteredDocuments" :key="doc.id">
            <td class="font-medium">{{ doc.title }}</td>
            <td>
              <RouterLink
                v-if="doc.legal_matter"
                :to="`/cases/${doc.legal_matter_id}/e-discovery`"
                class="text-primary hover:underline"
              >
                {{ doc.legal_matter.title }}
              </RouterLink>
            </td>
            <td>{{ doc.collection?.name ?? '—' }}</td>
            <td>{{ humanize(doc.privilege) }}</td>
            <td>{{ humanize(doc.relevance) }}</td>
            <td><StatusBadge :status="doc.review_status" /></td>
            <td>{{ formatDate(doc.document_date) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
