<script setup lang="ts">
type FlashState = {
  success?: string
  error?: string
  info?: string
  errors?: string[]
}

const flash = useState<FlashState>('flash', () => ({}))
const visible = ref(false)
let hideTimer: ReturnType<typeof setTimeout> | null = null

function hasMessage(value: FlashState) {
  return !!(value.success || value.error || value.info || value.errors?.length)
}

watch(
  flash,
  (value) => {
    if (!hasMessage(value)) {
      visible.value = false
      return
    }
    visible.value = true
    if (hideTimer) clearTimeout(hideTimer)
    hideTimer = setTimeout(() => {
      visible.value = false
      flash.value = {}
    }, 5000)
  },
  { deep: true, immediate: true },
)

onUnmounted(() => {
  if (hideTimer) clearTimeout(hideTimer)
})
</script>

<template>
  <div v-if="visible && hasMessage(flash)">
    <div v-if="flash.success" class="container mx-auto px-4 mt-4">
      <div class="bg-success/20 border border-success/50 text-success px-4 py-3 rounded-xl text-sm">
        {{ flash.success }}
      </div>
    </div>
    <div v-if="flash.info" class="container mx-auto px-4 mt-4">
      <div class="bg-secondary/20 border border-secondary/50 text-blue-200 px-4 py-3 rounded-xl text-sm">
        {{ flash.info }}
      </div>
    </div>
    <div v-if="flash.error" class="container mx-auto px-4 mt-4">
      <div class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm">
        {{ flash.error }}
      </div>
    </div>
    <div v-if="flash.errors?.length" class="container mx-auto px-4 mt-4">
      <div class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm">
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(err, i) in flash.errors" :key="i">{{ err }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>
