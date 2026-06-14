<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'stats' | 'list' | 'panel' | 'cards' | 'detail' | 'form'
    rows?: number
    count?: number
  }>(),
  { variant: 'list', rows: 5, count: 4 },
)
</script>

<template>
  <!-- KPI stat cards -->
  <div
    v-if="variant === 'stats'"
    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
  >
    <div v-for="i in count" :key="i" class="bw-card p-5">
      <div class="bw-skeleton h-3 w-24" />
      <div class="bw-skeleton mt-4 h-8 w-16" />
    </div>
  </div>

  <!-- standalone list card -->
  <div v-else-if="variant === 'list'" class="bw-card divide-y divide-border">
    <div v-for="i in rows" :key="i" class="flex items-center gap-3 px-5 py-4">
      <div class="bw-skeleton h-9 w-9 shrink-0 rounded-full" />
      <div class="flex-1 space-y-2">
        <div class="bw-skeleton h-3.5 w-1/3" />
        <div class="bw-skeleton h-3 w-1/2" />
      </div>
      <div class="bw-skeleton h-5 w-16 rounded-full" />
    </div>
  </div>

  <!-- rows inside an existing card -->
  <div v-else-if="variant === 'panel'" class="divide-y divide-border">
    <div v-for="i in rows" :key="i" class="space-y-2 px-6 py-4">
      <div class="bw-skeleton h-4 w-1/3" />
      <div class="bw-skeleton h-3 w-2/3" />
    </div>
  </div>

  <!-- card grid -->
  <div
    v-else-if="variant === 'cards'"
    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
  >
    <div v-for="i in count" :key="i" class="bw-card space-y-3 p-5">
      <div class="bw-skeleton h-4 w-2/3" />
      <div class="bw-skeleton h-3 w-1/2" />
      <div class="bw-skeleton h-3 w-1/3" />
    </div>
  </div>

  <!-- detail page -->
  <div v-else-if="variant === 'detail'" class="space-y-6">
    <div class="space-y-2">
      <div class="bw-skeleton h-7 w-64" />
      <div class="bw-skeleton h-4 w-40" />
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
      <div class="bw-card space-y-3 p-6">
        <div class="bw-skeleton h-4 w-32" />
        <div class="bw-skeleton h-3 w-full" />
        <div class="bw-skeleton h-3 w-3/4" />
        <div class="bw-skeleton h-3 w-2/3" />
      </div>
      <div class="bw-card space-y-3 p-6">
        <div class="bw-skeleton h-4 w-32" />
        <div class="bw-skeleton h-3 w-full" />
        <div class="bw-skeleton h-3 w-3/4" />
        <div class="bw-skeleton h-3 w-2/3" />
      </div>
    </div>
  </div>

  <!-- form -->
  <div v-else-if="variant === 'form'" class="bw-card space-y-4 p-6">
    <div v-for="i in rows" :key="i" class="space-y-2">
      <div class="bw-skeleton h-3 w-24" />
      <div class="bw-skeleton h-9 w-full" />
    </div>
  </div>
</template>
