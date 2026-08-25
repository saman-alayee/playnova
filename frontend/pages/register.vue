<script setup lang="ts">
useHead({ title: 'ثبت‌نام | PlayNova' })

const route = useRoute()
const auth = useAuthStore()
const api = useApi()

const form = reactive({
  username: '',
  mobile: '',
  password: '',
  password_confirmation: '',
  cod_id: '',
  referral_code: (route.query.ref as string) || '',
})

const loading = ref(false)
const errors = ref<string[]>([])

if (auth.isAuthenticated) {
  await navigateTo('/')
}

async function submit() {
  loading.value = true
  errors.value = []
  try {
    const result = await api.auth.register({ ...form })
    if (result.verification_required && result.token) {
      await navigateTo(`/register/verify/${result.token}`)
      return
    }
    if (result.token && result.user) {
      api.setToken(result.token)
      auth.setUser(result.user)
      await navigateTo('/')
      return
    }
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    if (err.data?.errors) {
      errors.value = Object.values(err.data.errors).flat()
    } else {
      errors.value = [err.message || 'ثبت‌نام ناموفق بود.']
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">ثبت‌نام</h1>

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm mb-1 text-gray-400">نام کاربری</label>
        <input v-model="form.username" type="text" required autocomplete="username">
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
        <input v-model="form.mobile" type="text" required inputmode="numeric" autocomplete="tel">
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">آیدی کالاف</label>
        <input v-model="form.cod_id" type="text" required>
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">رمز عبور</label>
        <input v-model="form.password" type="password" required autocomplete="new-password">
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">تکرار رمز عبور</label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password">
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">کد معرف (اختیاری)</label>
        <input v-model="form.referral_code" type="text">
      </div>
      <button type="submit" class="w-full btn-glow-primary rounded py-2" :disabled="loading">
        {{ loading ? '...' : 'ثبت‌نام' }}
      </button>
    </form>

    <p class="text-sm text-center mt-4 text-gray-400">
      حساب دارید؟
      <NuxtLink to="/login" class="text-secondary">ورود</NuxtLink>
    </p>
  </div>
</template>
