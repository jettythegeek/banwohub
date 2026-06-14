<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhCalendarCheck, PhCurrencyDollar, PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { usePermissions } from '@/composables/usePermissions'
import { legalProjectsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import { humanize } from '@/lib/status'
import type { LegalProjectBudget, LegalProjectMilestone } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()

const milestones = ref<LegalProjectMilestone[]>([])
const budgets = ref<LegalProjectBudget[]>([])
const budgetTotals = ref({ budgeted: 0, actual: 0 })
const milestoneTypes = ref<string[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showMilestoneModal = ref(false)
const showBudgetModal = ref(false)

const milestoneForm = ref({
  title: '',
  milestone_type: 'custom',
  due_at: '',
  status: 'pending',
})
const budgetForm = ref({
  category: 'fees',
  description: '',
  budgeted_amount: '',
  actual_amount: '',
})

const canView = computed(() => can('projects.view'))
const canCreate = computed(() => can('projects.create'))
const canUpdate = computed(() => can('projects.update'))

async function load() {
  if (!canView.value) return
  isLoading.value = true
  error.value = null
  try {
    const [milestoneRes, budgetRes] = await Promise.all([
      legalProjectsApi.listMilestones({ legal_matter_id: props.caseId }),
      legalProjectsApi.listBudgets({ legal_matter_id: props.caseId }),
    ])
    milestones.value = milestoneRes.milestones
    milestoneTypes.value = milestoneRes.milestoneTypes
    budgets.value = budgetRes.budgets
    budgetTotals.value = budgetRes.totals
  } catch (err) {
    error.value = formatApiError(err, 'Project data is not available yet.')
  } finally {
    isLoading.value = false
  }
}

function resetMilestoneForm() {
  milestoneForm.value = { title: '', milestone_type: 'custom', due_at: '', status: 'pending' }
}

function resetBudgetForm() {
  budgetForm.value = { category: 'fees', description: '', budgeted_amount: '', actual_amount: '' }
}

function openMilestoneModal() {
  resetMilestoneForm()
  showMilestoneModal.value = true
}

function closeMilestoneModal() {
  showMilestoneModal.value = false
  resetMilestoneForm()
}

function openBudgetModal() {
  resetBudgetForm()
  showBudgetModal.value = true
}

function closeBudgetModal() {
  showBudgetModal.value = false
  resetBudgetForm()
}

async function createMilestone() {
  if (!canCreate.value || !milestoneForm.value.title.trim()) return
  isSaving.value = true
  error.value = null
  try {
    await legalProjectsApi.createMilestone({
      legal_matter_id: props.caseId,
      title: milestoneForm.value.title.trim(),
      milestone_type: milestoneForm.value.milestone_type,
      due_at: milestoneForm.value.due_at || undefined,
      status: milestoneForm.value.status,
    })
    closeMilestoneModal()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'Could not create milestone.')
  } finally {
    isSaving.value = false
  }
}

async function completeMilestone(milestone: LegalProjectMilestone) {
  if (!canUpdate.value) return
  isSaving.value = true
  try {
    await legalProjectsApi.updateMilestone(milestone.id, { status: 'completed' })
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'Could not update milestone.')
  } finally {
    isSaving.value = false
  }
}

async function createBudgetLine() {
  if (!canCreate.value || !budgetForm.value.description.trim()) return
  isSaving.value = true
  error.value = null
  try {
    await legalProjectsApi.createBudget({
      legal_matter_id: props.caseId,
      category: budgetForm.value.category,
      description: budgetForm.value.description.trim(),
      budgeted_amount: budgetForm.value.budgeted_amount
        ? Number(budgetForm.value.budgeted_amount)
        : 0,
      actual_amount: budgetForm.value.actual_amount ? Number(budgetForm.value.actual_amount) : 0,
    })
    closeBudgetModal()
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'Could not create budget line.')
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6">
    <p v-if="!canView" class="text-sm text-muted-foreground">You do not have permission to view project management.</p>
    <p v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
      {{ error }}
    </p>

    <div v-if="isLoading && canView" class="space-y-4">
      <Skeleton class="h-40 rounded-xl" />
      <Skeleton class="h-40 rounded-xl" />
    </div>

    <template v-else-if="canView">
      <div class="grid gap-6 lg:grid-cols-2">
        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div class="flex items-center gap-2">
              <PhCalendarCheck class="h-5 w-5 text-primary-700" />
              <h2 class="font-semibold text-foreground">Milestones</h2>
            </div>
            <button
              v-if="canCreate"
              type="button"
              class="bw-btn bw-btn-accent bw-btn-sm"
              @click="openMilestoneModal"
            >
              <PhPlus class="h-4 w-4" weight="bold" />
              Add
            </button>
          </div>

          <div v-if="milestones.length" class="divide-y divide-border">
            <div
              v-for="milestone in milestones"
              :key="milestone.id"
              class="flex items-center justify-between gap-3 px-6 py-4"
            >
              <div>
                <p class="font-medium text-foreground">{{ milestone.title }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ humanize(milestone.milestone_type) }}
                  <span v-if="milestone.due_at"> · due {{ milestone.due_at }}</span>
                </p>
              </div>
              <div class="flex items-center gap-2">
                <StatusBadge :status="milestone.status" />
                <button
                  v-if="canUpdate && milestone.status !== 'completed'"
                  type="button"
                  class="bw-btn bw-btn-outline bw-btn-sm"
                  @click="completeMilestone(milestone)"
                >
                  Complete
                </button>
              </div>
            </div>
          </div>
          <EmptyState v-else class="py-10" title="No milestones" description="Add milestones to track matter progress." />
        </section>

        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div class="flex items-center gap-2">
              <PhCurrencyDollar class="h-5 w-5 text-primary-700" />
              <h2 class="font-semibold text-foreground">Budget</h2>
            </div>
            <button
              v-if="canCreate"
              type="button"
              class="bw-btn bw-btn-accent bw-btn-sm"
              @click="openBudgetModal"
            >
              <PhPlus class="h-4 w-4" weight="bold" />
              Add line
            </button>
          </div>

          <div class="grid grid-cols-2 gap-4 border-b border-border px-6 py-4 text-sm">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Budgeted</p>
              <p class="mt-1 font-semibold tabular-nums">{{ formatCurrency(budgetTotals.budgeted) }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Actual</p>
              <p class="mt-1 font-semibold tabular-nums">{{ formatCurrency(budgetTotals.actual) }}</p>
            </div>
          </div>

          <div v-if="budgets.length" class="divide-y divide-border">
            <div v-for="line in budgets" :key="line.id" class="px-6 py-4 text-sm">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-medium text-foreground">{{ line.description }}</p>
                  <p class="mt-1 text-muted-foreground">{{ humanize(line.category) }}</p>
                </div>
                <div class="text-right tabular-nums">
                  <p>{{ formatCurrency(line.budgeted_amount) }} budgeted</p>
                  <p class="text-muted-foreground">{{ formatCurrency(line.actual_amount) }} actual</p>
                </div>
              </div>
            </div>
          </div>
          <EmptyState v-else class="py-10" title="No budget lines" description="Track fees and expenses against budget." />
        </section>
      </div>
    </template>

    <BwModal :open="showMilestoneModal" title="New milestone" size="md" @close="closeMilestoneModal">
      <form id="milestone-form" class="space-y-4" @submit.prevent="createMilestone">
        <div>
          <label class="bw-label" for="milestone-title">Title</label>
          <input id="milestone-title" v-model="milestoneForm.title" class="bw-input" required />
        </div>
        <div>
          <label class="bw-label" for="milestone-type">Type</label>
          <select id="milestone-type" v-model="milestoneForm.milestone_type" class="bw-select">
            <option v-for="type in milestoneTypes" :key="type" :value="type">
              {{ humanize(type) }}
            </option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="milestone-due">Due date</label>
          <input id="milestone-due" v-model="milestoneForm.due_at" type="date" class="bw-input" />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeMilestoneModal">Cancel</button>
        <button type="submit" form="milestone-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save milestone' }}
        </button>
      </template>
    </BwModal>

    <BwModal :open="showBudgetModal" title="New budget line" size="md" @close="closeBudgetModal">
      <form id="budget-form" class="space-y-4" @submit.prevent="createBudgetLine">
        <div>
          <label class="bw-label" for="budget-description">Description</label>
          <input id="budget-description" v-model="budgetForm.description" class="bw-input" required />
        </div>
        <div>
          <label class="bw-label" for="budget-category">Category</label>
          <select id="budget-category" v-model="budgetForm.category" class="bw-select">
            <option value="fees">Fees</option>
            <option value="expenses">Expenses</option>
            <option value="disbursements">Disbursements</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="bw-label" for="budget-budgeted">Budgeted</label>
            <input id="budget-budgeted" v-model="budgetForm.budgeted_amount" type="number" min="0" step="0.01" class="bw-input" />
          </div>
          <div>
            <label class="bw-label" for="budget-actual">Actual</label>
            <input id="budget-actual" v-model="budgetForm.actual_amount" type="number" min="0" step="0.01" class="bw-input" />
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="closeBudgetModal">Cancel</button>
        <button type="submit" form="budget-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save budget line' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
