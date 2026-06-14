<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

const props = defineProps<{
  modelValue?: string | null
  width?: number
  height?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const lastPoint = ref<{ x: number; y: number } | null>(null)

const width = props.width ?? 400
const height = props.height ?? 120

function getContext() {
  const canvas = canvasRef.value
  if (!canvas) return null
  const ctx = canvas.getContext('2d')
  if (!ctx) return null
  ctx.strokeStyle = '#1a1a1a'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  return ctx
}

function pointerPos(event: PointerEvent) {
  const canvas = canvasRef.value
  if (!canvas) return { x: 0, y: 0 }
  const rect = canvas.getBoundingClientRect()
  return {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top,
  }
}

function startDraw(event: PointerEvent) {
  isDrawing.value = true
  lastPoint.value = pointerPos(event)
  canvasRef.value?.setPointerCapture(event.pointerId)
}

function draw(event: PointerEvent) {
  if (!isDrawing.value || !lastPoint.value) return
  const ctx = getContext()
  if (!ctx) return
  const point = pointerPos(event)
  ctx.beginPath()
  ctx.moveTo(lastPoint.value.x, lastPoint.value.y)
  ctx.lineTo(point.x, point.y)
  ctx.stroke()
  lastPoint.value = point
  emitValue()
}

function endDraw(event: PointerEvent) {
  if (!isDrawing.value) return
  isDrawing.value = false
  lastPoint.value = null
  canvasRef.value?.releasePointerCapture(event.pointerId)
  emitValue()
}

function emitValue() {
  const canvas = canvasRef.value
  if (!canvas) return
  emit('update:modelValue', canvas.toDataURL('image/png'))
}

function clear() {
  const canvas = canvasRef.value
  const ctx = getContext()
  if (!canvas || !ctx) return
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  emit('update:modelValue', '')
}

function restoreFromValue(value?: string | null) {
  const canvas = canvasRef.value
  const ctx = getContext()
  if (!canvas || !ctx) return
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  if (!value || !value.startsWith('data:image')) return
  const img = new Image()
  img.onload = () => ctx.drawImage(img, 0, 0)
  img.src = value
}

onMounted(() => restoreFromValue(props.modelValue))

watch(
  () => props.modelValue,
  (value) => {
    if (!isDrawing.value) restoreFromValue(value)
  },
)
</script>

<template>
  <div class="space-y-2">
    <canvas
      ref="canvasRef"
      :width="width"
      :height="height"
      class="w-full cursor-crosshair rounded-lg border border-border bg-white touch-none"
      @pointerdown.prevent="startDraw"
      @pointermove.prevent="draw"
      @pointerup.prevent="endDraw"
      @pointerleave.prevent="endDraw"
    />
    <button type="button" class="bw-btn bw-btn-ghost bw-btn-sm" @click="clear">
      Clear signature
    </button>
  </div>
</template>
