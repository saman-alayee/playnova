<script setup lang="ts">
useHead({ title: 'ثبت‌نام | PlayNova' })

const route = useRoute()
const auth = useAuthStore()
const api = useApi()
const captchaRef = ref<{ key: string; refresh: () => Promise<void> } | null>(null)

const form = reactive({
  username: '',
  mobile: '',
  password: '',
  password_confirmation: '',
  cod_id: '',
  referral_code: (route.query.ref as string) || '',
  accept_rules: false,
})

const captchaAnswer = ref('')
const loading = ref(false)
const errors = ref<string[]>([])
const fieldErrors = ref<Record<string, string[]>>({})

function setErrorsFromResponse(err: { message?: string; data?: { errors?: Record<string, string[]> } }) {
  fieldErrors.value = err.data?.errors || {}
  if (fieldErrors.value && Object.keys(fieldErrors.value).length > 0) {
    errors.value = Object.values(fieldErrors.value).flat()
    return
  }

  errors.value = [err.message || 'ثبت‌نام ناموفق بود.']
}

if (!auth.initialized) {
  await auth.init()
}

if (auth.isAuthenticated) {
  await navigateTo(auth.isAdmin ? '/admin' : (auth.isSeatAdmin ? '/admin/tournament-seats' : '/profile'))
}

function toAsciiDigits(value: string): string {
  return value.replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
}

function validateMobile(): string | null {
  const digits = toAsciiDigits(form.mobile).replace(/\D+/g, '')
  if (!digits) return 'شماره موبایل الزامی است.'
  if (digits.startsWith('9') && digits.length === 10) {
    return 'شماره موبایل باید با صفر شروع شود (مثال: 09123456789).'
  }
  if (!/^09\d{9}$/.test(digits) && !(digits.startsWith('98') && digits.length === 12 && /^989\d{9}$/.test(digits))) {
    return 'شماره موبایل معتبر نیست. شماره باید ۱۱ رقم و با ۰۹ شروع شود.'
  }
  return null
}

async function submit() {
  loading.value = true
  errors.value = []
  fieldErrors.value = {}
  try {
    const mobileError = validateMobile()
    if (mobileError) {
      fieldErrors.value = { mobile: [mobileError] }
      errors.value = [mobileError]
      return
    }

    const captchaKey = captchaRef.value?.key
    if (!captchaKey || captchaAnswer.value === '') {
      errors.value = ['لطفاً پاسخ کد امنیتی را وارد کنید.']
      return
    }

    const result = await api.auth.register({
      ...form,
      accept_rules: form.accept_rules ? '1' : '0',
      captcha_key: captchaKey,
      captcha: captchaAnswer.value,
    })
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
    setErrorsFromResponse(err)
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
    <h1 class="text-2xl font-bold mb-6 text-center">ثبت‌نام در PlayNova</h1>

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm mb-1 text-gray-400">نام کاربری</label>
        <input v-model="form.username" type="text" required autocomplete="username">
        <p v-if="fieldErrors.username?.[0]" class="text-xs text-danger mt-1">{{ fieldErrors.username[0] }}</p>
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">شماره موبایل</label>
        <input
          v-model="form.mobile"
          type="text"
          required
          inputmode="numeric"
          autocomplete="tel"
          maxlength="14"
          placeholder="09123456789"
          dir="ltr"
        >
        <p v-if="fieldErrors.mobile?.[0]" class="text-xs text-danger mt-1">{{ fieldErrors.mobile[0] }}</p>
        <p class="text-xs text-gray-500 mt-1">شماره باید با صفر شروع شود (مثال: 09123456789). با همین شماره وارد می‌شوید.</p>
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">آیدی کالاف <span class="text-gray-500">(نام شما در بازی کالاف دیوتی)</span></label>
        <input v-model="form.cod_id" type="text" required dir="ltr" class="font-mono">
        <p v-if="fieldErrors.cod_id?.[0]" class="text-xs text-danger mt-1">{{ fieldErrors.cod_id[0] }}</p>
        <p class="text-xs text-gray-500 mt-1">هر آیدی کالاف فقط یک‌بار قابل ثبت‌نام است و تکراری پذیرفته نمی‌شود.</p>
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">رمز عبور</label>
        <PasswordInput v-model="form.password" required autocomplete="new-password" />
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">تکرار رمز عبور</label>
        <PasswordInput v-model="form.password_confirmation" required autocomplete="new-password" />
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">کد معرف (اختیاری)</label>
        <input v-model="form.referral_code" type="text">
      </div>
      <label class="flex items-start gap-2 text-sm text-gray-400">
        <input v-model="form.accept_rules" type="checkbox" class="mt-1 shrink-0" required>
        <span>
          <NuxtLink to="/rules" class="text-secondary hover:underline">قوانین و مقررات</NuxtLink>
          را مطالعه کرده و می‌پذیرم.
        </span>
      </label>
      <AuthCaptcha ref="captchaRef" v-model="captchaAnswer" />
      <button type="submit" class="w-full btn-glow-primary rounded py-2" :disabled="loading">
        {{ loading ? '...' : 'ثبت‌نام' }}
      </button>
    </form>

    <p class="text-sm text-center mt-4 text-gray-400">
      حساب دارید؟
      <NuxtLink to="/login" class="text-secondary">ورود</NuxtLink>
    </p>
    </div>
  </div>
</template>
