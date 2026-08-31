<script setup lang="ts">
import type { Transaction } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت برداشت‌ها | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const { formatDateTime } = usePersianDateTime()
const { formatToman } = useFormatToman()

const view = ref<'withdrawals' | 'transactions'>('withdrawals')
const status = ref('pending')
const page = ref(1)
const txPage = ref(1)
const userSearch = ref('')
const txType = ref('all')
const rejectionReasons = reactive<Record<number, string>>({})
const approveChecks = reactive<Record<number, boolean>>({})

const txTypeOptions = [
  { value: 'all', label: 'همه' },
  { value: 'deposit', label: 'شارژ' },
  { value: 'withdraw', label: 'برداشت' },
  { value: 'fee', label: 'ورودی مسابقه' },
  { value: 'entry_fee', label: 'ورودی مسابقه' },
  { value: 'prize', label: 'جایزه' },
  { value: 'referral_bonus', label: 'پاداش معرفی' },
  { value: 'admin_credit', label: 'واریز ادمین' },
  { value: 'admin_debit', label: 'کسر ادمین' },
]

const { data, pending, error, refresh } = await useAsyncData(
  'admin-withdrawals',
  () => api.admin.withdrawals({ status: status.value, page: page.value }),
  { watch: [status, page] },
)

const {
  data: txData,
  pending: txPending,
  error: txError,
  refresh: refreshTransactions,
} = await useAsyncData(
  'admin-transactions',
  () =>
    api.admin.transactions({
      user_search: userSearch.value || undefined,
      tx_type: txType.value,
      page: txPage.value,
    }),
  { watch: [txPage], immediate: false },
)

watch(view, (next) => {
  if (next === 'transactions' && !txData.value) {
    refreshTransactions()
  }
})

const withdrawals = computed(() => data.value?.items ?? [])
const transactions = computed(() => txData.value?.items ?? [])
const financialSummary = computed(() => data.value?.financialSummary)
const userTransactions = computed(() => data.value?.userTransactions ?? {})

function statusClass(value: string) {
  if (value === 'pending') return 'text-yellow-300'
  if (value === 'completed') return 'text-green-300'
  if (value === 'rejected') return 'text-red-300'
  return 'text-gray-300'
}

function extractCard(tx: Transaction): string | null {
  if (tx.user?.bank_card_number) return tx.user.bank_card_number
  const match = tx.description?.match(/کارت:\s*([0-9]+)/u)
  return match?.[1] ?? null
}

function extractHolder(tx: Transaction): string | null {
  if (tx.user?.bank_account_name) return tx.user.bank_account_name
  const match = tx.description?.match(/صاحب حساب:\s*([^|]+)/u)
  return match?.[1]?.trim() ?? null
}

function userTxList(tx: Transaction): Transaction[] {
  const key = String(tx.user_id ?? tx.user?.id ?? '')
  return userTransactions.value[key] ?? []
}

function applyTransactionSearch() {
  txPage.value = 1
  refreshTransactions()
}

function goToPage(next: number, meta?: { last_page?: number } | null) {
  const last = meta?.last_page ?? 1
  if (next < 1 || next > last) return
  page.value = next
}

function goToTxPage(next: number, meta?: { last_page?: number } | null) {
  const last = meta?.last_page ?? 1
  if (next < 1 || next > last) return
  txPage.value = next
}

async function updateStatus(tx: Transaction, newStatus: string) {
  if (newStatus === 'completed' && !approveChecks[tx.id]) {
    flash.value = { error: 'برای تأیید، ابتدا گزینه بررسی کارت را علامت بزنید.' }
    return
  }

  if (newStatus === 'rejected') {
    const reason = (rejectionReasons[tx.id] || '').trim()
    if (reason.length < 3) {
      flash.value = { error: 'برای رد برداشت، دلیل حداقل ۳ کاراکتر الزامی است.' }
      return
    }
  }

  const label = tx.user?.username || 'کاربر'
  const amount = formatToman(tx.amount)
  const message =
    newStatus === 'completed'
      ? `آیا از تأیید برداشت ${amount} برای ${label} مطمئن هستید؟`
      : 'آیا از رد این درخواست برداشت مطمئن هستید؟'

  if (!confirm(message)) return

  try {
    await api.admin.updateWithdrawal(tx.id, {
      status: newStatus,
      rejection_reason: newStatus === 'rejected' ? rejectionReasons[tx.id] : undefined,
    })
    flash.value = {
      success: newStatus === 'completed' ? 'برداشت تأیید شد.' : 'برداشت رد شد و مبلغ بازگردانده شد.',
    }
    delete rejectionReasons[tx.id]
    delete approveChecks[tx.id]
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت برداشت‌ها و تراکنش‌ها</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
      <div class="bg-dark-800 border border-yellow-500/40 rounded-xl p-4">
        <p class="text-xs text-gray-400">برداشت‌های در انتظار</p>
        <p class="text-xl font-bold text-yellow-400">{{ formatToman(financialSummary?.pending_withdraws) }}</p>
        <p v-if="financialSummary?.pending_withdrawals_count" class="text-xs text-gray-500 mt-1">
          {{ Number(financialSummary.pending_withdrawals_count).toLocaleString('fa-IR') }} درخواست
        </p>
      </div>
      <div class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <p class="text-xs text-gray-400">برداشت‌های تأیید شده</p>
        <p class="text-xl font-bold text-white">{{ formatToman(financialSummary?.total_withdraws_completed) }}</p>
      </div>
      <div class="bg-dark-800 border border-secondary/40 rounded-xl p-4">
        <p class="text-xs text-gray-400">جمع مبالغ در کیف پول کاربران</p>
        <p class="text-xl font-bold text-secondary">{{ formatToman(financialSummary?.total_wallets) }}</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <button
        type="button"
        class="px-3 py-1.5 rounded text-sm"
        :class="view === 'withdrawals' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-300'"
        @click="view = 'withdrawals'"
      >
        درخواست‌های برداشت
      </button>
      <button
        type="button"
        class="px-3 py-1.5 rounded text-sm"
        :class="view === 'transactions' ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-300'"
        @click="view = 'transactions'"
      >
        همه تراکنش‌ها
      </button>
    </div>

    <template v-if="view === 'withdrawals'">
      <div class="flex flex-wrap gap-2 mb-4">
        <button
          v-for="item in [
            { key: 'pending', label: 'در انتظار' },
            { key: 'all', label: 'همه' },
            { key: 'completed', label: 'تأیید شده' },
            { key: 'rejected', label: 'رد شده' },
          ]"
          :key="item.key"
          type="button"
          class="px-3 py-1 rounded text-xs"
          :class="status === item.key ? 'bg-primary text-white' : 'bg-dark-700 text-gray-300'"
          @click="status = item.key; page = 1"
        >
          {{ item.label }}
        </button>
      </div>

      <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
      <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>
      <div v-else-if="!withdrawals.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
        درخواست برداشتی در این فیلتر یافت نشد.
      </div>

      <div v-else class="space-y-4">
        <div v-for="tx in withdrawals" :key="tx.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4">
          <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-3">
            <div>
              <span class="text-gray-400">کاربر:</span>
              <strong class="text-white">{{ tx.user?.username || '—' }}</strong>
            </div>
            <div>
              <span class="text-gray-400">مبلغ:</span>
              <strong class="text-secondary">{{ formatToman(tx.amount) }}</strong>
            </div>
            <div>
              <span class="text-gray-400">کارت:</span>
              <span dir="ltr" class="font-mono text-xs">{{ extractCard(tx) || '—' }}</span>
            </div>
            <div>
              <span class="text-gray-400">صاحب حساب:</span>
              {{ extractHolder(tx) || '—' }}
            </div>
            <div>
              <span class="text-gray-400">وضعیت:</span>
              <span :class="statusClass(tx.status)">{{ tx.status_label || tx.status }}</span>
            </div>
            <div>
              <span class="text-gray-400">تاریخ:</span>
              <span v-if="tx.status === 'pending'">درخواست {{ formatDateTime(tx.created_at_display || tx.created_at) }}</span>
              <span v-else>{{ tx.status === 'completed' ? 'تأیید' : 'رد' }} {{ formatDateTime(tx.displayed_at_display || tx.updated_at || tx.created_at) }}</span>
            </div>
          </div>

          <p v-if="tx.rejection_reason" class="text-sm text-red-300 mb-3 bg-red-500/10 border border-red-500/20 rounded px-3 py-2">
            دلیل رد: {{ tx.rejection_reason }}
          </p>

          <details v-if="tx.status === 'pending'" class="mb-3 group">
            <summary class="cursor-pointer text-sm text-secondary hover:underline select-none">بررسی و تصمیم‌گیری</summary>
            <div class="mt-3 grid md:grid-cols-2 gap-4">
              <div class="border border-green-500/30 rounded-lg p-3 bg-green-500/5">
                <h4 class="font-bold text-green-300 text-sm mb-2">تأیید برداشت</h4>
                <p class="text-xs text-gray-400 mb-3">
                  پس از تأیید، مبلغ {{ formatToman(tx.amount) }} به کاربر پرداخت‌شده ثبت می‌شود.
                </p>
                <label class="flex items-start gap-2 text-xs text-gray-300 mb-2">
                  <input v-model="approveChecks[tx.id]" type="checkbox" class="mt-0.5">
                  <span>اطلاعات کارت را بررسی کردم و تأیید می‌کنم.</span>
                </label>
                <button
                  type="button"
                  class="w-full bg-success text-white py-2 rounded text-sm font-bold"
                  @click="updateStatus(tx, 'completed')"
                >
                  تأیید نهایی برداشت
                </button>
              </div>
              <div class="border border-red-500/30 rounded-lg p-3 bg-red-500/5">
                <h4 class="font-bold text-red-300 text-sm mb-2">رد برداشت</h4>
                <p class="text-xs text-gray-400 mb-2">مبلغ به کیف پول کاربر بازگردانده می‌شود.</p>
                <textarea
                  v-model="rejectionReasons[tx.id]"
                  rows="3"
                  maxlength="500"
                  placeholder="دلیل رد (الزامی — به کاربر نمایش داده می‌شود)"
                  class="w-full bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs outline-none focus:border-danger resize-y mb-2 text-white"
                />
                <button
                  type="button"
                  class="w-full bg-danger text-white py-2 rounded text-sm font-bold"
                  @click="updateStatus(tx, 'rejected')"
                >
                  رد نهایی برداشت
                </button>
              </div>
            </div>
          </details>

          <details>
            <summary class="cursor-pointer text-sm text-gray-300 hover:text-white select-none">
              تراکنش‌های این کاربر ({{ userTxList(tx).length }})
            </summary>
            <div v-if="!userTxList(tx).length" class="text-xs text-gray-500 mt-2">تراکنشی ثبت نشده.</div>
            <div v-else class="mt-2 overflow-x-auto">
              <table class="w-full text-xs min-w-[640px]">
                <thead>
                  <tr class="text-gray-500 border-b border-dark-600">
                    <th class="py-1 px-2 text-right">تاریخ</th>
                    <th class="py-1 px-2 text-right">نوع</th>
                    <th class="py-1 px-2 text-right">مبلغ</th>
                    <th class="py-1 px-2 text-right">وضعیت</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="userTx in userTxList(tx).slice(0, 30)" :key="userTx.id" class="border-b border-dark-700/50">
                    <td class="py-1 px-2 whitespace-nowrap">{{ formatDateTime(userTx.created_at_display || userTx.created_at) }}</td>
                    <td class="py-1 px-2">{{ userTx.type_label || userTx.type }}</td>
                    <td class="py-1 px-2">{{ formatToman(userTx.amount, false) }}</td>
                    <td class="py-1 px-2">{{ userTx.status_label || userTx.status }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </details>
        </div>
      </div>

      <div v-if="data?.meta && data.meta.last_page > 1" class="flex items-center justify-center gap-3 mt-4">
        <button type="button" class="text-xs px-3 py-1 rounded bg-dark-700 text-gray-300 disabled:opacity-40" :disabled="page <= 1" @click="goToPage(page - 1, data.meta)">قبلی</button>
        <span class="text-xs text-gray-400">{{ page.toLocaleString('fa-IR') }} / {{ data.meta.last_page.toLocaleString('fa-IR') }}</span>
        <button type="button" class="text-xs px-3 py-1 rounded bg-dark-700 text-gray-300 disabled:opacity-40" :disabled="page >= data.meta.last_page" @click="goToPage(page + 1, data.meta)">بعدی</button>
      </div>
    </template>

    <template v-else>
      <form class="flex flex-wrap gap-2 mb-4 items-end" @submit.prevent="applyTransactionSearch">
        <div>
          <label class="block text-xs text-gray-400 mb-1">جستجوی کاربر</label>
          <input
            v-model="userSearch"
            type="text"
            placeholder="نام کاربری، موبایل، آیدی کالاف"
            class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-sm outline-none focus:border-secondary min-w-[220px] text-white"
          >
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">نوع تراکنش</label>
          <select v-model="txType" class="bg-dark-700 border border-dark-600 rounded px-3 py-2 text-sm outline-none focus:border-secondary text-white">
            <option v-for="option in txTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <button type="submit" class="bg-dark-600 hover:bg-dark-500 text-white px-4 py-2 rounded text-sm">جستجو</button>
      </form>

      <div v-if="txPending" class="text-gray-500">در حال بارگذاری...</div>
      <div v-else-if="txError" class="text-red-400">{{ (txError as Error).message }}</div>
      <div v-else class="bg-dark-800 border border-dark-600 rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[800px]">
          <thead>
            <tr class="bg-dark-700 text-gray-400">
              <th class="py-2 px-3 text-right">کاربر</th>
              <th class="py-2 px-3 text-right">نوع</th>
              <th class="py-2 px-3 text-right">مبلغ</th>
              <th class="py-2 px-3 text-right">وضعیت</th>
              <th class="py-2 px-3 text-right">توضیح</th>
              <th class="py-2 px-3 text-right">تاریخ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!transactions.length">
              <td colspan="6" class="py-6 text-center text-gray-500">تراکنشی یافت نشد.</td>
            </tr>
            <tr v-for="tx in transactions" :key="tx.id" class="border-b border-dark-700">
              <td class="py-2 px-3">{{ tx.user?.username || '—' }}</td>
              <td class="py-2 px-3">{{ tx.type_label || tx.type }}</td>
              <td class="py-2 px-3">{{ formatToman(tx.amount) }}</td>
              <td class="py-2 px-3">{{ tx.status_label || tx.status }}</td>
              <td class="py-2 px-3 text-xs text-gray-400 max-w-xs truncate" :title="tx.description || undefined">{{ tx.description || '—' }}</td>
              <td class="py-2 px-3 whitespace-nowrap">{{ formatDateTime(tx.created_at_display || tx.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="txData?.meta && txData.meta.last_page > 1" class="flex items-center justify-center gap-3 mt-4">
        <button type="button" class="text-xs px-3 py-1 rounded bg-dark-700 text-gray-300 disabled:opacity-40" :disabled="txPage <= 1" @click="goToTxPage(txPage - 1, txData.meta)">قبلی</button>
        <span class="text-xs text-gray-400">{{ txPage.toLocaleString('fa-IR') }} / {{ txData.meta.last_page.toLocaleString('fa-IR') }}</span>
        <button type="button" class="text-xs px-3 py-1 rounded bg-dark-700 text-gray-300 disabled:opacity-40" :disabled="txPage >= txData.meta.last_page" @click="goToTxPage(txPage + 1, txData.meta)">بعدی</button>
      </div>
    </template>
  </div>
</template>
