<script setup lang="ts">
definePageMeta({ middleware: 'admin', layout: 'admin' })

const route = useRoute()
const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const { formatDateTime } = usePersianDateTime()

const tournamentId = computed(() => String(route.params.id))

const { data: tournament } = usePageData(
  () => `admin-tournament-prizes-meta-${tournamentId.value}`,
  () => api.admin.tournament(tournamentId.value),
)

const { data: batch, pending, refresh } = usePageData(
  () => `admin-tournament-prizes-${tournamentId.value}`,
  () => api.admin.tournamentPrizes(tournamentId.value),
)

useHead({
  title: computed(() =>
    tournament.value ? `تأیید جوایز — ${tournament.value.title}` : 'تأیید جوایز',
  ),
})

const editableEntries = ref<Array<{ id: number; prize_amount: number }>>([])

watch(batch, (value) => {
  editableEntries.value = (value?.entries ?? []).map((entry) => ({
    id: entry.id,
    prize_amount: entry.prize_amount,
  }))
}, { immediate: true })

const payableTotal = computed(() =>
  (batch.value?.entries ?? []).reduce((sum, entry) => {
    if (entry.confirmation_status === 'unconfirmed') return sum
    const editable = editableEntries.value.find((item) => item.id === entry.id)
    return sum + Number(editable?.prize_amount ?? entry.prize_amount ?? 0)
  }, 0),
)

const prizeBudget = computed(() =>
  Number(batch.value?.prize_pool ?? tournament.value?.prize_pool ?? 0),
)

const leftover = computed(() => prizeBudget.value - payableTotal.value)

const budgetOverflows = computed(() => payableTotal.value - prizeBudget.value > 0.5)

const canApprove = computed(() =>
  batch.value?.status === 'pending_approval' && payableTotal.value > 0 && !budgetOverflows.value,
)

async function saveAmounts() {
  try {
    await api.admin.updateTournamentPrizes(tournamentId.value, editableEntries.value)
    flash.value = { success: 'مبالغ جوایز ذخیره شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}

async function approvePrizes() {
  if (budgetOverflows.value) {
    flash.value = { error: `مجموع قابل واریز بیشتر از بودجه مسابقه (${prizeBudget.value.toLocaleString('fa-IR')} تومان) است.` }
    return
  }
  if (!confirm('پیش‌نمایش زیر تأیید شود و فقط مبالغ تأییدشده به کیف پول واریز شود؟ بازیکنان عدم تأیید واریز نمی‌شوند.')) return
  try {
    await api.admin.approveTournamentPrizes(tournamentId.value)
    flash.value = { success: 'جوایز با موفقیت واریز شدند.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-white">تأیید جوایز مسابقه</h1>
        <p v-if="tournament" class="text-sm text-gray-400 mt-1">{{ tournament.title }}</p>
      </div>
      <NuxtLink to="/admin/tournaments" class="text-sm text-secondary">← لیست مسابقات</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>

    <div v-else-if="!batch" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      هنوز نتیجه/جایزه‌ای برای این مسابقه ثبت نشده است.
    </div>

    <div v-else class="space-y-4">
      <div class="bg-dark-800 border border-dark-600 rounded-xl p-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4 text-sm">
        <div>
          <span class="text-gray-400">وضعیت:</span>
          <span class="text-white mr-2">{{ batch.status_label }}</span>
        </div>
        <div>
          <span class="text-gray-400">بودجه مسابقه:</span>
          <span class="text-white font-bold mr-2">{{ prizeBudget.toLocaleString('fa-IR') }} تومان</span>
        </div>
        <div>
          <span class="text-gray-400">مجموع قابل واریز:</span>
          <span class="font-bold mr-2" :class="budgetOverflows ? 'text-red-300' : 'text-secondary'">
            {{ payableTotal.toLocaleString('fa-IR') }} تومان
          </span>
        </div>
        <div v-if="leftover > 0.5">
          <span class="text-gray-400">باقیمانده (واریز نمی‌شود):</span>
          <span class="text-amber-300 font-bold mr-2">{{ leftover.toLocaleString('fa-IR') }} تومان</span>
        </div>
        <div v-if="batch.winner">
          <span class="text-gray-400">برنده:</span>
          <span class="text-white mr-2">{{ batch.winner.username }}</span>
        </div>
        <div v-if="batch.approved_by">
          <span class="text-gray-400">تأییدکننده:</span>
          <span class="text-white mr-2">{{ batch.approved_by.username }}</span>
        </div>
        <div v-if="batch.approved_at">
          <span class="text-gray-400">زمان تأیید:</span>
          <span class="text-white mr-2">{{ formatDateTime(batch.approved_at) }}</span>
        </div>
      </div>

      <div class="bg-dark-800 border border-dark-600 rounded-xl overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
          <thead>
            <tr class="bg-dark-700 text-gray-400">
              <th class="py-2 px-3 text-right">رتبه</th>
              <th class="py-2 px-3 text-right">بازیکن</th>
              <th class="py-2 px-3 text-right">تیم/جایگاه</th>
              <th class="py-2 px-3 text-right">کیل</th>
              <th class="py-2 px-3 text-right">وضعیت</th>
              <th class="py-2 px-3 text-right">مبلغ جایزه (تومان)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in batch.entries" :key="entry.id" class="border-b border-dark-700">
              <td class="py-2 px-3">{{ entry.rank ?? '—' }}</td>
              <td class="py-2 px-3">
                <div>{{ entry.username }}</div>
                <div v-if="entry.cod_id" class="text-xs text-gray-500 font-mono">{{ entry.cod_id }}</div>
              </td>
              <td class="py-2 px-3">{{ entry.team_label ?? '—' }}</td>
              <td class="py-2 px-3">{{ entry.kills ?? '—' }}</td>
              <td class="py-2 px-3" :class="entry.confirmation_status === 'unconfirmed' ? 'text-amber-300' : 'text-green-300'">
                {{ entry.confirmation_label || (entry.confirmation_status === 'unconfirmed' ? 'عدم تأیید' : 'تأیید شده') }}
              </td>
              <td class="py-2 px-3">
                <input
                  v-if="batch.status === 'pending_approval' && entry.confirmation_status !== 'unconfirmed'"
                  v-model.number="editableEntries.find(e => e.id === entry.id)!.prize_amount"
                  type="number"
                  min="0"
                  class="w-32 bg-dark-700 border border-dark-600 rounded px-2 py-1 text-white"
                >
                <span v-else>{{ Number(entry.prize_amount).toLocaleString('fa-IR') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="batch.status === 'pending_approval'" class="text-sm text-gray-400">
        این صفحه فقط پیش‌نمایش است. تا زدن تأیید نهایی هیچ واریزی انجام نمی‌شود.
        بازیکنان «عدم تأیید» مبلغ ۰ دارند و برایشان تراکنش ساخته نمی‌شود.
      </p>
      <p v-if="batch.status === 'pending_approval' && budgetOverflows" class="text-sm text-red-300">
        مجموع قابل واریز از بودجه بیشتر است. مبالغ را کم کنید.
      </p>

      <div v-if="batch.status === 'pending_approval'" class="flex gap-3">
        <button type="button" class="bg-secondary text-white px-4 py-2 rounded font-bold" @click="saveAmounts">
          ذخیره مبالغ
        </button>
        <button
          type="button"
          class="bg-success text-white px-4 py-2 rounded font-bold disabled:opacity-50"
          :disabled="!canApprove"
          @click="approvePrizes"
        >
          تأیید نهایی و واریز مبالغ تأییدشده
        </button>
      </div>
    </div>
  </div>
</template>
