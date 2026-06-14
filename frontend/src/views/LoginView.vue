<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PhEnvelope, PhLock, PhShieldCheck } from '@phosphor-icons/vue'
import AuthShell from '@/components/auth/AuthShell.vue'
import InputWithIcon from '@/components/auth/InputWithIcon.vue'
import { formatApiError } from '@/lib/api-error'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const challengeToken = ref<string | null>(null)
const twoFactorCode = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

async function handleSubmit() {
  error.value = null
  submitting.value = true
  try {
    if (challengeToken.value) {
      await auth.verifyTwoFactor(challengeToken.value, twoFactorCode.value)
      await router.push('/dashboard')
      return
    }

    const result = await auth.login(email.value, password.value)
    if (!result.complete) {
      challengeToken.value = result.challengeToken
      twoFactorCode.value = ''
      return
    }
    await router.push('/dashboard')
  } catch (err) {
    error.value = formatApiError(err, 'Invalid email or password.')
  } finally {
    submitting.value = false
  }
}

function backToCredentials() {
  challengeToken.value = null
  twoFactorCode.value = ''
  error.value = null
}
</script>

<template>
  <AuthShell
    :title="challengeToken ? 'Two-factor verification' : 'Sign in to Banwolaw Hub'"
    :subtitle="challengeToken ? 'Enter the code from your authenticator app.' : 'We\'re glad you\'re here.'"
  >
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <template v-if="!challengeToken">
        <div class="space-y-2">
          <label for="email" class="text-sm font-medium text-foreground">Email</label>
          <InputWithIcon
            id="email"
            v-model="email"
            :icon="PhEnvelope"
            type="email"
            placeholder="you@email.com"
            required
            autocomplete="username"
          />
        </div>
        <div class="space-y-2">
          <label for="password" class="text-sm font-medium text-foreground">Password</label>
          <InputWithIcon
            id="password"
            v-model="password"
            :icon="PhLock"
            type="password"
            placeholder="Password"
            required
            autocomplete="current-password"
          />
        </div>
      </template>

      <template v-else>
        <div class="space-y-2">
          <label for="two-factor-code" class="text-sm font-medium text-foreground">
            Authenticator code
          </label>
          <InputWithIcon
            id="two-factor-code"
            v-model="twoFactorCode"
            :icon="PhShieldCheck"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            placeholder="000000"
            required
            autocomplete="one-time-code"
          />
        </div>
        <button
          type="button"
          class="text-sm text-primary hover:underline"
          @click="backToCredentials"
        >
          Back to sign in
        </button>
      </template>

      <p v-if="error" class="text-sm text-destructive" role="alert">
        {{ error }}
      </p>
      <button
        type="submit"
        class="bw-btn bw-btn-action w-full"
        :disabled="submitting || (challengeToken !== null && twoFactorCode.length !== 6)"
      >
        {{
          submitting
            ? challengeToken
              ? 'Verifying…'
              : 'Signing in…'
            : challengeToken
              ? 'Verify and continue'
              : 'Sign in'
        }}
      </button>
    </form>
    <template #footer>
      <div v-if="!challengeToken" class="space-y-3 text-center text-sm text-muted-foreground">
        <p>Sign in with the email we have on file for you.</p>
        <p>
          <RouterLink to="/forgot-password" class="text-primary hover:underline">
            Forgot password?
          </RouterLink>
        </p>
      </div>
    </template>
  </AuthShell>
</template>
