<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import { PhEnvelope } from '@phosphor-icons/vue'
import AuthShell from '@/components/auth/AuthShell.vue'
import InputWithIcon from '@/components/auth/InputWithIcon.vue'
import { forgotPassword } from '@/lib/api'

const email = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)
const success = ref(false)
const debugLink = ref<string | null>(null)

async function handleSubmit() {
  error.value = null
  debugLink.value = null
  submitting.value = true
  try {
    const data = await forgotPassword(email.value)
    success.value = true
    if (data.reset_link) debugLink.value = data.reset_link
  } catch (err) {
    if (axios.isAxiosError(err)) {
      if (err.response?.status === 429) {
        error.value = 'Too many attempts. Please wait a minute and try again.'
      } else if (err.response?.status === 422) {
        error.value = 'Please enter a valid email address.'
      } else {
        error.value =
          (err.response?.data as { message?: string })?.message ??
          'Could not send reset instructions.'
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
    title="Forgot your password?"
    subtitle="Enter your email and we'll send you a reset link if we find your account."
  >
    <div v-if="success" class="space-y-4" role="status">
      <p class="text-sm text-foreground">
        If that email is on file, we sent reset instructions. Check your inbox.
        For local dev, see
        <code class="text-xs">storage/logs/laravel.log</code>.
      </p>
      <p
        v-if="debugLink"
        class="break-all rounded-md border border-border bg-surface p-3 text-xs text-muted-foreground"
      >
        <span class="font-medium text-foreground">Debug reset link:</span>
        <a :href="debugLink" class="text-primary hover:underline">{{ debugLink }}</a>
      </p>
    </div>
    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <div class="space-y-2">
        <label for="email" class="text-sm font-medium text-foreground"
          >Email</label
        >
        <InputWithIcon
          id="email"
          v-model="email"
          :icon="PhEnvelope"
          type="email"
          placeholder="you@email.com"
          required
          autocomplete="email"
        />
      </div>
      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <button
        type="submit"
        class="bw-btn bw-btn-action w-full"
        :disabled="submitting"
      >
        {{ submitting ? 'Sending…' : 'Send reset link' }}
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
