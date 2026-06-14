import { computed, ref, watch } from 'vue'

/**
 * Lightweight global loading bar state.
 * Driven by both router navigation and in-flight API requests so the user
 * always sees a thin brand-colored bar instead of a blank "Loading" screen.
 */
const requests = ref(0)
const routing = ref(false)

export const value = ref(0)
export const visible = ref(false)

const active = computed(() => requests.value > 0 || routing.value)

let timer: ReturnType<typeof setInterval> | undefined
let hideTimer: ReturnType<typeof setTimeout> | undefined

watch(active, (on) => {
  if (on) {
    if (hideTimer) {
      clearTimeout(hideTimer)
      hideTimer = undefined
    }
    visible.value = true
    if (value.value === 0) value.value = 8
    if (timer) clearInterval(timer)
    timer = setInterval(() => {
      if (value.value < 90) {
        value.value += Math.max(0.4, (90 - value.value) / 14)
      }
    }, 180)
  } else {
    if (timer) {
      clearInterval(timer)
      timer = undefined
    }
    value.value = 100
    hideTimer = setTimeout(() => {
      if (!active.value) {
        visible.value = false
        value.value = 0
      }
    }, 280)
  }
})

export function requestStart() {
  requests.value += 1
}

export function requestDone() {
  requests.value = Math.max(0, requests.value - 1)
}

export function routeStart() {
  routing.value = true
}

export function routeDone() {
  routing.value = false
}
