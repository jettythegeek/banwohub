<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { PhBellSlash } from '@phosphor-icons/vue'
import EmptyState from '@/components/common/EmptyState.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import { humanize } from '@/lib/status'
import { notificationsApi } from '@/lib/api'
import { formatApiError } from '@/lib/api-error'
import { notificationLink } from '@/lib/notification-links'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification } from '@/types'

const notifications = ref<AppNotification[]>([])
const unreadOnly = ref(false)
const isLoading = ref(true)
const updatingId = ref<number | null>(null)
const isMarkingAll = ref(false)
const error = ref<string | null>(null)
const selectedId = ref<number | null>(null)
const store = useNotificationsStore()

const unreadCount = computed(
  () => notifications.value.filter((notification) => !notification.read_at).length,
)

async function load() {
  isLoading.value = true
  error.value = null
  try {
    notifications.value = await notificationsApi.list(unreadOnly.value)
  } catch (err) {
    error.value = formatApiError(err, 'Notifications are not available yet.')
  } finally {
    isLoading.value = false
  }
}

async function markRead(notification: AppNotification) {
  updatingId.value = notification.id
  error.value = null
  try {
    const updated = await notificationsApi.markRead(notification.id)
    notifications.value = notifications.value.map((item) =>
      item.id === updated.id ? updated : item,
    )
    store.decrement()
  } catch (err) {
    error.value = formatApiError(err, 'We could not mark this notification read.')
  } finally {
    updatingId.value = null
  }
}

async function markAllRead() {
  isMarkingAll.value = true
  error.value = null
  try {
    await notificationsApi.markAllRead()
    store.set(0)
    await load()
  } catch (err) {
    error.value = formatApiError(err, 'We could not mark notifications read.')
  } finally {
    isMarkingAll.value = false
  }
}

function formatDate(iso?: string) {
  if (!iso) return 'Recently'
  return new Date(iso).toLocaleString()
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Notifications"
      :subtitle="`${unreadCount} unread`"
    >
      <template #actions>
        <button
          type="button"
          class="bw-btn bw-btn-outline"
          :disabled="isMarkingAll || unreadCount === 0"
          @click="markAllRead"
        >
          {{ isMarkingAll ? 'Marking…' : 'Mark all read' }}
        </button>
      </template>
    </PageHeader>

    <section class="bw-card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
        <label class="flex items-center gap-2 text-sm text-muted-foreground">
          <input
            v-model="unreadOnly"
            type="checkbox"
            class="bw-focus-ring h-4 w-4 rounded border-border-strong"
            @change="load"
          />
          Show unread only
        </label>
        <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="load">
          Refresh
        </button>
      </div>

      <p v-if="error" class="p-4 text-sm text-destructive" role="alert">{{ error }}</p>

      <Skeleton v-else-if="isLoading" variant="panel" :rows="5" />

      <div v-else-if="notifications.length" class="divide-y divide-border">
        <article
          v-for="notification in notifications"
          :key="notification.id"
          class="flex cursor-pointer flex-wrap items-start justify-between gap-4 px-6 py-4 text-sm transition-colors hover:bg-surface-muted"
          :class="selectedId === notification.id ? 'bw-row-selected' : ''"
          @click="selectedId = notification.id"
        >
          <div class="flex min-w-[220px] flex-1 gap-3">
            <span
              class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
              :class="!notification.read_at ? 'bg-destructive' : 'bg-transparent'"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <h2 class="font-medium text-foreground">{{ notification.title }}</h2>
              <p v-if="notification.body" class="mt-1 text-muted-foreground">
                {{ notification.body }}
              </p>
              <p class="mt-2 text-xs text-muted-foreground">
                {{ humanize(notification.type) }} · {{ formatDate(notification.created_at) }}
                <span v-if="notification.actor"> · {{ notification.actor.name }}</span>
              </p>
              <RouterLink
                v-if="notificationLink(notification)"
                :to="notificationLink(notification)!"
                class="mt-2 inline-flex text-xs font-medium text-primary-700 hover:underline"
                @click="!notification.read_at && markRead(notification)"
              >
                View details →
              </RouterLink>
            </div>
          </div>
          <button
            v-if="!notification.read_at"
            type="button"
            class="bw-btn bw-btn-outline bw-btn-sm"
            :disabled="updatingId === notification.id"
            @click="markRead(notification)"
          >
            {{ updatingId === notification.id ? 'Saving…' : 'Mark read' }}
          </button>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="PhBellSlash"
        title="You're all caught up"
        message="No notifications to show right now."
      />
    </section>
  </div>
</template>
