<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { PhPlus, PhVault } from '@phosphor-icons/vue'
import BwModal from '@/components/common/BwModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { trustLedgerApi, type TrustLedgerEntryPayload } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { CURRENCY_SYMBOL, formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/composables/usePermissions'
import type { TrustLedgerEntry, TrustLedgerSummary } from '@/types'

const props = defineProps<{
  caseId: number
  billingType?: string | null
}>()

const emit = defineEmits<{
  updated: []
}>()

const { can } = usePermissions()
const entries = ref<TrustLedgerEntry[]>([])
const summary = ref<TrustLedgerSummary | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showAddModal = ref(false)

const isRetainerMatter = computed(() => props.billingType === 'retainer')

const form = ref<Omit<TrustLedgerEntryPayload, 'legal_matter_id'>>({
  entry_type: 'deposit',
  amount: 0,
  description: '',
  occurred_at: new Date().toISOString().slice(0, 10),
})

const amountMin = computed(() => (form.value.entry_type === 'adjustment' ? undefined : 0.01))
const amountStep = computed(() => (form.value.entry_type === 'adjustment' ? 'any' : '0.01'))

function resetForm() {
  form.value = {
    entry_type: 'deposit',
    amount: 0,
    description: '',
    occurred_at: new Date().toISOString().slice(0, 10),
  }
}

function signedAmount(entry: TrustLedgerEntry): number {
  if (entry.entry_type === 'disbursement') return -entry.amount
  return entry.amount
}

function entryTypeLabel(type: string): string {
  return type.replace(/_/g, ' ')
}

async function load() {
  if (!isRetainerMatter.value) {
    isLoading.value = false
    return
  }

  isLoading.value = true
  error.value = null
  try {
    const result = await trustLedgerApi.list({ legal_matter_id: props.caseId })
    entries.value = result.entries
    summary.value = result.summary
  } catch (err) {
    error.value = formatApiError(err, 'Trust ledger is not available.')
  } finally {
    isLoading.value = false
  }
}

async function handleSubmit() {
  if (!can('trust.create')) return
  isSaving.value = true
  error.value = null
  try {
    await trustLedgerApi.create({
      ...form.value,
      legal_matter_id: props.caseId,
      amount: Number(form.value.amount),
    })
    resetForm()
    showAddModal.value = false
    await load()
    emit('updated')
  } catch (err) {
    error.value = formatApiError(err, 'We could not save the trust transaction.')
  } finally {
    isSaving.value = false
  }
}

async function removeEntry(id: number) {
  if (!can('trust.delete')) return
  error.value = null
  try {
    await trustLedgerApi.remove(id)
    await load()
    emit('updated')
  } catch (err) {
    error.value = formatApiError(err, 'We could not delete the trust transaction.')
  }
}

onMounted(load)
</script>

<template>
  <div v-if="isRetainerMatter" class="bw-card p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <PhVault class="h-5 w-5 text-accent-700" weight="fill" />
        <div>
          <h2 class="font-semibold text-foreground">Trust ledger</h2>
          <p class="text-sm text-muted-foreground">Client trust account activity for this matter.</p>
        </div>
      </div>
      <button
        v-if="can('trust.create')"
        type="button"
        class="bw-btn bw-btn-accent bw-btn-sm"
        @click="showAddModal = true"
      >
        <PhPlus class="h-4 w-4" weight="bold" />
        Record transaction
      </button>
    </div>

    <div v-if="summary" class="mb-4 grid gap-4 sm:grid-cols-3">
      <div class="rounded-lg border border-border bg-surface px-4 py-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Current balance</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ formatCurrency(summary.balance) }}
        </p>
      </div>
      <div class="rounded-lg border border-border bg-surface px-4 py-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Retainer minimum</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">
          {{ summary.retainer_minimum != null ? formatCurrency(summary.retainer_minimum) : '—' }}
        </p>
      </div>
      <div class="rounded-lg border border-border bg-surface px-4 py-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Transactions</p>
        <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ summary.entry_count }}</p>
      </div>
    </div>

    <p v-if="error" class="mb-4 text-sm text-destructive" role="alert">{{ error }}</p>

    <Skeleton v-if="isLoading" variant="panel" :rows="4" />

    <div v-else-if="entries.length" class="overflow-x-auto">
      <table class="bw-table w-full text-sm">
        <thead>
          <tr>
            <th class="text-left">Date</th>
            <th class="text-left">Type</th>
            <th class="text-left">Description</th>
            <th class="text-right">Amount</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in entries" :key="entry.id">
            <td>{{ entry.occurred_at ? new Date(entry.occurred_at).toLocaleDateString() : '—' }}</td>
            <td class="capitalize">{{ entryTypeLabel(entry.entry_type) }}</td>
            <td>{{ entry.description || '—' }}</td>
            <td
              class="text-right tabular-nums"
              :class="signedAmount(entry) < 0 ? 'text-destructive' : 'text-foreground'"
            >
              {{ formatCurrency(signedAmount(entry)) }}
            </td>
            <td class="text-right">
              <button
                v-if="can('trust.delete')"
                type="button"
                class="text-sm text-destructive hover:underline"
                @click="removeEntry(entry.id)"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EmptyState
      v-else
      title="No trust transactions"
      message="Record client deposits, disbursements, and balance adjustments to maintain the trust ledger."
    >
      <button
        v-if="can('trust.create')"
        type="button"
        class="bw-btn bw-btn-accent bw-btn-sm"
        @click="showAddModal = true"
      >
        <PhPlus class="h-4 w-4" weight="bold" />
        Record first transaction
      </button>
    </EmptyState>

    <BwModal
      v-if="can('trust.create')"
      :open="showAddModal"
      title="Record trust transaction"
      size="md"
      @close="showAddModal = false"
    >
      <form id="trust-ledger-form" class="space-y-4" @submit.prevent="handleSubmit">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="bw-label" for="trust-date">Date</label>
            <input id="trust-date" v-model="form.occurred_at" type="date" required class="bw-input" />
          </div>
          <div>
            <label class="bw-label" for="trust-type">Type</label>
            <select id="trust-type" v-model="form.entry_type" class="bw-input" required>
              <option value="deposit">Deposit</option>
              <option value="disbursement">Disbursement</option>
              <option value="adjustment">Adjustment</option>
            </select>
          </div>
        </div>
        <div>
          <label class="bw-label" for="trust-description">Description</label>
          <input
            id="trust-description"
            v-model="form.description"
            class="bw-input"
            placeholder="Retainer received, filing fee paid, etc."
          />
        </div>
        <div>
          <label class="bw-label" for="trust-amount">
            Amount ({{ CURRENCY_SYMBOL }})
            <span v-if="form.entry_type === 'adjustment'" class="font-normal text-muted-foreground">
              — use negative values to reduce balance
            </span>
          </label>
          <input
            id="trust-amount"
            v-model.number="form.amount"
            type="number"
            :min="amountMin"
            :step="amountStep"
            required
            class="bw-input"
          />
        </div>
      </form>
      <template #footer>
        <button type="button" class="bw-btn bw-btn-outline" @click="showAddModal = false">
          Cancel
        </button>
        <button type="submit" form="trust-ledger-form" class="bw-btn bw-btn-action" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save transaction' }}
        </button>
      </template>
    </BwModal>
  </div>
</template>
