<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useHead({ title: 'کیف پول | PlayNova' })

const api = useApi()
const auth = useAuthStore()
const flash = useState('flash')
const { formatDateTime } = usePersianDateTime()
const { formatToman } = useFormatToman()

const { data, pending, refresh } = await useAsyncData('wallet', () => api.wallet.show())

const depositAmount = ref<number | null>(null)
const withdrawForm = reactive({
  amount: null as number | null,
  bank_card_confirm: '',
})
const loadingDeposit = ref(false)
const loadingWithdraw = ref(false)
const errors = ref<string[]>([])

const typeLabels: Record<string, string> = {
  deposit: 'شارژ',
  withdraw: 'برداشت',
  fee: 'ورودی مسابقه',
  entry_fee: 'ورودی مسابقه',
  prize: 'جایزه',
  referral_bonus: 'پاداش معرفی',
  admin_credit: 'واریز ادمین',
  admin_debit: 'کسر ادمین',
}

async function deposit() {
  if (!depositAmount.value) return
  loadingDeposit.value = true
  errors.value = []
  try {
    const result = await api.wallet.deposit(depositAmount.value)
    if (result.redirect_url) {
      window.location.assign(result.redirect_url)
      return
    }
    flash.value = { success: 'درخواست شارژ ثبت شد.' }
    await refresh()
    await auth.fetchUser()
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'شارژ ناموفق بود.']
  } finally {
    loadingDeposit.value = false
  }
}

async function withdraw() {
  loadingWithdraw.value = true
  errors.value = []
  try {
    await api.wallet.withdraw({
      amount: withdrawForm.amount,
      bank_card_confirm: withdrawForm.bank_card_confirm,
    })
    flash.value = { success: 'درخواست برداشت ثبت شد.' }
    withdrawForm.amount = null
    withdrawForm.bank_card_confirm = ''
    await refresh()
    await auth.fetchUser()
  } catch (e: unknown) {
    const err = e as Error
    errors.value = [err.message || 'برداشت ناموفق بود.']
  } finally {
    loadingWithdraw.value = false
  }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">کیف پول</h1>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <template v-else-if="data">
      <div v-if="errors.length" class="bg-danger/20 border border-danger/50 text-danger px-4 py-3 rounded-xl text-sm mb-4">
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(err, i) in errors" :key="i">{{ err }}</li>
        </ul>
      </div>

      <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="bg-dark-800 border border-success/40 rounded-xl p-6">
          <p class="text-sm text-gray-400 mb-1">موجودی فعلی</p>
          <p class="text-3xl font-black text-secondary mb-4">{{ formatToman(data.balance) }}</p>

          <h3 class="font-bold mb-2">شارژ کیف پول</h3>
          <p v-if="data.kyc_verified" class="text-xs text-green-400/90 mb-2">احراز هویت تأیید شده — سقف واریز برداشته شده است.</p>
          <p v-else class="text-xs text-amber-400/90 mb-2">
            تا تأیید احراز هویت، سقف واریز محدود است.
            <NuxtLink to="/kyc" class="text-secondary hover:underline">ارسال مدارک</NuxtLink>
          </p>
          <form class="flex gap-2" @submit.prevent="deposit">
            <input
              v-model.number="depositAmount"
              type="number"
              min="10000"
              :max="data.max_deposit || 50000000"
              step="1000"
              placeholder="مبلغ به تومان"
              required
              class="flex-1"
            >
            <button type="submit" class="bg-success hover:opacity-90 text-white rounded px-4 py-2 font-bold whitespace-nowrap" :disabled="loadingDeposit">
              شارژ
            </button>
          </form>
        </div>

        <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
          <h3 class="font-bold mb-2">درخواست برداشت</h3>
          <p v-if="!auth.user?.bank_card_number" class="text-xs text-amber-400/90 mb-3">
            ابتدا شماره کارت را در
            <NuxtLink to="/profile" class="text-secondary hover:underline">پروفایل</NuxtLink>
            ثبت کنید.
          </p>
          <p v-else class="text-xs text-gray-400 mb-3">
            کارت ثبت‌شده: {{ auth.user.bank_card_number }}
          </p>
          <form class="space-y-3" @submit.prevent="withdraw">
            <input v-model.number="withdrawForm.amount" type="number" min="50000" placeholder="مبلغ برداشت (تومان)" required>
            <input
              v-model="withdrawForm.bank_card_confirm"
              type="text"
              inputmode="numeric"
              placeholder="تأیید شماره کارت (مطابق پروفایل)"
              required
            >
            <button type="submit" class="bg-primary hover:opacity-90 text-white rounded px-4 py-2 font-bold" :disabled="loadingWithdraw">
              ثبت درخواست
            </button>
          </form>
        </div>
      </div>

      <div class="bg-dark-800 border border-dark-600 rounded-xl overflow-hidden">
        <h3 class="font-bold p-4 border-b border-dark-600">تراکنش‌ها</h3>
        <div v-if="!data.transactions?.length" class="p-6 text-center text-gray-500 text-sm">تراکنشی ثبت نشده است.</div>
        <div v-else class="divide-y divide-dark-600">
          <div
            v-for="tx in data.transactions"
            :key="tx.id"
            class="px-4 py-3 flex items-center justify-between gap-3 text-sm"
          >
            <div>
              <p class="font-bold">{{ tx.type_label || typeLabels[tx.type] || tx.type }}</p>
              <p class="text-xs text-gray-500">{{ formatDateTime(tx.created_at_display || tx.created_at) }}</p>
            </div>
            <div class="text-left">
              <p class="font-bold" :class="Number(tx.amount) >= 0 ? 'text-success' : 'text-danger'">
                {{ Number(tx.amount).toLocaleString('fa-IR') }} تومان
              </p>
              <p class="text-xs text-gray-400">{{ tx.status_label || tx.status }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
