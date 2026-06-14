<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { aiProvidersApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import type { AiProviderConfig, AiProviderName } from '@/types'

const loading = ref(false)
const savingKey = ref<AiProviderName | null>(null)
const savingModel = ref<AiProviderName | null>(null)
const testingProvider = ref<AiProviderName | null>(null)
const togglingProvider = ref<AiProviderName | null>(null)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const activeProvider = ref<AiProviderName | null>(null)
const providers = ref<AiProviderConfig[]>([])
const expandedProvider = ref<AiProviderName | null>(null)

const forms = reactive<Record<AiProviderName, { api_key: string; model: string }>>({
  openai: { api_key: '', model: '' },
  anthropic: { api_key: '', model: '' },
  google: { api_key: '', model: '' },
  deepseek: { api_key: '', model: '' },
})

const providerOrder: AiProviderName[] = ['openai', 'anthropic', 'google', 'deepseek']

const sortedProviders = computed(() =>
  [...providers.value].sort(
    (a, b) => providerOrder.indexOf(a.provider) - providerOrder.indexOf(b.provider),
  ),
)

function syncForms(rows: AiProviderConfig[]) {
  for (const row of rows) {
    forms[row.provider] = {
      api_key: '',
      model: row.model ?? row.default_model ?? '',
    }
  }
}

function applyProviderList(rows: AiProviderConfig[], nextActive: AiProviderName | null) {
  activeProvider.value = nextActive
  providers.value = rows
  syncForms(rows)
}

async function loadProviders() {
  loading.value = true
  error.value = null
  try {
    const data = await aiProvidersApi.list()
    applyProviderList(data.providers, data.active_provider)
    if (!expandedProvider.value && data.active_provider) {
      expandedProvider.value = data.active_provider
    }
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    loading.value = false
  }
}

function expandProvider(provider: AiProviderName) {
  expandedProvider.value = expandedProvider.value === provider ? null : provider
}

function isExpanded(provider: AiProviderName) {
  return expandedProvider.value === provider
}

async function saveApiKey(provider: AiProviderConfig) {
  const apiKey = forms[provider.provider].api_key.trim()
  if (!apiKey) {
    error.value = 'Enter an API key before saving.'
    return
  }

  savingKey.value = provider.provider
  error.value = null
  success.value = null

  try {
    const data = await aiProvidersApi.update({
      provider: provider.provider,
      api_key: apiKey,
    })
    applyProviderList(data.providers, data.active_provider)
    forms[provider.provider].api_key = ''
    success.value = `${provider.label} API key saved. Test the connection to choose a model.`
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    savingKey.value = null
  }
}

async function saveModel(provider: AiProviderConfig) {
  savingModel.value = provider.provider
  error.value = null
  success.value = null

  try {
    const data = await aiProvidersApi.update({
      provider: provider.provider,
      model: forms[provider.provider].model || null,
    })
    applyProviderList(data.providers, data.active_provider)
    success.value = `${provider.label} model saved.`
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    savingModel.value = null
  }
}

async function toggleProvider(provider: AiProviderConfig, enabled: boolean) {
  expandedProvider.value = provider.provider
  togglingProvider.value = provider.provider
  error.value = null
  success.value = null

  try {
    const data = await aiProvidersApi.update({
      provider: provider.provider,
      is_enabled: enabled,
    })
    applyProviderList(data.providers, data.active_provider)
    success.value = enabled
      ? `${provider.label} is now the active AI provider.`
      : `${provider.label} disabled.`
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    togglingProvider.value = null
  }
}

async function testConnection(provider: AiProviderConfig) {
  testingProvider.value = provider.provider
  error.value = null
  success.value = null
  const apiKey = forms[provider.provider].api_key.trim()

  try {
    const result = await aiProvidersApi.testConnection(provider.provider, apiKey || undefined)
    applyProviderList(result.providers, result.active_provider)
    if (result.success) {
      success.value = result.message
    } else {
      error.value = result.message
    }
  } catch (err) {
    error.value = formatApiError(err)
  } finally {
    testingProvider.value = null
  }
}

function onToggleChange(provider: AiProviderConfig, event: Event) {
  const input = event.target as HTMLInputElement
  void toggleProvider(provider, input.checked)
}

onMounted(loadProviders)
</script>

<template>
  <div class="space-y-4 max-w-2xl">
    <div>
      <h2 class="font-semibold text-foreground">AI providers</h2>
      <p class="mt-1 text-sm text-muted-foreground">
        Enable one provider at a time. Save an API key, verify the connection, then choose a model.
      </p>
      <p v-if="activeProvider" class="mt-2 text-sm text-foreground">
        Active:
        <span class="font-medium capitalize">{{ activeProvider }}</span>
      </p>
      <p v-else class="mt-2 text-sm text-muted-foreground">
        No active provider — configure an AI provider below or set up the AI service with AI_OPENAI_API_KEY.
      </p>
    </div>

    <Skeleton v-if="loading" variant="form" :rows="4" />

    <template v-else>
      <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
      <p v-if="success" class="text-sm text-success">{{ success }}</p>

      <div class="border border-border rounded-lg divide-y divide-border">
        <section v-for="provider in sortedProviders" :key="provider.provider">
          <div
            class="flex items-center gap-3 px-3 py-2.5"
            :class="{ 'bg-primary-50': isExpanded(provider.provider) }"
          >
            <button
              type="button"
              class="min-w-0 flex-1 text-left"
              @click="expandProvider(provider.provider)"
            >
              <span class="block text-sm font-medium text-foreground">{{ provider.label }}</span>
              <span class="block truncate text-xs text-muted-foreground">
                {{ provider.is_active ? 'Active provider' : provider.description }}
              </span>
            </button>

            <label
              class="relative inline-flex shrink-0 cursor-pointer items-center"
              :title="
                provider.can_enable
                  ? 'Enable as active provider'
                  : 'Save API key and pass connection test first'
              "
              @click.stop
            >
              <input
                type="checkbox"
                class="peer sr-only"
                :checked="provider.is_enabled"
                :disabled="!provider.can_enable || togglingProvider === provider.provider"
                @change="onToggleChange(provider, $event)"
              />
              <span
                class="h-5 w-9 rounded-full border border-border bg-muted transition-colors peer-checked:border-primary peer-checked:bg-primary peer-disabled:opacity-50"
              />
              <span
                class="pointer-events-none absolute left-0.5 top-0.5 h-4 w-4 rounded-full border border-border bg-surface transition-transform peer-checked:translate-x-4"
              />
            </label>
          </div>

          <div
            v-if="isExpanded(provider.provider)"
            class="space-y-3 border-t border-border bg-surface px-3 py-3"
          >
            <div>
              <label class="bw-label" :for="`key-${provider.provider}`">API key</label>
              <input
                :id="`key-${provider.provider}`"
                v-model="forms[provider.provider].api_key"
                type="password"
                class="bw-input"
                autocomplete="off"
                placeholder="Paste provider API key"
              />
              <p v-if="provider.api_key_set" class="mt-1 text-xs text-muted-foreground">
                Saved: {{ provider.api_key_masked }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="bw-btn bw-btn-primary"
                :disabled="savingKey === provider.provider"
                @click="saveApiKey(provider)"
              >
                {{ savingKey === provider.provider ? 'Saving…' : 'Save key' }}
              </button>
              <button
                type="button"
                class="bw-btn bw-btn-secondary"
                :disabled="testingProvider === provider.provider || !provider.api_key_set"
                @click="testConnection(provider)"
              >
                {{ testingProvider === provider.provider ? 'Testing…' : 'Test connection' }}
              </button>
            </div>

            <p
              v-if="provider.api_key_set && !provider.can_select_model"
              class="text-xs text-muted-foreground"
            >
              Run a successful connection test to unlock model selection.
            </p>
            <p
              v-else-if="provider.last_test_success_at"
              class="text-xs text-success"
            >
              Connection verified.
            </p>

            <div v-if="provider.can_select_model">
              <label class="bw-label" :for="`model-${provider.provider}`">Model</label>
              <select
                :id="`model-${provider.provider}`"
                v-model="forms[provider.provider].model"
                class="bw-input"
              >
                <option
                  v-for="model in provider.available_models"
                  :key="model"
                  :value="model"
                >
                  {{ model }}
                </option>
              </select>
              <button
                type="button"
                class="bw-btn bw-btn-secondary mt-2"
                :disabled="savingModel === provider.provider"
                @click="saveModel(provider)"
              >
                {{ savingModel === provider.provider ? 'Saving…' : 'Save model' }}
              </button>
            </div>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
