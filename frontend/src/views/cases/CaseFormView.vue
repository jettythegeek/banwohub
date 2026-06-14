<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { api } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { CURRENCY_SYMBOL } from '@/lib/currency'
import { CASE_STAGES, MATTER_STAGES, PRIORITIES, humanizeEnum } from '@/lib/enums'
import type { Client, LegalMatter, Organization, PaginatedResponse, User } from '@/types'

const route = useRoute()
const router = useRouter()
const caseId = computed(() =>
  route.params.id && route.name === 'case-edit' ? Number(route.params.id) : null,
)
const isEdit = computed(() => caseId.value !== null)

const clients = ref<Client[]>([])
const lawyers = ref<User[]>([])
const practiceAreas = ref<string[]>([])
const form = ref({
  title: '',
  client_id: '' as string | number,
  practice_area: '',
  tags_text: '',
  stage: 'lead',
  matter_stage: 'intake',
  status: 'new',
  priority: 'normal',
  opened_at: '',
  expected_close_at: '',
  lead_lawyer_id: '' as string | number,
  opposing_party: '',
  description: '',
  billing_type: 'hourly' as 'hourly' | 'fixed' | 'retainer',
  billing_rate: '' as string | number,
  fixed_fee_amount: '' as string | number,
  retainer_minimum_amount: '' as string | number,
})

function parseTags(text: string): string[] {
  return text
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean)
}
const isLoading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  isLoading.value = true
  try {
    const [clientsRes, usersRes, orgRes] = await Promise.all([
      api.get<PaginatedResponse<Client>>('/clients', { params: { per_page: 100 } }),
      api
        .get<PaginatedResponse<User>>('/users', {
          params: { per_page: 100, active: true },
        })
        .catch(() => null),
      api.get<Organization>('/organization').catch(() => null),
    ])
    clients.value = clientsRes.data.data
    if (orgRes) {
      practiceAreas.value = orgRes.data.practice_areas ?? []
    }
    if (usersRes) {
      lawyers.value = usersRes.data.data.filter((u) =>
        u.roles?.some((r) => ['Lawyer', 'Partner', 'Firm Admin'].includes(r)),
      )
    }

    const prefillClient = route.query.client_id
    if (prefillClient) form.value.client_id = Number(prefillClient)

    if (isEdit.value) {
      const { data } = await api.get<LegalMatter>(`/cases/${caseId.value}`)
      const opposing = data.parties?.find((p) => p.party_type === 'opposing')
      form.value = {
        title: data.title,
        client_id: (data.client as Client)?.id ?? '',
        practice_area: data.practice_area ?? '',
        tags_text: (data.tags ?? []).join(', '),
        stage: data.stage ?? 'lead',
        matter_stage: data.matter_stage ?? 'intake',
        status: data.status,
        priority: data.priority,
        opened_at: data.opened_at ?? '',
        expected_close_at: data.expected_close_at ?? '',
        lead_lawyer_id: data.lead_lawyer?.id ?? '',
        opposing_party: opposing?.name ?? '',
        description: data.description ?? '',
        billing_type: data.billing_type ?? 'hourly',
        billing_rate: data.billing_rate ?? '',
        fixed_fee_amount: data.fixed_fee_amount ?? '',
        retainer_minimum_amount: data.retainer_minimum_amount ?? '',
      }
    }
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    isLoading.value = false
  }
})

async function handleSubmit() {
  submitting.value = true
  error.value = null
  const payload = {
    title: form.value.title,
    client_id: Number(form.value.client_id),
    practice_area: form.value.practice_area.trim() || null,
    tags: parseTags(form.value.tags_text),
    stage: form.value.stage,
    matter_stage: form.value.matter_stage,
    status: form.value.status,
    priority: form.value.priority,
    lead_lawyer_id: form.value.lead_lawyer_id
      ? Number(form.value.lead_lawyer_id)
      : null,
    opposing_party: form.value.opposing_party,
    description: form.value.description,
    opened_at: form.value.opened_at || null,
    expected_close_at: form.value.expected_close_at || null,
    billing_type: form.value.billing_type,
    billing_rate: form.value.billing_rate ? Number(form.value.billing_rate) : null,
    fixed_fee_amount: form.value.fixed_fee_amount ? Number(form.value.fixed_fee_amount) : null,
    retainer_minimum_amount: form.value.retainer_minimum_amount
      ? Number(form.value.retainer_minimum_amount)
      : null,
  }
  try {
    if (isEdit.value) {
      await api.patch(`/cases/${caseId.value}`, payload)
      await router.push(`/cases/${caseId.value}`)
    } else {
      const { data } = await api.post<LegalMatter>('/cases', payload)
      await router.push(`/cases/${data.id}`)
    }
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <PageHeader
      :title="isEdit ? 'Edit case' : 'New case'"
      subtitle="Link a client and set how the matter is tracked."
    />

    <Skeleton v-if="isLoading" variant="form" :rows="6" />

    <form v-else class="bw-card space-y-5 p-6" @submit.prevent="handleSubmit">
      <div>
        <label class="bw-label" for="case-title">Title</label>
        <input id="case-title" v-model="form.title" required class="bw-input" />
      </div>
      <div>
        <label class="bw-label" for="case-client">Client</label>
        <select id="case-client" v-model="form.client_id" required class="bw-select">
          <option disabled value="">Select client</option>
          <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <label class="bw-label" for="case-stage">Pipeline stage</label>
          <select id="case-stage" v-model="form.stage" class="bw-select">
            <option v-for="s in CASE_STAGES" :key="s" :value="s">
              {{ humanizeEnum(s) }}
            </option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="case-matter-stage">Matter stage</label>
          <select id="case-matter-stage" v-model="form.matter_stage" class="bw-select">
            <option v-for="s in MATTER_STAGES" :key="s" :value="s">
              {{ humanizeEnum(s) }}
            </option>
          </select>
        </div>
      </div>
      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <label class="bw-label" for="case-status">Status</label>
          <select id="case-status" v-model="form.status" class="bw-select">
            <option value="new">New</option>
            <option value="active">Active</option>
            <option value="in_court">In court</option>
            <option value="awaiting_client_response">Awaiting client</option>
            <option value="closed">Closed</option>
            <option value="archived">Archived</option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="case-priority">Priority</label>
          <select id="case-priority" v-model="form.priority" class="bw-select">
            <option v-for="p in PRIORITIES" :key="p" :value="p">
              {{ humanizeEnum(p) }}
            </option>
          </select>
        </div>
      </div>
      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <label class="bw-label" for="case-opened">Opened</label>
          <input id="case-opened" v-model="form.opened_at" type="date" class="bw-input" />
        </div>
        <div>
          <label class="bw-label" for="case-close">Expected close</label>
          <input
            id="case-close"
            v-model="form.expected_close_at"
            type="date"
            class="bw-input"
          />
        </div>
      </div>
      <div v-if="lawyers.length">
        <label class="bw-label" for="case-lawyer">Lead lawyer</label>
        <select id="case-lawyer" v-model="form.lead_lawyer_id" class="bw-select">
          <option value="">Unassigned</option>
          <option v-for="u in lawyers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <label class="bw-label" for="case-practice-area">Practice area</label>
          <select id="case-practice-area" v-model="form.practice_area" class="bw-select">
            <option value="">Select practice area</option>
            <option v-for="area in practiceAreas" :key="area" :value="area">
              {{ area }}
            </option>
          </select>
        </div>
        <div>
          <label class="bw-label" for="case-tags">Tags</label>
          <input
            id="case-tags"
            v-model="form.tags_text"
            class="bw-input"
            placeholder="Comma-separated tags"
          />
        </div>
      </div>
      <div>
        <label class="bw-label" for="case-opposing">Opposing party</label>
        <input id="case-opposing" v-model="form.opposing_party" class="bw-input" />
      </div>
      <div>
        <label class="bw-label" for="case-description">Description</label>
        <textarea id="case-description" v-model="form.description" rows="3" class="bw-textarea" />
      </div>

      <div class="space-y-4 border-t border-border pt-5">
        <h3 class="font-medium">Billing</h3>
        <div>
          <label class="bw-label" for="case-billing-type">Billing type</label>
          <select id="case-billing-type" v-model="form.billing_type" class="bw-select">
            <option value="hourly">Hourly</option>
            <option value="fixed">Fixed fee</option>
            <option value="retainer">Retainer (minimum)</option>
          </select>
        </div>
        <div v-if="form.billing_type === 'hourly'">
          <label class="bw-label" for="case-billing-rate">Hourly rate ({{ CURRENCY_SYMBOL }})</label>
          <input id="case-billing-rate" v-model="form.billing_rate" type="number" min="0" step="0.01" class="bw-input" />
        </div>
        <div v-else-if="form.billing_type === 'fixed'">
          <label class="bw-label" for="case-fixed-fee">Fixed fee amount ({{ CURRENCY_SYMBOL }})</label>
          <input id="case-fixed-fee" v-model="form.fixed_fee_amount" type="number" min="0" step="0.01" class="bw-input" />
        </div>
        <div v-else>
          <label class="bw-label" for="case-retainer-min">Retainer minimum ({{ CURRENCY_SYMBOL }})</label>
          <input id="case-retainer-min" v-model="form.retainer_minimum_amount" type="number" min="0" step="0.01" class="bw-input" />
          <p class="mt-1.5 text-xs text-muted-foreground">
            Record deposits and disbursements on the case overview trust ledger after saving.
          </p>
        </div>
      </div>

      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

      <div class="flex gap-3 border-t border-border pt-5">
        <button type="submit" class="bw-btn bw-btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving…' : 'Save case' }}
        </button>
        <RouterLink
          :to="isEdit ? `/cases/${caseId}` : '/cases'"
          class="bw-btn bw-btn-outline"
        >
          Cancel
        </RouterLink>
      </div>
    </form>
  </div>
</template>
