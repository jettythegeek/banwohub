<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'
import { PhX } from '@phosphor-icons/vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { size: 'md' },
)

const emit = defineEmits<{ close: [] }>()

const sizeClass = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.open) emit('close')
}

watch(
  () => props.open,
  (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
  },
)

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <transition name="bw-modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <div
          class="absolute inset-0 bg-neutral-900/40"
          aria-hidden="true"
          @click="emit('close')"
        />
        <div
          class="relative flex max-h-[min(90vh,800px)] w-full flex-col overflow-hidden rounded-xl bg-surface shadow-modal"
          :class="sizeClass[size]"
        >
          <header
            class="flex shrink-0 items-center justify-between gap-4 border-b border-border px-5 py-4"
          >
            <h2 class="text-base font-semibold text-foreground">{{ title }}</h2>
            <button
              type="button"
              class="bw-btn bw-btn-ghost bw-btn-icon"
              aria-label="Close"
              @click="emit('close')"
            >
              <PhX class="h-5 w-5" />
            </button>
          </header>
          <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <slot />
          </div>
          <footer
            v-if="$slots.footer"
            class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4"
          >
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.bw-modal-enter-active,
.bw-modal-leave-active {
  transition: opacity 0.15s ease;
}
.bw-modal-enter-active > div:last-child,
.bw-modal-leave-active > div:last-child {
  transition: transform 0.15s ease;
}
.bw-modal-enter-from,
.bw-modal-leave-to {
  opacity: 0;
}
.bw-modal-enter-from > div:last-child,
.bw-modal-leave-to > div:last-child {
  transform: scale(0.98) translateY(4px);
}
@media (prefers-reduced-motion: reduce) {
  .bw-modal-enter-active,
  .bw-modal-leave-active,
  .bw-modal-enter-active > div:last-child,
  .bw-modal-leave-active > div:last-child {
    transition: none;
  }
}
</style>
