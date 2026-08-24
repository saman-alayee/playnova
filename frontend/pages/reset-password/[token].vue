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
  resending.value = true
  try {
    await api.auth.resendResetCode(token.value)
    flash.value = { success: 'کد جدید ارسال شد.' }
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'ارسال مجدد ناموفق بود.']
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div class="max-w-md mx-auto bg-dark-800 border border-dark-600 rounded-xl p-6">
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
        <input v-model="form.password" type="password" required autocomplete="new-password">
      </div>
      <div>
        <label class="block text-sm mb-1 text-gray-400">تکرار رمز عبور</label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password">
      </div>
      <button type="submit" class="w-full btn-glow-success rounded py-2 font-bold" :disabled="loading">
        {{ loading ? '...' : 'تغییر رمز عبور' }}
      </button>
    </form>

    <button
      type="button"
      class="w-full mt-3 text-sm text-secondary font-bold"
      :disabled="resending"
      @click="resend"
    >
      {{ resending ? '...' : 'ارسال مجدد کد' }}
    </button>
  </div>
</template>
