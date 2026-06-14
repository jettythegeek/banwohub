import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, getStoredToken, persistToken } from '@/lib/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(true)
  let bootstrap: Promise<void> | null = null

  /**
   * Resolves once the initial /auth/me bootstrap has completed. Memoized so the
   * app entrypoint and the router guard share a single request instead of each
   * triggering their own blank-screen fetch.
   */
  function ensureLoaded(): Promise<void> {
    if (!bootstrap) {
      bootstrap = refreshUser()
    }
    return bootstrap
  }

  async function refreshUser() {
    const token = getStoredToken()
    if (!token) {
      user.value = null
      loading.value = false
      return
    }
    try {
      const { data } = await api.get<{ data: User } | User>('/auth/me')
      const payload = data as { data?: User }
      user.value = payload.data ?? (data as User)
    } catch {
      persistToken(null)
      user.value = null
    } finally {
      loading.value = false
    }
  }

  type LoginResult =
    | { complete: true; user: User }
    | { complete: false; challengeToken: string }

  async function login(email: string, password: string): Promise<LoginResult> {
    const { data } = await api.post<{
      token?: string
      user?: User
      two_factor_required?: boolean
      challenge_token?: string
    }>('/auth/login', { email, password })

    if (data.two_factor_required && data.challenge_token) {
      return { complete: false, challengeToken: data.challenge_token }
    }

    persistToken(data.token!)
    user.value = data.user!
    return { complete: true, user: data.user! }
  }

  async function verifyTwoFactor(challengeToken: string, code: string) {
    const { data } = await api.post<{ token: string; user: User }>(
      '/auth/two-factor/verify',
      { challenge_token: challengeToken, code },
    )
    persistToken(data.token)
    user.value = data.user
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      /* ignore */
    }
    persistToken(null)
    user.value = null
  }

  return { user, loading, ensureLoaded, refreshUser, login, verifyTwoFactor, logout }
})
