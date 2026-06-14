<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { approvalsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { humanize } from '@/lib/status'
import { usePermissions } from '@/composables/usePermissions'
import type { ApprovalRequest, ApprovalSubjectType } from '@/types'

const props = defineProps<{
  subjectType: ApprovalSubjectType
  subjectId: number
  requiresApproval?: boolean
}>()

const emit = defineEmits<{
  updated: []
}>()

const { can } = usePermissions()

const request = ref<ApprovalRequest | null>(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const isReviewing = ref(false)
const error = ref<string | null>(null)
const reviewComment = ref('')
const submitNotes = ref('')

const canSubmit = computed(() => can('approvals.submit'))
const canReview = computed(() => can('approvals.review'))
const showPanel = computed(() => props.requiresApproval || request.value !== null)

const canSubmitForReview = computed(() => {
  if (!canSubmit.value) return false
  if (!request.value) return true
  return ['changes_requested', 'rejected'].includes(request.value.status)
})

const awaitingReview = computed(() => request.value?.status === 'submitted')
const isApproved = computed(() => ['approved', 'finalized'].includes(request.value?.status ?? ''))

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const { requests } = await approvalsApi.list({
      subject_type: props.subjectType,
      subject_id: props.subjectId,
      per_page: 1,
    })
    request.value = requests[0] ?? null
  } catch (err) {
    error.value = formatApiError(err, 'Approval status is unavailable.')
  } finally {
    isLoading.value = false
  }
}

async function submitForReview() {
  isSubmitting.value = true
  error.value = null
  try {
    request.value = await approvalsApi.submit({
      subject_type: props.subjectType,
      subject_id: props.subjectId,
      notes: submitNotes.value || undefined,
      requires_approval: true,
    })
    submitNotes.value = ''
    emit('updated')
  } catch (err) {
    error.value = formatApiError(err, 'We could not submit this item for review.')
  } finally {
    isSubmitting.value = false
  }
}

async function review(action: 'approve' | 'reject' | 'request_changes') {
  if (!request.value) return
  isReviewing.value = true
  error.value = null
  try {
    request.value = await approvalsApi.review(request.value.id, {
      action,
      comment: reviewComment.value || undefined,
    })
    reviewComment.value = ''
    emit('updated')
  } catch (err) {
    error.value = formatApiError(err, 'We could not record this review decision.')
  } finally {
    isReviewing.value = false
  }
}

watch(
  () => [props.subjectType, props.subjectId] as const,
  () => load(),
  { immediate: true },
)

onMounted(load)
</script>

<template>
  <section v-if="showPanel" class="bw-card space-y-4 p-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="font-semibold text-foreground">Approval workflow</h2>
        <p class="text-sm text-muted-foreground">
          Submit for partner or lawyer review before sending or sharing.
        </p>
      </div>
      <span
        v-if="request"
        class="bw-badge"
        :class="{
          'bw-badge-warning': awaitingReview,
          'bw-badge-success': isApproved,
          'bw-badge-danger': request.status === 'rejected',
        }"
      >
        {{ humanize(request.status) }}
      </span>
      <span v-else-if="requiresApproval" class="bw-badge bw-badge-warning">Approval required</span>
    </div>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    <p v-else-if="isLoading" class="text-sm text-muted-foreground">Loading approval status…</p>

    <template v-else>
      <p v-if="request?.submitter" class="text-sm text-muted-foreground">
        Submitted by {{ request.submitter.name }}
        <span v-if="request.submitted_at">
          · {{ new Date(request.submitted_at).toLocaleString() }}
        </span>
      </p>
      <p v-if="request?.reviewer && request.reviewed_at" class="text-sm text-muted-foreground">
        Reviewed by {{ request.reviewer.name }}
        · {{ new Date(request.reviewed_at).toLocaleString() }}
      </p>
      <p v-if="request?.notes" class="rounded-lg border border-border bg-surface p-3 text-sm">
        {{ request.notes }}
      </p>

      <div v-if="request?.comments?.length" class="space-y-2">
        <p class="text-sm font-medium text-foreground">Reviewer comments</p>
        <div
          v-for="(comment, index) in request.comments"
          :key="index"
          class="rounded-lg border border-border p-3 text-sm"
        >
          <p class="font-medium text-foreground">{{ comment.user_name }}</p>
          <p class="text-muted-foreground">{{ comment.body }}</p>
        </div>
      </div>

      <div v-if="canSubmitForReview" class="space-y-3 border-t border-border pt-4">
        <label class="block space-y-1 text-sm">
          <span class="text-muted-foreground">Notes for reviewer (optional)</span>
          <textarea v-model="submitNotes" rows="2" class="bw-input w-full" />
        </label>
        <button
          type="button"
          class="bw-btn bw-btn-outline bw-btn-sm"
          :disabled="isSubmitting"
          @click="submitForReview"
        >
          {{ isSubmitting ? 'Submitting…' : 'Submit for review' }}
        </button>
      </div>

      <div v-if="awaitingReview && canReview" class="space-y-3 border-t border-border pt-4">
        <label class="block space-y-1 text-sm">
          <span class="text-muted-foreground">Comment (optional)</span>
          <textarea v-model="reviewComment" rows="2" class="bw-input w-full" />
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="bw-btn bw-btn-primary bw-btn-sm"
            :disabled="isReviewing"
            @click="review('approve')"
          >
            Approve
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="isReviewing"
            @click="review('request_changes')"
          >
            Request changes
          </button>
          <button
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm text-destructive"
            :disabled="isReviewing"
            @click="review('reject')"
          >
            Reject
          </button>
        </div>
      </div>

      <p
        v-else-if="requiresApproval && !isApproved && !canSubmitForReview && !awaitingReview"
        class="text-sm text-muted-foreground"
      >
        Approval is required before this item can be sent or shared with the client.
      </p>
    </template>
  </section>
</template>
