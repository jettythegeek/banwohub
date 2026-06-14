<script setup lang="ts">
import { value, visible } from '@/lib/progress'
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 top-0 z-[100] h-0.5"
    aria-hidden="true"
  >
    <div
      class="h-full bg-accent-300 transition-[width,opacity] duration-200 ease-out"
      :style="{
        width: `${value}%`,
        opacity: visible ? 1 : 0,
      }"
    />
  </div>
</template>
