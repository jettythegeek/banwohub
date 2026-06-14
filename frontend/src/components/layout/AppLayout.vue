<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterView } from 'vue-router'
import Sidebar from '@/components/layout/Sidebar.vue'
import Topbar from '@/components/layout/Topbar.vue'
import { useNotificationsStore } from '@/stores/notifications'

const sidebarOpen = ref(false)
const notifications = useNotificationsStore()

onMounted(() => {
  if (!notifications.loaded) notifications.refresh()
})
</script>

<template>
  <div class="min-h-dvh bg-background text-foreground">
    <Sidebar class="hidden lg:flex" />

    <!-- Mobile drawer -->
    <transition name="bw-fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-neutral-900/40 lg:hidden"
        @click="sidebarOpen = false"
      />
    </transition>
    <transition name="bw-slide">
      <Sidebar
        v-if="sidebarOpen"
        class="z-50 lg:hidden"
        @navigate="sidebarOpen = false"
      />
    </transition>

    <div class="flex min-h-dvh min-w-0 flex-col lg:ml-64">
      <Topbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />
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
