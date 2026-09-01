<script setup lang="ts">
type FlashState = {
  success?: string
  error?: string
  info?: string
  errors?: string[]
}

type ToastKind = 'success' | 'error' | 'info'

const flash = useState<FlashState | null>('flash', () => ({}))
const visible = ref(false)
const toastKind = ref<ToastKind>('info')
const toastText = ref('')
const toastList = ref<string[]>([])

let hideTimer: ReturnType<typeof setTimeout> | null = null

function flashPayload(): FlashState {
  return flash.value ?? {}
}

function hasMessage(value: FlashState) {
  return !!(value.success || value.error || value.info || value.errors?.length)
}

function syncToast(value: FlashState) {
  if (value.error) {
    toastKind.value = 'error'
    toastText.value = value.error
    toastList.value = []
    return
  }
  if (value.errors?.length) {
    toastKind.value = 'error'
    toastText.value = ''
    toastList.value = value.errors
    return
  }
  if (value.success) {
    toastKind.value = 'success'
    toastText.value = value.success
    toastList.value = []
    return
  }
  if (value.info) {
    toastKind.value = 'info'
    toastText.value = value.info
    toastList.value = []
  }
}

function dismiss() {
  visible.value = false
  if (hideTimer) {
    clearTimeout(hideTimer)
    hideTimer = null
  }
  flash.value = {}
}

function scheduleHide() {
  if (hideTimer) clearTimeout(hideTimer)
  hideTimer = setTimeout(() => dismiss(), 6000)
}

watch(
  flash,
  (value) => {
    const payload = value ?? {}
    if (!hasMessage(payload)) {
      visible.value = false
      return
    }
    syncToast(payload)
    visible.value = true
    scheduleHide()
  },
  { deep: true, immediate: true },
)

onUnmounted(() => {
  if (hideTimer) clearTimeout(hideTimer)
})

const titleMap: Record<ToastKind, string> = {
  success: 'موفق',
  error: 'خطا',
  info: 'اطلاع',
}

const iconMap: Record<ToastKind, string> = {
  success: '✓',
  error: '✕',
  info: 'ℹ',
}
</script>

<template>
  <Teleport to="body">
    <Transition name="flash-popup">
      <div
        v-if="visible && hasMessage(flashPayload())"
        class="flash-popup"
        role="alertdialog"
        aria-live="assertive"
        aria-modal="true"
        @click.self="dismiss"
      >
        <div class="flash-popup__panel" :class="`flash-popup__panel--${toastKind}`">
          <button type="button" class="flash-popup__close" aria-label="بستن" @click="dismiss">
            ✕
          </button>

          <div class="flash-popup__icon" aria-hidden="true">{{ iconMap[toastKind] }}</div>
          <p class="flash-popup__title">{{ titleMap[toastKind] }}</p>

          <p v-if="toastText" class="flash-popup__message">{{ toastText }}</p>
          <ul v-else-if="toastList.length" class="flash-popup__list">
            <li v-for="(err, i) in toastList" :key="i">{{ err }}</li>
          </ul>

          <button type="button" class="flash-popup__action" @click="dismiss">
            باشه
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.flash-popup {
  position: fixed;
  inset: 0;
  z-index: 100001;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.72);
  backdrop-filter: blur(2px);
}

.flash-popup__panel {
  position: relative;
  width: min(100%, 22rem);
  padding: 1.25rem 1.1rem 1rem;
  border-radius: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: #14141f;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.45);
  text-align: center;
}

.flash-popup__panel--success {
  border-color: rgba(34, 197, 94, 0.45);
}

.flash-popup__panel--error {
  border-color: rgba(239, 68, 68, 0.45);
}

.flash-popup__panel--info {
  border-color: rgba(59, 130, 246, 0.45);
}

.flash-popup__close {
  position: absolute;
  top: 0.55rem;
  left: 0.65rem;
  border: none;
  background: transparent;
  color: #9ca3af;
  font-size: 0.95rem;
  line-height: 1;
  cursor: pointer;
  padding: 0.2rem;
}

.flash-popup__icon {
  width: 2.75rem;
  height: 2.75rem;
  margin: 0 auto 0.65rem;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  font-weight: 800;
}

.flash-popup__panel--success .flash-popup__icon {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
}

.flash-popup__panel--error .flash-popup__icon {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
}

.flash-popup__panel--info .flash-popup__icon {
  background: rgba(59, 130, 246, 0.15);
  color: #93c5fd;
}

.flash-popup__title {
  margin: 0 0 0.45rem;
  font-size: 1rem;
  font-weight: 800;
  color: #fff;
}

.flash-popup__message {
  margin: 0 0 1rem;
  font-size: 0.875rem;
  line-height: 1.7;
  color: #d1d5db;
}

.flash-popup__list {
  margin: 0 0 1rem;
  padding: 0;
  list-style: none;
  text-align: right;
  font-size: 0.875rem;
  line-height: 1.7;
  color: #fca5a5;
}

.flash-popup__list li + li {
  margin-top: 0.35rem;
}

.flash-popup__action {
  width: 100%;
  border: none;
  border-radius: 0.65rem;
  padding: 0.65rem 0.85rem;
  font-size: 0.875rem;
  font-weight: 800;
  cursor: pointer;
  color: #fff;
}

.flash-popup__panel--success .flash-popup__action {
  background: linear-gradient(135deg, #22c55e, #16a34a);
}

.flash-popup__panel--error .flash-popup__action {
  background: linear-gradient(135deg, #ef4444, #dc2626);
}

.flash-popup__panel--info .flash-popup__action {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.flash-popup-enter-active,
.flash-popup-leave-active {
  transition: opacity 0.2s ease;
}

.flash-popup-enter-active .flash-popup__panel,
.flash-popup-leave-active .flash-popup__panel {
  transition: transform 0.22s ease, opacity 0.22s ease;
}

.flash-popup-enter-from,
.flash-popup-leave-to {
  opacity: 0;
}

.flash-popup-enter-from .flash-popup__panel,
.flash-popup-leave-to .flash-popup__panel {
  transform: translateY(12px) scale(0.96);
  opacity: 0;
}
</style>
