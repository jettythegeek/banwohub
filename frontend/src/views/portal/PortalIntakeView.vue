<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { portalIntakeApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import type { IntakeField, IntakeForm } from '@/types'

const route = useRoute()
const router = useRouter()

const forms = ref<IntakeForm[]>([])
const selectedForm = ref<IntakeForm | null>(null)
const answers = ref<Record<string, string | boolean>>({})
const isLoading = ref(true)
const isSubmitting = ref(false)
const submitted = ref(false)
const error = ref<string | null>(null)

const formId = computed(() => {
  const raw = route.params.id
  return typeof raw === 'string' ? Number(raw) : null
})

function fieldKey(field: IntakeField): string {
  return field.name
}

async function loadForms() {
  isLoading.value = true
  error.value = null
  try {
    forms.value = await portalIntakeApi.list()
    if (formId.value) {
      selectedForm.value = await portalIntakeApi.get(formId.value)
      initAnswers(selectedForm.value)
    }
  } catch (err) {
    error.value = formatApiError(err, 'Intake forms are not available.')
  } finally {
    isLoading.value = false
  }
}

function initAnswers(form: IntakeForm) {
  const next: Record<string, string | boolean> = {}
  for (const field of form.fields ?? []) {
    next[fieldKey(field)] = field.type === 'checkbox' ? false : ''
  }
  answers.value = next
}

async function openForm(form: IntakeForm) {
  await router.push({ name: 'portal-intake-detail', params: { id: form.id } })
}

async function handleSubmit() {
  if (!selectedForm.value) return
  isSubmitting.value = true
  error.value = null
  try {
    await portalIntakeApi.submit(selectedForm.value.id, { data: answers.value })
    submitted.value = true
  } catch (err) {
    error.value = formatApiError(err, 'We could not submit the form.')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(loadForms)

watch(formId, () => {
  void loadForms()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Intake forms"
      subtitle="Complete forms requested by your legal team."
    />

    <Skeleton v-if="isLoading" variant="form" :rows="5" />

    <template v-else-if="submitted">
      <div class="bw-card space-y-3 p-6">
        <h2 class="text-lg font-semibold">Thank you</h2>
        <p class="text-muted-foreground">Your intake form was submitted. The firm will review it shortly.</p>
        <RouterLink to="/portal" class="bw-btn bw-btn-primary inline-flex">Back to dashboard</RouterLink>
      </div>
    </template>

    <template v-else-if="selectedForm">
      <div class="bw-card space-y-5 p-6">
        <div>
          <h2 class="text-lg font-semibold">{{ selectedForm.name }}</h2>
          <p v-if="selectedForm.description" class="mt-1 text-sm text-muted-foreground">
            {{ selectedForm.description }}
          </p>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div v-for="field in selectedForm.fields" :key="field.name">
            <label class="bw-label" :for="`field-${field.name}`">
              {{ field.label }}
              <span v-if="field.required" class="text-destructive">*</span>
            </label>

            <textarea
              v-if="field.type === 'long_text'"
              :id="`field-${field.name}`"
              v-model="answers[field.name]"
              :required="field.required"
              rows="4"
              class="bw-textarea"
            />
            <input
              v-else-if="field.type === 'checkbox'"
              :id="`field-${field.name}`"
              v-model="answers[field.name]"
              type="checkbox"
              class="rounded border-border"
            />
            <select
              v-else-if="field.type === 'dropdown'"
              :id="`field-${field.name}`"
              v-model="answers[field.name]"
              :required="field.required"
              class="bw-select"
            >
              <option value="">Select…</option>
              <option v-for="opt in field.options ?? []" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <input
              v-else
              :id="`field-${field.name}`"
              v-model="answers[field.name]"
              :type="field.type === 'email' ? 'email' : field.type === 'date' ? 'date' : field.type === 'phone' ? 'tel' : 'text'"
              :required="field.required"
              class="bw-input"
            />
          </div>

          <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

          <div class="flex gap-3 border-t border-border pt-4">
            <button type="submit" class="bw-btn bw-btn-primary" :disabled="isSubmitting">
              {{ isSubmitting ? 'Submitting…' : 'Submit form' }}
            </button>
            <RouterLink to="/portal/intake" class="bw-btn bw-btn-outline">Back to list</RouterLink>
          </div>
        </form>
      </div>
    </template>

    <template v-else>
      <div v-if="forms.length" class="grid gap-4 sm:grid-cols-2">
        <button
          v-for="form in forms"
          :key="form.id"
          type="button"
          class="bw-card p-5 text-left hover:border-primary"
          @click="openForm(form)"
        >
          <h3 class="font-medium">{{ form.name }}</h3>
          <p v-if="form.description" class="mt-1 text-sm text-muted-foreground line-clamp-2">
            {{ form.description }}
          </p>
        </button>
      </div>
      <EmptyState
        v-else
        title="No forms available"
        description="Your firm has not published any intake forms yet."
      />
    </template>
  </div>
</template>
