<script setup lang="ts">
import { computed } from 'vue'
import { initialsOf } from '@/lib/status'

const props = withDefaults(
  defineProps<{
    name?: string | null
    size?: 'sm' | 'md' | 'lg' | 'xl'
    tone?: 'primary' | 'accent'
  }>(),
  { size: 'md', tone: 'primary' },
)

const initials = computed(() => initialsOf(props.name))

const sizeClass = computed(
  () =>
    ({
      sm: 'h-8 w-8 text-xs',
      md: 'h-9 w-9 text-sm',
      lg: 'h-11 w-11 text-sm',
      xl: 'h-16 w-16 text-lg',
    })[props.size],
)

const toneClass = computed(
  () =>
    ({
      primary: 'bg-primary-50 text-primary-700',
      accent: 'bg-accent-100 text-accent-700',
    })[props.tone],
)
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold"
    :class="[sizeClass, toneClass]"
    aria-hidden="true"
  >
    {{ initials }}
  </span>
</template>
