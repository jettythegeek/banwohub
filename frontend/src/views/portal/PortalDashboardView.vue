<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  PhBell,
  PhBriefcase,
  PhCalendarBlank,
  PhChatCircle,
  PhDotsSixVertical,
  PhFile,
  PhPlus,
  PhReceipt,
} from '@phosphor-icons/vue'
import AppAvatar from '@/components/common/AppAvatar.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import Skeleton from '@/components/common/Skeleton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { portalDashboardApi } from '@/lib/portal-api'
import { formatApiError } from '@/lib/api-error'
import { formatCurrency } from '@/lib/currency'
import { humanize } from '@/lib/status'
import { usePortalAuthStore } from '@/stores/portalAuth'
import type { MessageThread, PortalDashboardActivity, PortalDashboardData } from '@/types'

const auth = usePortalAuthStore()
const data = ref<PortalDashboardData | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)

const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? 'there')
const recentMessages = computed(() => (data.value?.messages ?? []) as MessageThread[])
const activities = computed(() => data.value?.activities ?? [])
const upcomingAppointments = computed(() => data.value?.upcoming_appointments ?? [])
const pendingInvoices = computed(() =>
  (data.value?.recent_invoices ?? []).filter((inv) =>
    ['sent', 'partial', 'overdue'].includes(inv.status) && inv.balance_due > 0,
  ),
)

const summaryStatCards = computed(() => [
  {
    label: 'Upcoming appointments',
    displayValue: String(upcomingAppointments.value.length),
    detail: upcomingAppointments.value.length === 1 ? 'Next on your schedule' : 'On your schedule',
    icon: PhCalendarBlank,
    iconBg: 'bg-primary-50',
    iconColor: 'text-primary-700',
    href: '/portal/appointments',
  },
  {
    label: 'Active cases',
    displayValue: String(data.value?.stats.active_cases ?? 0),
    detail: (data.value?.stats.active_cases ?? 0) === 1 ? 'Open matter' : 'Open matters',
    icon: PhBriefcase,
    iconBg: 'bg-accent-100',
    iconColor: 'text-accent-700',
    href: '/portal/cases',
  },
  {
    label: 'Outstanding balance',
    displayValue: formatCurrency(data.value?.stats.unpaid_balance ?? 0),
    detail:
      (data.value?.stats.pending_invoices ?? 0) > 0
        ? `${data.value?.stats.pending_invoices} pending invoice${(data.value?.stats.pending_invoices ?? 0) === 1 ? '' : 's'}`
        : 'All invoices paid',
    icon: PhReceipt,
    iconBg: 'bg-primary-100',
    iconColor: 'text-primary-800',
    href: '/portal/invoices',
  },
])

function formatDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString()
}

function formatDateTime(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatTime(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function scheduleDay(iso: string) {
  const date = new Date(iso)
  return {
    weekday: date.toLocaleDateString([], { weekday: 'short' }),
    day: date.getDate(),
    month: date.toLocaleDateString([], { month: 'short' }),
  }
}

function activityIcon(type: PortalDashboardActivity['type']) {
  if (type === 'message') return PhChatCircle
  if (type === 'invoice') return PhReceipt
  if (type === 'appointment') return PhCalendarBlank
  return PhFile
}

function contactName(thread: MessageThread) {
  return thread.latest_message?.sender?.name ?? thread.creator?.name ?? 'Legal team'
}

onMounted(async () => {
  isLoading.value = true
  error.value = null
  try {
    data.value = await portalDashboardApi.get()
    if (data.value?.stats) auth.setDashboardStats(data.value.stats)
  } catch (err) {
    error.value = formatApiError(err, 'Dashboard is not available yet.')
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <section
      class="overflow-hidden rounded-2xl border border-primary-600/30 bg-gradient-to-br from-primary-800 via-primary-700 to-primary-600 p-6 shadow-card sm:p-8"
    >
      <div class="relative max-w-2xl">
        <div
          class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-accent-500/20 blur-2xl"
          aria-hidden="true"
        />
        <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
          Welcome back, {{ firstName }}! <span aria-hidden="true">👋</span>
        </h1>
        <p class="mt-2 text-sm text-primary-100 sm:text-base">
          Your cases, billing, and appointments at a glance.
        </p>
        <RouterLink to="/portal/appointments" class="bw-btn bw-btn-accent mt-5">
          <PhPlus class="h-4 w-4" weight="bold" />
          Book appointment
        </RouterLink>
      </div>
    </section>

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>

    <div v-if="isLoading" class="grid gap-4 md:grid-cols-3">
      <Skeleton v-for="n in 3" :key="n" class="h-32 rounded-xl" />
    </div>
    <div v-else class="grid gap-4 md:grid-cols-3">
      <RouterLink
        v-for="card in summaryStatCards"
        :key="card.label"
        :to="card.href"
        class="bw-card group p-5 transition-colors hover:border-primary-200"
      >
        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {{ card.label }}
        </p>
        <div class="mt-2 flex items-end justify-between gap-3">
          <div class="min-w-0">
            <p class="text-3xl font-bold tabular-nums tracking-tight text-foreground">
              {{ card.displayValue }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground group-hover:text-foreground/80">
              {{ card.detail }}
            </p>
          </div>
          <span
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"
            :class="[card.iconBg, card.iconColor]"
          >
            <component :is="card.icon" class="h-5 w-5" weight="fill" />
          </span>
        </div>
      </RouterLink>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
      <div class="space-y-6">
        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div>
              <h2 class="text-base font-semibold text-foreground">Upcoming schedule</h2>
              <p class="text-sm text-muted-foreground">Your next consultations and meetings.</p>
            </div>
            <RouterLink to="/portal/appointments" class="text-sm font-medium text-primary hover:underline">
              View all
            </RouterLink>
          </div>
          <div v-if="isLoading" class="space-y-3 p-5">
            <Skeleton v-for="n in 3" :key="n" class="h-16 rounded-lg" />
          </div>
          <ul v-else-if="upcomingAppointments.length" class="divide-y divide-border">
            <li
              v-for="appt in upcomingAppointments"
              :key="appt.id"
              class="flex items-center gap-4 px-5 py-4"
            >
              <div
                class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl border border-border bg-primary-50 text-primary-700"
              >
                <span class="text-[10px] font-semibold uppercase tracking-wide">
                  {{ scheduleDay(appt.starts_at).weekday }}
                </span>
                <span class="text-lg font-bold leading-none">{{ scheduleDay(appt.starts_at).day }}</span>
                <span class="text-[10px]">{{ scheduleDay(appt.starts_at).month }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-foreground">
                  {{ humanize(appt.consultation_type) }}
                </p>
                <p class="text-sm text-muted-foreground">
                  {{ formatTime(appt.starts_at) }}
                  <span v-if="appt.lawyer"> · {{ appt.lawyer.name }}</span>
                  <span v-if="appt.case"> · {{ appt.case.title }}</span>
                </p>
              </div>
              <StatusBadge :status="appt.status" />
            </li>
          </ul>
          <EmptyState
            v-else
            :icon="PhCalendarBlank"
            title="No upcoming appointments"
            message="Book a consultation when you're ready to connect with your legal team."
            class="p-6"
          />
        </section>

        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div>
              <h2 class="text-base font-semibold text-foreground">Pending invoices</h2>
              <p class="text-sm text-muted-foreground">Outstanding balances requiring your attention.</p>
            </div>
            <RouterLink to="/portal/invoices" class="text-sm font-medium text-primary hover:underline">
              View all
            </RouterLink>
          </div>
          <div v-if="isLoading" class="space-y-3 p-5">
            <Skeleton v-for="n in 3" :key="n" class="h-14 rounded-lg" />
          </div>
          <ul v-else-if="pendingInvoices.length" class="divide-y divide-border">
            <li v-for="invoice in pendingInvoices" :key="invoice.id">
              <RouterLink
                :to="`/portal/invoices/${invoice.id}`"
                class="flex items-center gap-3 px-5 py-4 hover:bg-surface-muted"
              >
                <PhDotsSixVertical class="h-4 w-4 shrink-0 text-neutral-400" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-foreground">{{ invoice.invoice_number }}</p>
                  <p class="text-sm text-muted-foreground">
                    Due {{ formatDate(invoice.issue_date) }}
                    <span v-if="invoice.case"> · {{ invoice.case.title }}</span>
                  </p>
                </div>
                <div class="flex items-center gap-3">
                  <StatusBadge :status="invoice.status" />
                  <span class="text-sm font-semibold tabular-nums text-foreground">
                    {{ formatCurrency(invoice.balance_due) }}
                  </span>
                </div>
              </RouterLink>
            </li>
          </ul>
          <EmptyState
            v-else
            :icon="PhReceipt"
            title="All caught up"
            message="You have no outstanding invoices at the moment."
            class="p-6"
          />
        </section>

        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <h2 class="text-base font-semibold text-foreground">Shared documents</h2>
            <RouterLink to="/portal/cases" class="text-sm font-medium text-primary hover:underline">
              View cases
            </RouterLink>
          </div>
          <div v-if="isLoading" class="space-y-3 p-5">
            <Skeleton v-for="n in 3" :key="n" class="h-12 rounded-lg" />
          </div>
          <ul v-else-if="data?.recent_documents?.length" class="divide-y divide-border">
            <li
              v-for="doc in data.recent_documents"
              :key="doc.id"
              class="flex items-center gap-3 px-5 py-4"
            >
              <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-100 text-accent-700">
                <PhFile class="h-4 w-4" weight="fill" />
              </span>
              <div class="min-w-0">
                <p class="font-medium text-foreground">{{ doc.name }}</p>
                <p class="text-sm text-muted-foreground">
                  {{ formatDate(doc.created_at) }}
                  <span v-if="doc.case"> · {{ doc.case.title }}</span>
                </p>
              </div>
            </li>
          </ul>
          <EmptyState
            v-else
            :icon="PhFile"
            title="No shared documents"
            message="Documents your firm shares with you will appear here."
            class="p-6"
          />
        </section>
      </div>

      <aside class="space-y-6">
        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div>
              <h2 class="text-base font-semibold text-foreground">Messages</h2>
              <p class="text-sm text-muted-foreground">Recent conversations.</p>
            </div>
            <RouterLink to="/portal/messages" class="text-sm font-medium text-primary hover:underline">
              View all
            </RouterLink>
          </div>
          <ul v-if="recentMessages.length" class="divide-y divide-border">
            <li v-for="thread in recentMessages" :key="thread.id">
              <RouterLink
                :to="`/portal/messages?thread=${thread.id}`"
                class="flex items-center gap-3 px-4 py-3 hover:bg-surface-muted"
              >
                <AppAvatar :name="contactName(thread)" size="sm" tone="primary" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-foreground">{{ thread.subject }}</p>
                  <p class="truncate text-xs text-muted-foreground">
                    {{ contactName(thread) }}
                  </p>
                </div>
                <span
                  v-if="thread.unread_count"
                  class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-action-teal px-1.5 text-[11px] font-semibold text-action-teal-fg"
                >
                  {{ thread.unread_count }}
                </span>
              </RouterLink>
            </li>
          </ul>
          <EmptyState
            v-else
            :icon="PhChatCircle"
            title="No messages"
            message="Start a conversation with your legal team."
            class="p-5"
          />
        </section>

        <section class="bw-card overflow-hidden">
          <div class="bw-card-header">
            <div class="flex items-center gap-2">
              <PhBell class="h-4 w-4 text-primary-700" weight="fill" />
              <h2 class="text-base font-semibold text-foreground">Recent activity</h2>
            </div>
          </div>
          <ul v-if="activities.length" class="px-4 py-2">
            <li
              v-for="(activity, index) in activities"
              :key="`${activity.type}-${index}`"
              class="relative flex gap-3 py-3"
            >
              <span
                v-if="index < activities.length - 1"
                class="absolute left-[15px] top-10 h-[calc(100%-12px)] w-px bg-border"
                aria-hidden="true"
              />
              <span
                class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700"
              >
                <component :is="activityIcon(activity.type)" class="h-4 w-4" weight="fill" />
              </span>
              <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-sm font-medium text-foreground">{{ activity.title }}</p>
                <p v-if="activity.description" class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                  {{ activity.description }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                  {{ formatDateTime(activity.occurred_at) }}
                </p>
              </div>
            </li>
          </ul>
          <EmptyState
            v-else
            :icon="PhBell"
            title="No recent activity"
            message="Updates from your firm will appear here."
            class="p-5"
          />
        </section>
      </aside>
    </div>
  </div>
</template>
