<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { PhPlugs } from '@phosphor-icons/vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { integrationsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { IntegrationSetting } from '@/types'

const route = useRoute()
const loading = ref(false)
const connecting = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const integrations = ref<IntegrationSetting[]>([])

async function load() {
  loading.value = true
  error.value = null
  try {
    integrations.value = await integrationsApi.list()
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    loading.value = false
  }
}

function statusBadge(item: IntegrationSetting) {
  if (item.oauth && item.connected) return 'bw-badge-success'
  return item.status === 'configured' ? 'bw-badge-success' : 'bw-badge-neutral'
}

function statusLabel(item: IntegrationSetting) {
  if (item.oauth && item.connected) return 'connected'
  return item.status
}

async function connectGoogleCalendar() {
  connecting.value = true
  error.value = null
  notice.value = null
  try {
    const result = await integrationsApi.googleCalendarConnect()
    if (!result.configured || !result.auth_url) {
      error.value = result.message ?? 'Google Calendar OAuth is not configured on the server.'
      return
    }
    window.location.href = result.auth_url
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    connecting.value = false
  }
}

async function disconnectGoogleCalendar() {
  connecting.value = true
  error.value = null
  try {
    await integrationsApi.googleCalendarDisconnect()
    notice.value = 'Google Calendar disconnected.'
    await load()
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    connecting.value = false
  }
}

function applyOAuthQueryNotice() {
  const status = route.query.google_calendar
  if (status === 'connected') {
    notice.value = 'Google Calendar connected (OAuth stub — token stored for sync adapter).'
  } else if (status === 'error') {
    error.value = 'Google Calendar connection failed. Please try again.'
  }
}

onMounted(async () => {
  applyOAuthQueryNotice()
  await load()
})
</script>

<template>
  <div class="space-y-4">
    <div class="bw-card max-w-3xl p-6">
      <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
          <PhPlugs class="h-5 w-5" weight="fill" />
        </span>
        <div>
          <h2 class="font-semibold text-foreground">External integrations</h2>
          <p class="mt-1 text-sm text-muted-foreground">
            Connect SMS, calendar sync, and court e-filing providers. OAuth flows store tokens for
            future sync adapters — configure credentials in your server
            <code class="text-xs">.env</code> file.
          </p>
        </div>
      </div>
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <p v-if="notice" class="text-sm text-success">{{ notice }}</p>

    <Skeleton v-if="loading" variant="panel" :rows="3" />

    <div v-else class="grid max-w-3xl gap-4">
      <article
        v-for="item in integrations"
        :key="item.key"
        class="bw-card p-6"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="font-semibold text-foreground">{{ item.name }}</h3>
            <p class="mt-1 text-sm text-muted-foreground">{{ item.description }}</p>
          </div>
          <span class="bw-badge shrink-0 capitalize" :class="statusBadge(item)">
            {{ statusLabel(item) }}
          </span>
        </div>

        <div v-if="item.oauth" class="mt-4 flex flex-wrap gap-2">
          <button
            v-if="!item.connected"
            type="button"
            class="bw-btn bw-btn-primary bw-btn-sm"
            :disabled="connecting"
            @click="connectGoogleCalendar"
          >
            {{ connecting ? 'Connecting…' : 'Connect Google Calendar' }}
          </button>
          <button
            v-else
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="connecting"
            @click="disconnectGoogleCalendar"
          >
            Disconnect
          </button>
        </div>

        <div class="mt-4 rounded-lg border border-border bg-surface px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Environment keys
          </p>
          <ul class="mt-2 space-y-1 font-mono text-xs text-foreground">
            <li v-for="envKey in item.env_keys" :key="envKey">{{ envKey }}</li>
          </ul>
        </div>
      </article>
    </div>
  </div>
</template>
