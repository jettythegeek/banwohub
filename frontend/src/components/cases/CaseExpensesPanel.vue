<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { PhPlus } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { caseExpensesApi, type CaseExpensePayload } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { CURRENCY_SYMBOL, formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/composables/usePermissions'
import type { CaseExpense, CaseExpenseSummary } from '@/types'

const props = defineProps<{
  caseId: number
}>()

const { can } = usePermissions()
const expenses = ref<CaseExpense[]>([])
const summary = ref<CaseExpenseSummary | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showAddModal = ref(false)

const form = ref<Omit<CaseExpensePayload, 'legal_matter_id'>>({
  description: '',
  category: '',
  amount: 0,
  expense_date: new Date().toISOString().slice(0, 10),
  billable: true,
})

function resetForm() {
  form.value = {
    description: '',
    category: '',
    amount: 0,
    expense_date: new Date().toISOString().slice(0, 10),
    billable: true,
  }
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const result = await caseExpensesApi.list({ legal_matter_id: props.caseId })
    expenses.value = result.expenses
    summary.value = result.summary
  } catch (err) {
    error.value = formatApiError(err, 'Expenses are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function handleSubmit() {
  if (!can('expenses.create')) return
  isSaving.value = true
  error.value = null
  try {
    await caseExpensesApi.create({
      ...form.value,
      legal_matter_id: props.caseId,
      amount: Number(form.value.amount),
    })
    resetForm()
    showAddModal.value = false
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not save the expense.')
  } finally {
    isSaving.value = false
  }
}

async function removeExpense(id: number) {
  if (!can('expenses.delete')) return
  error.value = null
  try {
    await caseExpensesApi.remove(id)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete the expense.')
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div v-if="summary" class="grid gap-4 sm:grid-cols-3">
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total expenses</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ formatCurrency(summary.total_amount) }}</p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Billable</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ formatCurrency(summary.billable_amount) }}</p>
      </div>
      <div class="bw-card p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Count</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ summary.expense_count }}</p>
      </div>
    </div>

    <section class="bw-card overflow-hidden">
      <div class="bw-card-header">
        <h3 class="font-semibold text-foreground">Expenses</h3>
        <button
          v-if="can('expenses.create')"
          type="button"
          class="bw-btn bw-btn-accent bw-btn-sm"
          @click="showAddModal = true"
        >
          <PhPlus class="h-4 w-4" weight="bold" />
          Add expense
        </button>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-if="isLoading" variant="panel" :rows="4" />

      <div v-else-if="expenses.length" class="overflow-x-auto">
        <table class="bw-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Billable</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="expense in expenses" :key="expense.id">
              <td class="tabular-nums">{{ expense.expense_date }}</td>
              <td>
                <span v-if="expense.category" class="text-muted-foreground">{{ expense.category }} — </span>
                {{ expense.description }}
              </td>
              <td class="tabular-nums">{{ formatCurrency(expense.amount) }}</td>
              <td>{{ expense.billable ? 'Yes' : 'No' }}</td>
              <td class="text-right">
                <button
                  v-if="can('expenses.delete') && !expense.invoice_id"
                  type="button"
                  class="text-sm text-destructive hover:underline"
                  @click="removeExpense(expense.id)"
                >
                  Delete
                </button>
                <span v-else-if="expense.invoice_id" class="text-xs text-muted-foreground">Invoiced</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState v-else title="No expenses" message="Track filing fees, travel, and other case costs here." />
    </section>

    <BwModal
      v-if="can('expenses.create')"
      :open="showAddModal"
      title="Add expense"
      size="md"
      @close="showAddModal = false"
    >
      <form id="expense-form" class="space-y-4" @submit.prevent="handleSubmit">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="expense-date">Date</label>
            <input id="expense-date" v-model="form.expense_date" type="date" required class="bw-input" />
          </div>
          <div>
            <label class="bw-label" for="expense-category">Category</label>
            <input id="expense-category" v-model="form.category" class="bw-input" placeholder="Travel, filing, etc." />
          </div>
        </div>
        <div>
          <label class="bw-label" for="expense-description">Description</label>
          <input id="expense-description" v-model="form.description" required class="bw-input" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="expense-amount">Amount ({{ CURRENCY_SYMBOL }})</label>
            <input
              id="expense-amount"
              v-model.number="form.amount"
              type="number"
              min="0.01"
              step="0.01"
              required
              class="bw-input"
            />
          </div>
          <label class="flex items-end gap-2 pb-2 text-sm">
            <input v-model="form.billable" type="checkbox" class="rounded border-border" />
            Billable to client
          </label>
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showAddModal = false">
          Cancel
        </button>
        <button type="submit" form="expense-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Add expense' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
