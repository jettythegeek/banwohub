<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

defineProps<{
  title: string
  subtitle: string
}>()

const heroMissing = ref(false)
const heroSrc = '/images/login-hero.png'
</script>

<template>
  <div class="flex min-h-screen bg-background">
    <div
      class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-[var(--sidebar-bg)] p-10 text-white lg:flex"
    >
      <RouterLink to="/" class="text-lg font-semibold tracking-tight text-white">
        Banwolaw Hub
      </RouterLink>
      <div class="relative aspect-[4/3] w-full max-w-lg overflow-hidden rounded-xl">
        <div
          class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"
          aria-hidden="true"
        />
        <img
          v-show="!heroMissing"
          :src="heroSrc"
          alt=""
          class="relative h-full w-full rounded-xl object-cover opacity-90"
          @error="heroMissing = true"
        />
      </div>
      <p class="max-w-md text-sm text-white/70">
        Access your matters, messages, and documents in one bright workspace.
      </p>
    </div>
    <div class="flex flex-1 flex-col justify-center p-6 sm:p-10">
      <div
        class="mx-auto w-full max-w-md rounded-xl border border-border bg-surface p-8 shadow-card"
      >
        <div class="mb-8 lg:hidden">
          <RouterLink to="/" class="text-lg font-semibold text-foreground">
            Banwolaw Hub
          </RouterLink>
        </div>
        <h1 class="text-2xl font-semibold text-foreground">{{ title }}</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
        <div class="mt-8">
          <slot />
        </div>
        <div v-if="$slots.footer" class="mt-6 border-t border-border pt-6">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </div>
</template>
