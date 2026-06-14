import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function usePermissions() {
  const auth = useAuthStore()

  const permissions = computed(() => auth.user?.permissions ?? [])

  function can(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  const primaryRole = computed(() => auth.user?.roles?.[0] ?? '')

  return { permissions, can, primaryRole }
}
