<script setup lang="ts">
const api = useApi()

const key = ref('')
const question = ref('')
const answer = defineModel<string>({ default: '' })
const loading = ref(false)
const error = ref('')

async function refresh() {
  loading.value = true
  error.value = ''
  try {
    const data = await api.auth.captcha()
    key.value = data.key
    question.value = data.question
    answer.value = ''
  } catch {
    error.value = 'بارگذاری کد امنیتی ناموفق بود.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void refresh()
})

defineExpose({ key, refresh })
</script>

<template>
  <div>
    <div class="flex items-center justify-between gap-2 mb-1">
      <label class="block text-sm text-gray-400">
        کد امنیتی: <span class="text-white font-bold">{{ question || '...' }}</span>
      </label>
      <button
        type="button"
        class="text-xs text-secondary hover:text-white shrink-0"
        :disabled="loading"
        @click="refresh"
      >
        {{ loading ? '...' : 'تازه‌سازی' }}
      </button>
    </div>
    <input
      v-model="answer"
      type="number"
      required
      inputmode="numeric"
      placeholder="پاسخ را وارد کنید"
      :disabled="loading || !question"
    >
    <p v-if="error" class="text-red-400 text-xs mt-1">{{ error }}</p>
  </div>
</template>
