<script setup lang="ts">
const flash = useState<{ success?: string; error?: string; errors?: string[] }>('flash', () => ({}))

const visible = ref(true)

onMounted(() => {
  if (flash.value.success || flash.value.error || flash.value.errors?.length) {
    setTimeout(() => {
      visible.value = false
      setTimeout(() => {
        flash.value = {}
      }, 500)
    }, 5000)
  }
})

function setFlash(payload: { success?: string; error?: string; errors?: string[] }) {
  flash.value = payload
  visible.value = true
  setTimeout(() => {
    visible.value = false
  }, 5000)
}

defineExpose({ setFlash })
</script>

<template>
  <div v-if="visible">
    <div v-if="flash.success" class="container mx-auto px-4 mt-4">
      <div
        class="bg-success/20 border border-success/50 text-success px-4 py-3 rounded-xl text-sm transition-opacity"
        :class="{ 'opacity-0': !visible }"
      >
        {{ flash.success }}
      </div>
    </div>
    <div v-if="flash.error" class="container mx-auto px-4 mt-4">
      <div
        class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm transition-opacity"
        :class="{ 'opacity-0': !visible }"
      >
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
