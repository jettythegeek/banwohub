<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { PhList, PhSignOut } from '@phosphor-icons/vue'
import PortalSidebar from '@/components/layout/PortalSidebar.vue'
import { portalDashboardApi } from '@/lib/portal-api'
import { usePortalAuthStore } from '@/stores/portalAuth'

const auth = usePortalAuthStore()
const router = useRouter()
const sidebarOpen = ref(false)

async function signOut() {
  await auth.logout()
  await router.push({ name: 'portal-login' })
}

onMounted(async () => {
  try {
    const data = await portalDashboardApi.get()
    auth.setDashboardStats(data.stats)
  } catch {
    /* sidebar badge is optional */
  }
})
</script>

<template>
  <div class="min-h-dvh bg-background text-foreground">
    <PortalSidebar class="hidden lg:flex">
      <template #sign-out>
        <button
          type="button"
          class="bw-focus-ring rounded-md p-2 text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-fg"
          aria-label="Sign out"
          @click="signOut"
        >
          <PhSignOut class="h-[18px] w-[18px]" weight="regular" />
        </button>
      </template>
    </PortalSidebar>

    <transition name="bw-fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-neutral-900/40 lg:hidden"
        @click="sidebarOpen = false"
      />
    </transition>
    <transition name="bw-slide">
      <PortalSidebar v-if="sidebarOpen" class="z-50 lg:hidden" @navigate="sidebarOpen = false">
        <template #sign-out>
          <button
            type="button"
            class="bw-focus-ring rounded-md p-2 text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-fg"
            aria-label="Sign out"
            @click="signOut"
          >
            <PhSignOut class="h-[18px] w-[18px]" weight="regular" />
          </button>
        </template>
      </PortalSidebar>
    </transition>

    <div class="flex min-h-dvh min-w-0 flex-col lg:ml-64">
      <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-surface px-4 lg:hidden"
      >
        <button
          type="button"
          class="bw-focus-ring rounded-md p-2 text-muted-foreground hover:bg-surface-muted hover:text-foreground"
          aria-label="Open menu"
          @click="sidebarOpen = !sidebarOpen"
        >
          <PhList class="h-5 w-5" weight="bold" />
        </button>
        <span class="text-sm font-semibold text-foreground">Banwolaw Client Portal</span>
      </header>

      <main class="flex-1 overflow-y-auto">
        <div class="mx-auto w-full max-w-[1400px] p-4 sm:p-6 lg:p-8">
          <RouterView v-slot="{ Component }">
            <transition name="bw-page" mode="out-in">
              <component :is="Component" />
            </transition>
          </RouterView>
        </div>
      </main>
    </div>
  </div>
</template>

<style>
.bw-page-enter-active,
.bw-page-leave-active {
  transition: opacity 0.15s ease;
}
.bw-page-enter-from,
.bw-page-leave-to {
  opacity: 0;
}
.bw-fade-enter-active,
.bw-fade-leave-active {
  transition: opacity 0.2s ease;
}
.bw-fade-enter-from,
.bw-fade-leave-to {
  opacity: 0;
}
.bw-slide-enter-active,
.bw-slide-leave-active {
  transition: transform 0.2s ease;
}
.bw-slide-enter-from,
.bw-slide-leave-to {
  transform: translateX(-100%);
}
@media (prefers-reduced-motion: reduce) {
  .bw-page-enter-active,
  .bw-page-leave-active,
  .bw-fade-enter-active,
  .bw-fade-leave-active,
  .bw-slide-enter-active,
  .bw-slide-leave-active {
    transition: none;
  }
}
</style>
