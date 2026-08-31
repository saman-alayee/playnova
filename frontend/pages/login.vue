<script setup lang="ts">
useHead({ title: 'ورود | PlayNova' })

const route = useRoute()
const auth = useAuthStore()
const flash = useState('flash')
const captchaRef = ref<{ key: string; refresh: () => Promise<void> } | null>(null)

const mobile = ref('')
const password = ref('')
const remember = ref(false)
const captchaAnswer = ref('')
const loading = ref(false)
const errors = ref<string[]>([])

if (!auth.initialized) {
  await auth.init()
}

if (auth.isAuthenticated) {
  await navigateTo(auth.isAdmin ? '/admin' : '/profile')
}

async function submit() {
  loading.value = true
  errors.value = []
  try {
    const captchaKey = captchaRef.value?.key
    if (!captchaKey || captchaAnswer.value === '') {
      errors.value = ['لطفاً پاسخ کد امنیتی را وارد کنید.']
      return
    }

    await auth.login(mobile.value, password.value, remember.value, {
      key: captchaKey,
      answer: captchaAnswer.value,
    })
    flash.value = { success: 'با موفقیت وارد شدید.' }
    const redirect = auth.needsKycRedirect
      ? '/kyc'
      : ((route.query.redirect as string) || '/')
    await navigateTo(redirect)
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    if (err.data?.errors) {
      errors.value = Object.values(err.data.errors).flat()
    } else {
      errors.value = [err.message || 'ورود ناموفق بود.']
    }
    await captchaRef.value?.refresh()
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page-wrap">
    <div class="auth-page max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <div class="flex justify-center mb-4">
      <NuxtLink to="/" class="site-header-logo">
        <SiteLogoImage />
      </NuxtLink>
    </div>
    <h1 class="text-2xl font-bold mb-6 text-center">ورود به حساب کاربری</h1>

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
        <input
          v-model="mobile"
          type="text"
          required
          inputmode="numeric"
          autocomplete="tel"
          placeholder="09123456789"
        >
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">رمز عبور</label>
        <input
          v-model="password"
          type="password"
          required
          autocomplete="current-password"
        >
      </div>
      <AuthCaptcha ref="captchaRef" v-model="captchaAnswer" />
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <label class="inline-flex items-center gap-2 text-sm text-gray-400 w-fit max-w-full">
          <input v-model="remember" type="checkbox" class="shrink-0">
          <span class="whitespace-nowrap">مرا به خاطر بسپار</span>
        </label>
        <NuxtLink to="/forgot-password" class="text-red-500 hover:text-red-400 font-bold text-base whitespace-nowrap">
          فراموشی رمز عبور
        </NuxtLink>
      </div>
      <button
        type="submit"
        class="w-full btn-glow-success rounded py-2 font-bold shadow-glowsuccess"
        :disabled="loading"
      >
        {{ loading ? '...' : 'ورود' }}
      </button>
    </form>

    <p class="text-sm text-center mt-4 text-gray-400">
      حساب کاربری ندارید؟
      <NuxtLink to="/register" class="text-secondary">ثبت‌نام کنید</NuxtLink>
    </p>
    </div>
  </div>
</template>
