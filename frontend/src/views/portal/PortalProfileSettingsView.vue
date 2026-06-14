<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { portalAuthApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import { usePortalAuthStore } from '@/stores/portalAuth'

const auth = usePortalAuthStore()
const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const form = ref({
  name: '',
  phone: '',
})

onMounted(async () => {
  isLoading.value = true
  try {
    await auth.refreshUser()
    form.value.name = auth.user?.name ?? ''
    form.value.phone = auth.user?.phone ?? ''
  } finally {
    isLoading.value = false
  }
})

async function saveProfile() {
  isSaving.value = true
  error.value = null
  success.value = null
  try {
    const updated = await portalAuthApi.updateProfile({
      name: form.value.name.trim(),
      phone: form.value.phone.trim() || null,
    })
    auth.user = updated
    success.value = 'Profile updated.'
  } catch (err) {
    error.value = formatApiError(err, 'We could not update your profile.')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Profile settings" subtitle="Update your name and phone number." />

    <Skeleton v-if="isLoading" variant="detail" />

    <section v-else class="bw-card max-w-xl p-6 space-y-4">
      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <p v-if="success" class="text-sm text-primary-700" role="status">{{ success }}</p>

      <label class="block space-y-1 text-sm">
        <span class="text-muted-foreground">Email</span>
        <input :value="auth.user?.email ?? ''" disabled class="bw-input w-full opacity-70" />
      </label>

      <label class="block space-y-1 text-sm">
        <span class="text-muted-foreground">Full name</span>
        <input v-model="form.name" required class="bw-input w-full" />
      </label>

      <label class="block space-y-1 text-sm">
        <span class="text-muted-foreground">Phone</span>
        <input v-model="form.phone" class="bw-input w-full" />
      </label>

      <button
        type="button"
        class="bw-btn bw-btn-primary"
        :disabled="isSaving || !form.name.trim()"
        @click="saveProfile"
      >
        {{ isSaving ? 'Saving…' : 'Save changes' }}
      </button>
    </section>
  </div>
</template>
