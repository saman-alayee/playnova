<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useHead({ title: 'پروفایل | PlayNova' })

const api = useApi()
const flash = useState('flash')

const { data, pending, refresh } = await useAsyncData('profile', () => api.profile.show())

const form = reactive({
  username: '',
  email: '',
  mobile: '',
  cod_id: '',
  password: '',
  password_confirmation: '',
})

watch(
  () => data.value?.user,
  (user) => {
    if (!user) return
    form.username = user.username || ''
    form.email = user.email || ''
    form.mobile = user.mobile || ''
    form.cod_id = user.cod_id || ''
  },
  { immediate: true },
)

const loading = ref(false)
const errors = ref<string[]>([])

async function submit() {
  loading.value = true
  errors.value = []
  try {
    const payload: Record<string, string> = {
      username: form.username,
      email: form.email,
      mobile: form.mobile,
      cod_id: form.cod_id,
    }
    if (form.password) {
      payload.password = form.password
      payload.password_confirmation = form.password_confirmation
    }
    await api.profile.update(payload)
    flash.value = { success: 'پروفایل با موفقیت به‌روزرسانی شد.' }
    await refresh()
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'ذخیره ناموفق بود.']
  } finally {
    loading.value = false
  }
}

function copyReferralLink() {
  const link = `${window.location.origin}/register?ref=${data.value?.user.referral_code || ''}`
  navigator.clipboard.writeText(link)
  flash.value = { success: 'لینک دعوت کپی شد!' }
}
</script>

<template>
  <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>
  <div v-else-if="data?.user" class="grid md:grid-cols-3 gap-6">
    <div class="md:col-span-1 bg-dark-800 border border-dark-600 rounded-xl p-6 text-center">
      <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-secondary mx-auto mb-3 flex items-center justify-center text-2xl font-black">
        {{ data.user.username?.charAt(0)?.toUpperCase() }}
      </div>
      <h2 class="font-bold text-lg">{{ data.user.username }}</h2>
      <p class="text-xs text-gray-400 mb-3">COD ID: {{ data.user.cod_id || '—' }}</p>
      <div class="mt-4 bg-dark-700 rounded-lg p-3">
        <p class="text-xs text-gray-400">موجودی کیف پول</p>
        <p class="text-xl font-bold text-secondary">{{ Number(data.user.wallet).toLocaleString('fa-IR') }} تومان</p>
      </div>
      <div class="mt-3 bg-dark-700 rounded-lg p-3">
        <p class="text-xs text-gray-400">کد معرف شما</p>
        <p class="font-mono text-primary font-bold">{{ data.user.referral_code }}</p>
        <button type="button" class="text-xs bg-primary/20 text-primary px-2 py-1 rounded mt-2" @click="copyReferralLink">
          کپی لینک دعوت
        </button>
      </div>
      <div v-if="data.active_seats?.length" class="mt-3 bg-dark-700 rounded-lg p-3 text-right">
        <p class="text-xs text-gray-400 mb-2">جایگاه‌های فعال شما</p>
        <ul class="space-y-2">
          <li v-for="reg in data.active_seats" :key="reg.id" class="text-sm">
            <span class="text-white font-bold">{{ reg.tournament?.title }}</span>
            <span class="text-secondary font-mono" dir="ltr">{{ reg.seat_number }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="md:col-span-2 space-y-6">
      <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h3 class="font-bold mb-4">ویرایش اطلاعات</h3>
        <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
          </ul>
        </div>
        <form class="space-y-3" @submit.prevent="submit">
          <input v-model="form.username" type="text" placeholder="نام کاربری">
          <input v-model="form.email" type="email" placeholder="ایمیل">
          <input v-model="form.mobile" type="text" placeholder="موبایل">
          <input
            v-model="form.cod_id"
            type="text"
            placeholder="آیدی کالاف"
            :readonly="data.user.cod_id_changed && !!data.user.cod_id"
          >
          <p class="text-xs text-yellow-500/90">فقط یک‌بار امکان تغییر آیدی کالاف وجود دارد.</p>
          <input v-model="form.password" type="password" placeholder="رمز عبور جدید (اختیاری)">
          <input v-model="form.password_confirmation" type="password" placeholder="تکرار رمز عبور جدید">
          <button type="submit" class="bg-secondary hover:opacity-90 text-white rounded px-4 py-2 font-bold" :disabled="loading">
            {{ loading ? '...' : 'ذخیره تغییرات' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
