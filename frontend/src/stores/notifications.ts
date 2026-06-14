import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationsApi } from '@/lib/api'

export const useNotificationsStore = defineStore('notifications', () => {
  const unread = ref(0)
  const loaded = ref(false)

  async function refresh() {
    try {
      unread.value = (await notificationsApi.list(true)).length
    } catch {
      unread.value = 0
    } finally {
      loaded.value = true
    }
  }

  function set(count: number) {
    unread.value = Math.max(0, count)
  }

  function decrement() {
    unread.value = Math.max(0, unread.value - 1)
  }

  return { unread, loaded, refresh, set, decrement }
})
