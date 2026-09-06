<script setup lang="ts">
useHead({ title: 'بازیابی رمز عبور | PlayNova' })

const route = useRoute()
const api = useApi()
const flash = useState('flash')

const token = computed(() => route.params.token as string)
const form = reactive({
  code: '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const resending = ref(false)
const errors = ref<string[]>([])
const { canResend, label, start, sync, applyError } = useOtpResendTimer(
  () => `otp-resend-reset-${token.value}`,
)

onMounted(async () => {
  try {
    const info = await api.auth.showResetPasswordVerify(token.value)
    sync(info.resend_after)
  } catch {
    // Keep the local countdown if the session info is unavailable.
  }
})

async function submit() {
  loading.value = true
  errors.value = []
  try {
    await api.auth.resetPassword(token.value, { ...form })
    flash.value = { success: 'رمز عبور با موفقیت تغییر کرد.' }
    await navigateTo('/login')
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'بازیابی رمز ناموفق بود.']
  } finally {
    loading.value = false
  }
}

async function resend() {
  if (!canResend.value || resending.value) {
    return
  }

  resending.value = true
  errors.value = []
  try {
    const result = await api.auth.resendResetCode(token.value)
    flash.value = { success: 'کد جدید ارسال شد.' }
    start(result.resend_after)
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: unknown } }
    applyError(err)
    errors.value = [err.message || 'ارسال مجدد ناموفق بود.']
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div class="auth-page-wrap">
    <div class="auth-page max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">بازیابی رمز عبور</h1>

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm mb-1 text-gray-400">کد تأیید</label>
        <input v-model="form.code" type="text" required inputmode="numeric" maxlength="6">
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">رمز عبور جدید</label>
        <PasswordInput v-model="form.password" required autocomplete="new-password" />
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">تکرار رمز عبور</label>
        <PasswordInput v-model="form.password_confirmation" required autocomplete="new-password" />
      </div>
      <button type="submit" class="w-full btn-glow-success rounded py-2 font-bold" :disabled="loading">
        {{ loading ? '...' : 'تغییر رمز عبور' }}
      </button>
    </form>

    <button
      type="button"
      class="otp-resend-btn"
      :disabled="resending || !canResend"
      @click="resend"
    >
      {{ resending ? '...' : label }}
    </button>
    </div>
  </div>
</template>
