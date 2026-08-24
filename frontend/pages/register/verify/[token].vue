<script setup lang="ts">
useHead({ title: 'تأیید موبایل | PlayNova' })

const route = useRoute()
const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')

const token = computed(() => route.params.token as string)
const code = ref('')
const loading = ref(false)
const resending = ref(false)
const errors = ref<string[]>([])

async function submit() {
  loading.value = true
  errors.value = []
  try {
    const result = await api.auth.verifyRegister(token.value, code.value)
    api.setToken(result.token)
    auth.setUser(result.user)
    flash.value = { success: 'ثبت‌نام با موفقیت تکمیل شد.' }
    await navigateTo('/')
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'کد وارد شده صحیح نیست.']
  } finally {
    loading.value = false
  }
}

async function resend() {
  resending.value = true
  try {
    await api.auth.resendRegisterVerify(token.value)
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
    <h1 class="text-2xl font-bold mb-2 text-center">تأیید شماره موبایل</h1>
    <p class="text-sm text-gray-400 text-center mb-6">کد ۶ رقمی ارسال‌شده را وارد کنید.</p>

    <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
      <ul class="list-disc list-inside space-y-1">
        <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm mb-1 text-gray-400">کد تأیید</label>
        <input v-model="code" type="text" required inputmode="numeric" maxlength="6" placeholder="123456">
      </div>
      <button type="submit" class="w-full btn-glow-success rounded py-2 font-bold" :disabled="loading">
        {{ loading ? '...' : 'تأیید' }}
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
