<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useHead({ title: 'پروفایل | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')
const { formatToman } = useFormatToman()

const { data, pending, error, refresh } = usePageData('profile', () => api.profile.show())

const user = computed(() => data.value?.user ?? null)
const activeSeats = computed(() => data.value?.active_seats ?? user.value?.active_seats ?? [])

const form = reactive({
  username: '',
  email: '',
  mobile: '',
  cod_id: '',
  password: '',
  password_confirmation: '',
})

const bankForm = reactive({
  bank_card_number: '',
  bank_account_name: '',
})

watch(
  user,
  (next) => {
    if (!next) return
    form.username = next.username || ''
    form.email = next.email || ''
    form.mobile = next.mobile || ''
    form.cod_id = next.cod_id || ''
    bankForm.bank_card_number = next.bank_card_number || ''
    bankForm.bank_account_name = next.bank_account_name || ''
  },
  { immediate: true },
)

const loading = ref(false)
const bankLoading = ref(false)
const errors = ref<string[]>([])
const bankErrors = ref<string[]>([])

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
    form.password = ''
    form.password_confirmation = ''
    await refresh()
    await auth.fetchUser()
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    errors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'ذخیره ناموفق بود.']
  } finally {
    loading.value = false
  }
}

async function submitBank() {
  if (!user.value) return
  bankLoading.value = true
  bankErrors.value = []
  try {
    await api.profile.update({
      username: user.value.username,
      email: user.value.email || '',
      mobile: user.value.mobile || '',
      cod_id: user.value.cod_id || '',
      bank_card_number: bankForm.bank_card_number,
      bank_account_name: bankForm.bank_account_name,
    })
    flash.value = { success: 'اطلاعات بانکی ذخیره شد.' }
    await refresh()
    await auth.fetchUser()
  } catch (e: unknown) {
    const err = e as { message?: string; data?: { errors?: Record<string, string[]> } }
    bankErrors.value = err.data?.errors
      ? Object.values(err.data.errors).flat()
      : [err.message || 'ذخیره ناموفق بود.']
  } finally {
    bankLoading.value = false
  }
}

function copyReferralLink() {
  if (!import.meta.client || !user.value?.referral_code) return
  const link = `${window.location.origin}/register?ref=${user.value.referral_code}`
  navigator.clipboard.writeText(link)
  flash.value = { success: 'لینک دعوت کپی شد!' }
}
</script>

<template>
  <PageLoading v-if="pending" />
  <div v-else-if="error" class="bg-danger/20 border border-danger/50 text-danger px-4 py-6 rounded-xl text-center">
    {{ (error as Error).message || 'بارگذاری پروفایل ناموفق بود.' }}
    <button type="button" class="block mx-auto mt-3 text-sm underline" @click="refresh()">تلاش مجدد</button>
  </div>
  <div v-else-if="user" class="grid md:grid-cols-3 gap-6">
    <div class="md:col-span-1 bg-dark-800 border border-dark-600 rounded-xl p-6 text-center">
      <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-secondary mx-auto mb-3 flex items-center justify-center text-2xl font-black">
        {{ user.username?.charAt(0)?.toUpperCase() }}
      </div>
      <h2 class="font-bold text-lg text-white">{{ user.username }}</h2>
      <p class="text-xs text-gray-400 mb-3">COD ID: {{ user.cod_id || '—' }}</p>
      <div class="mt-4 bg-dark-700 rounded-lg p-3">
        <p class="text-xs text-gray-400">موجودی کیف پول</p>
        <p class="text-xl font-bold text-secondary">{{ formatToman(user.wallet) }}</p>
      </div>
      <div class="mt-3 bg-dark-700 rounded-lg p-3">
        <p class="text-xs text-gray-400">کد معرف شما</p>
        <p class="font-mono text-primary font-bold">{{ user.referral_code }}</p>
        <button type="button" class="text-xs bg-primary/20 text-primary px-2 py-1 rounded mt-2" @click="copyReferralLink">
          کپی لینک دعوت
        </button>
      </div>
      <div v-if="data?.referral_bonus_percent" class="mt-3 bg-dark-700 rounded-lg p-3">
        <p class="text-xs text-gray-400">پاداش معرف</p>
        <p class="text-sm text-secondary">{{ data.referral_bonus_percent }}% از اولین شارژ کاربر معرف</p>
      </div>
      <div v-if="activeSeats.length" class="mt-3 bg-dark-700 rounded-lg p-3 text-right">
        <p class="text-xs text-gray-400 mb-2">جایگاه‌های فعال شما</p>
        <ul class="space-y-2">
          <li v-for="reg in activeSeats" :key="reg.id" class="text-sm flex items-center justify-between gap-2">
            <NuxtLink
              v-if="reg.tournament?.id"
              :to="`/tournaments/${reg.tournament.id}/select-seat`"
              class="text-white font-bold truncate hover:text-secondary"
            >
              {{ reg.tournament.title }}
            </NuxtLink>
            <span v-else class="text-white font-bold truncate">{{ reg.tournament?.title }}</span>
            <NuxtLink
              v-if="reg.tournament?.id"
              :to="`/tournaments/${reg.tournament.id}/select-seat`"
              class="text-secondary font-mono shrink-0 hover:underline"
              dir="ltr"
            >
              {{ reg.seat_label || reg.seat_number }}
            </NuxtLink>
            <span v-else class="text-secondary font-mono shrink-0" dir="ltr">{{ reg.seat_label || reg.seat_number }}</span>
          </li>
        </ul>
        <p class="text-[10px] text-gray-500 mt-2">روی مسابقه بزنید تا نقشه جایگاه‌ها و هم‌تیمی‌ها را ببینید. پس از پایان مسابقه این لیست خالی می‌شود.</p>
      </div>
    </div>

    <div class="md:col-span-2 space-y-6">
      <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h3 class="font-bold mb-4 text-white">ویرایش اطلاعات</h3>
        <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
          </ul>
        </div>
        <form class="space-y-3" @submit.prevent="submit">
          <input v-model="form.username" type="text" placeholder="نام کاربری" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
          <input v-model="form.email" type="email" placeholder="ایمیل" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
          <input v-model="form.mobile" type="text" placeholder="موبایل" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
          <input
            v-model="form.cod_id"
            type="text"
            placeholder="آیدی کالاف"
            class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white"
            :readonly="user.cod_id_changed && !!user.cod_id"
          >
          <p class="text-xs text-yellow-500/90">فقط یک‌بار امکان تغییر آیدی کالاف وجود دارد. در صورت نیاز به تغییرات بیشتر از بخش ارتباط با ما استفاده کنید.</p>
          <input v-model="form.password" type="password" placeholder="رمز عبور جدید (اختیاری)" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
          <input v-model="form.password_confirmation" type="password" placeholder="تکرار رمز عبور جدید" class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white">
          <button type="submit" class="bg-secondary hover:opacity-90 text-white rounded px-4 py-2 font-bold" :disabled="loading">
            {{ loading ? '...' : 'ذخیره تغییرات' }}
          </button>
        </form>
      </div>

      <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h3 class="font-bold mb-2 text-white">اطلاعات حساب بانکی</h3>
        <p class="text-xs text-gray-500 mb-4">برای برداشت وجه و تطبیق با احراز هویت، شماره کارت و نام صاحب حساب را ثبت کنید.</p>
        <div v-if="bankErrors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(err, i) in bankErrors" :key="i">{{ err }}</li>
          </ul>
        </div>
        <form class="space-y-3" @submit.prevent="submitBank">
          <div>
            <label class="block text-xs text-gray-400 mb-1">شماره کارت بانکی</label>
            <input
              v-model="bankForm.bank_card_number"
              type="text"
              placeholder="6037xxxxxxxxxxxx"
              dir="ltr"
              maxlength="24"
              inputmode="numeric"
              class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary font-mono text-white"
            >
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">نام صاحب حساب (مطابق کارت بانکی)</label>
            <input
              v-model="bankForm.bank_account_name"
              type="text"
              placeholder="نام و نام خانوادگی"
              class="w-full bg-dark-700 border border-dark-600 rounded px-3 py-2 outline-none focus:border-secondary text-white"
            >
          </div>
          <button type="submit" class="bg-secondary hover:opacity-90 text-white rounded px-4 py-2 font-bold" :disabled="bankLoading">
            {{ bankLoading ? '...' : 'ذخیره اطلاعات بانکی' }}
          </button>
        </form>
      </div>

      <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
        <h3 class="font-bold mb-2 text-white">تراکنش‌ها</h3>
        <p class="text-sm text-gray-400">
          تاریخچه واریز و برداشت در
          <NuxtLink to="/wallet" class="text-secondary hover:underline">صفحه کیف پول</NuxtLink>
          نمایش داده می‌شود.
        </p>
      </div>
    </div>
  </div>
</template>
