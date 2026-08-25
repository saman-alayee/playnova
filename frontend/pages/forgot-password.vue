<script setup lang="ts">
useHead({ title: 'فراموشی رمز عبور | PlayNova' })

const api = useApi()
const flash = useState('flash')

const mobile = ref('')
const loading = ref(false)
const errors = ref<string[]>([])

async function submit() {
  loading.value = true
  errors.value = []
  try {
    const result = await api.auth.forgotPassword(mobile.value)
    flash.value = { success: 'کد بازیابی ارسال شد.' }
    if (result.token) {
      await navigateTo(`/reset-password/${result.token}`)
    }
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'ارسال کد ناموفق بود.']
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">فراموشی رمز عبور</h1>

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
        <input v-model="mobile" type="text" required inputmode="numeric" placeholder="09123456789">
      </div>
      <button type="submit" class="w-full btn-glow-primary rounded py-2" :disabled="loading">
        {{ loading ? '...' : 'ارسال کد بازیابی' }}
      </button>
    </form>

    <p class="text-sm text-center mt-4 text-gray-400">
      <NuxtLink to="/login" class="text-secondary">بازگشت به ورود</NuxtLink>
    </p>
  </div>
</template>
