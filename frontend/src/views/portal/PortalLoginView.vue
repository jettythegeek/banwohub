<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PhEnvelope, PhLock } from '@phosphor-icons/vue'
import AuthShell from '@/components/auth/AuthShell.vue'
import InputWithIcon from '@/components/auth/InputWithIcon.vue'
import { formatApiError } from '@/lib/api-error'
import { usePortalAuthStore } from '@/stores/portalAuth'

const auth = usePortalAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

async function handleSubmit() {
  error.value = null
  submitting.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push('/portal')
  } catch (err) {
    error.value = formatApiError(err, 'Invalid email or password.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthShell
    title="Client portal sign in"
    subtitle="View your cases, documents, and invoices."
  >
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="space-y-2">
        <label for="portal-email" class="text-sm font-medium text-foreground">Email</label>
        <InputWithIcon
          id="portal-email"
          v-model="email"
          :icon="PhEnvelope"
          type="email"
          placeholder="you@email.com"
          required
          autocomplete="username"
        />
      </div>
      <div class="space-y-2">
        <label for="portal-password" class="text-sm font-medium text-foreground">Password</label>
        <InputWithIcon
          id="portal-password"
          v-model="password"
          :icon="PhLock"
          type="password"
          placeholder="Password"
          required
          autocomplete="current-password"
        />
      </div>
      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
      <button
        type="submit"
        class="bw-focus-ring w-full rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-60"
        :disabled="submitting"
      >
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
    <template #footer>
      <p class="text-center text-sm text-muted-foreground">
        Firm staff?
        <RouterLink to="/login" class="text-primary hover:underline">Sign in to Banwolaw Hub</RouterLink>
      </p>
    </template>
  </AuthShell>
</template>
