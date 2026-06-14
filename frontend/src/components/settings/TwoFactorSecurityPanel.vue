<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { twoFactorApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const enabled = ref(false)
const loading = ref(true)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const setupSecret = ref<string | null>(null)
const otpauthUrl = ref<string | null>(null)
const qrDataUrl = ref<string | null>(null)
const confirmCode = ref('')
const disablePassword = ref('')
const disableCode = ref('')
const enabling = ref(false)
const confirming = ref(false)
const disabling = ref(false)

async function loadStatus() {
  loading.value = true
  error.value = null
  try {
    const status = await twoFactorApi.status()
    enabled.value = status.enabled
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    loading.value = false
  }
}

async function renderQr(url: string) {
  try {
    const { default: QRCode } = await import('qrcode')
    qrDataUrl.value = await QRCode.toDataURL(url, { width: 200, margin: 1 })
  } catch {
    qrDataUrl.value = null
  }
}

watch(otpauthUrl, (url) => {
  if (url) {
    void renderQr(url)
  } else {
    qrDataUrl.value = null
  }
})

async function startEnable() {
  enabling.value = true
  error.value = null
  success.value = null
  confirmCode.value = ''
  try {
    const data = await twoFactorApi.enable()
    setupSecret.value = data.secret
    otpauthUrl.value = data.otpauth_url
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    enabling.value = false
  }
}

function cancelSetup() {
  setupSecret.value = null
  otpauthUrl.value = null
  confirmCode.value = ''
}

async function confirmEnable() {
  confirming.value = true
  error.value = null
  success.value = null
  try {
    const data = await twoFactorApi.confirm(confirmCode.value)
    enabled.value = true
    setupSecret.value = null
    otpauthUrl.value = null
    confirmCode.value = ''
    if (data.user) {
      auth.user = data.user
    }
    success.value = 'Two-factor authentication is enabled.'
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    confirming.value = false
  }
}

async function disableTwoFactor() {
  disabling.value = true
  error.value = null
  success.value = null
  try {
    const data = await twoFactorApi.disable(disablePassword.value, disableCode.value)
    enabled.value = false
    disablePassword.value = ''
    disableCode.value = ''
    if (data.user) {
      auth.user = data.user
    }
    success.value = 'Two-factor authentication has been disabled.'
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    disabling.value = false
  }
}

onMounted(() => {
  void loadStatus()
})
</script>

<template>
  <div class="bw-card max-w-xl space-y-5 p-6">
    <div>
      <h2 class="font-semibold text-foreground">Two-factor authentication</h2>
      <p class="mt-2 text-sm text-muted-foreground">
        Add an extra layer of security with a time-based code from your authenticator app.
      </p>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Loading security settings…</p>

    <template v-else>
      <div
        v-if="enabled"
        class="rounded-md border border-success/30 bg-success/5 px-4 py-3 text-sm text-foreground"
      >
        Two-factor authentication is <strong>enabled</strong> on your account.
      </div>

      <div
        v-else-if="!setupSecret"
        class="rounded-md border border-border bg-surface px-4 py-3 text-sm text-muted-foreground"
      >
        Two-factor authentication is not enabled.
      </div>

      <div v-if="setupSecret" class="space-y-4 rounded-md border border-border p-4">
        <p class="text-sm text-muted-foreground">
          Scan this QR code with Google Authenticator, Authy, or another TOTP app.
        </p>
        <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
          <img
            v-if="qrDataUrl"
            :src="qrDataUrl"
            alt="Authenticator QR code"
            class="h-[200px] w-[200px] rounded-md border border-border bg-white"
          />
          <div class="min-w-0 text-sm">
            <p class="font-medium text-foreground">Manual entry key</p>
            <p class="mt-1 break-all font-mono text-xs text-muted-foreground">{{ setupSecret }}</p>
          </div>
        </div>
        <div>
          <label class="bw-label" for="confirm-code">Verification code</label>
          <input
            id="confirm-code"
            v-model="confirmCode"
            class="bw-input max-w-xs font-mono tracking-widest"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            placeholder="000000"
            autocomplete="one-time-code"
          />
        </div>
        <div class="flex flex-wrap gap-3">
          <button
            type="button"
            class="bw-btn bw-btn-primary"
            :disabled="confirming || confirmCode.length !== 6"
            @click="confirmEnable"
          >
            {{ confirming ? 'Confirming…' : 'Confirm and enable' }}
          </button>
          <button type="button" class="bw-btn bw-btn-secondary" @click="cancelSetup">
            Cancel
          </button>
        </div>
      </div>

      <form
        v-else-if="enabled"
        class="space-y-4 border-t border-border pt-5"
        @submit.prevent="disableTwoFactor"
      >
        <p class="text-sm text-muted-foreground">
          Enter your password and a current authenticator code to disable 2FA.
        </p>
        <div>
          <label class="bw-label" for="disable-password">Password</label>
          <input
            id="disable-password"
            v-model="disablePassword"
            type="password"
            class="bw-input max-w-sm"
            required
            autocomplete="current-password"
          />
        </div>
        <div>
          <label class="bw-label" for="disable-code">Authenticator code</label>
          <input
            id="disable-code"
            v-model="disableCode"
            class="bw-input max-w-xs font-mono tracking-widest"
            inputmode="numeric"
            maxlength="6"
            placeholder="000000"
            required
            autocomplete="one-time-code"
          />
        </div>
        <button
          type="submit"
          class="bw-btn bw-btn-secondary"
          :disabled="disabling || disableCode.length !== 6 || !disablePassword"
        >
          {{ disabling ? 'Disabling…' : 'Disable two-factor' }}
        </button>
      </form>

      <div v-else class="border-t border-border pt-5">
        <button
          type="button"
          class="bw-btn bw-btn-primary"
          :disabled="enabling"
          @click="startEnable"
        >
          {{ enabling ? 'Preparing…' : 'Enable two-factor authentication' }}
        </button>
      </div>

      <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
      <p v-if="success" class="text-sm text-success">{{ success }}</p>
    </template>
  </div>
</template>
