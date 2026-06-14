<script setup lang="ts">
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import { PhScales } from '@phosphor-icons/vue'
import TopProgressBar from '@/components/layout/TopProgressBar.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
// Brief branded splash only during the very first /auth/me bootstrap.
const showSplash = computed(() => auth.loading)
</script>

<template>
  <TopProgressBar />
  <transition name="bw-splash">
    <div
      v-if="showSplash"
      class="fixed inset-0 z-[90] flex flex-col items-center justify-center gap-4 bg-background"
    >
      <span
        class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground"
      >
        <PhScales class="h-6 w-6" weight="fill" />
      </span>
      <p class="text-sm font-medium text-muted-foreground">Banwolaw Hub</p>
    </div>
  </transition>
  <RouterView />
</template>

<style>
.bw-splash-leave-active {
  transition: opacity 0.2s ease;
}
.bw-splash-leave-to {
  opacity: 0;
}
</style>
