import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  getStoredPortalToken,
  persistPortalToken,
  portalAuthApi,
} from '@/lib/portal-api'
import type { PortalUser } from '@/types'

export type PortalDashboardStats = {
  active_cases: number
  unpaid_balance: number
  unread_messages?: number
  pending_invoices?: number
  recent_documents?: number
}

export const usePortalAuthStore = defineStore('portalAuth', () => {
  const user = ref<PortalUser | null>(null)
  const dashboardStats = ref<PortalDashboardStats | null>(null)
  const loading = ref(true)
  let bootstrap: Promise<void> | null = null

  const dashboardUnread = computed(() => dashboardStats.value?.unread_messages ?? 0)

  function setDashboardStats(stats: PortalDashboardStats) {
    dashboardStats.value = stats
  }

  function ensureLoaded(): Promise<void> {
    if (!bootstrap) {
      bootstrap = refreshUser()
    }
    return bootstrap
  }

  async function refreshUser() {
    const token = getStoredPortalToken()
    if (!token) {
      user.value = null
      loading.value = false
      return
    }
    try {
      user.value = await portalAuthApi.me()
    } catch {
      persistPortalToken(null)
      user.value = null
    } finally {
      loading.value = false
    }
  }

  async function login(email: string, password: string) {
    const data = await portalAuthApi.login(email, password)
    persistPortalToken(data.token)
    user.value = data.user
  }

  async function logout() {
    try {
      await portalAuthApi.logout()
    } catch {
      /* ignore */
    }
    persistPortalToken(null)
    user.value = null
  }

  return {
    user,
    dashboardStats,
    dashboardUnread,
    loading,
    ensureLoaded,
    refreshUser,
    login,
    logout,
    setDashboardStats,
  }
})
