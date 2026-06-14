<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { PhLock } from '@phosphor-icons/vue'
import AuthShell from '@/components/auth/AuthShell.vue'
import InputWithIcon from '@/components/auth/InputWithIcon.vue'
import { resetPassword } from '@/lib/api'

const route = useRoute()
const router = useRouter()

const token = ref('')
const emailParam = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)
const success = ref(false)

const missingParams = ref(true)

onMounted(() => {
  token.value = (route.query.token as string) ?? ''
  emailParam.value = (route.query.email as string) ?? ''
  missingParams.value = !token.value || !emailParam.value
})

async function handleSubmit() {
  error.value = null
  submitting.value = true
  try {
    await resetPassword({
      token: token.value,
      email: emailParam.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    success.value = true
    setTimeout(() => router.push('/login'), 2500)
  } catch (err) {
    if (axios.isAxiosError(err)) {
      if (err.response?.status === 422) {
        const messages = err.response.data?.errors as
          | Record<string, string[]>
          | undefined
        const first = messages && Object.values(messages).flat()[0]
        error.value =
          first ??
          'This reset link is invalid or has expired. Request a new one.'
      } else {
        error.value =
          (err.response?.data as { message?: string })?.message ??
          'Could not reset your password.'
      }
    } else {
      error.value = 'Could not reach the API. Check that the backend is running.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthShell
    title="Set a new password"
    subtitle="Enter your new password twice to confirm it."
  >
    <div v-if="missingParams" class="space-y-4">
      <p class="text-sm text-destructive" role="alert">
        This link is missing details or has expired.
      </p>
      <RouterLink to="/forgot-password" class="bw-btn bw-btn-action w-full">
        Get a new reset link
      </RouterLink>
    </div>
    <p v-else-if="success" class="text-sm text-foreground" role="status">
      Password updated. Taking you back to sign in…
    </p>
    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <div class="space-y-2">
        <label for="password" class="text-sm font-medium text-foreground"
          >New password</label
        >
        <InputWithIcon
          id="password"
          v-model="password"
          :icon="PhLock"
          type="password"
          placeholder="New password"
          required
          autocomplete="new-password"
        />
      </div>
      <div class="space-y-2">
        <label for="password_confirmation" class="text-sm font-medium text-foreground"
          >Confirm password</label
        >
        <InputWithIcon
          id="password_confirmation"
          v-model="passwordConfirmation"
          :icon="PhLock"
          type="password"
          placeholder="Confirm password"
          required
          autocomplete="new-password"
        />
      </div>
      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <button type="submit" class="bw-btn bw-btn-action w-full" :disabled="submitting">
        {{ submitting ? 'Saving…' : 'Set new password' }}
      </button>
    </form>
    <template #footer>
      <p class="text-center text-sm text-muted-foreground">
        <RouterLink to="/login" class="text-primary hover:underline">
          Back to sign in
        </RouterLink>
      </p>
    </template>
  </AuthShell>
</template>
