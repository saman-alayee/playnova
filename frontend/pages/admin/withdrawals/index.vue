<script setup lang="ts">
import type { Transaction } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت برداشت‌ها | PlayNova' })

const api = useApi()
const flash = useState<{ success?: string; error?: string } | null>('flash')
const { formatDateTime } = usePersianDateTime()
const status = ref('pending')

const { data, pending, error, refresh } = await useAsyncData(
  'admin-withdrawals',
  () => api.admin.withdrawals({ status: status.value }),
  { watch: [status] },
)

const withdrawals = computed(() => data.value?.items ?? [])
const rejectionReasons = reactive<Record<number, string>>({})

async function updateStatus(tx: Transaction, newStatus: string) {
  try {
    await api.admin.updateWithdrawal(tx.id, {
      status: newStatus,
      rejection_reason: newStatus === 'rejected' ? rejectionReasons[tx.id] : undefined,
    })
    flash.value = { success: 'وضعیت به‌روز شد.' }
    await refresh()
  } catch (e: unknown) {
    flash.value = { error: (e as Error).message }
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">درخواست‌های برداشت</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div class="flex gap-2 mb-4">
      <button v-for="s in ['pending', 'completed', 'rejected', 'all']" :key="s" type="button" class="text-xs px-3 py-1 rounded" :class="status === s ? 'bg-secondary text-white' : 'bg-dark-700 text-gray-400'" @click="status = s">
        {{ s === 'pending' ? 'معلق' : s === 'completed' ? 'تأیید شده' : s === 'rejected' ? 'رد شده' : 'همه' }}
      </button>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="text-red-400">{{ (error as Error).message }}</div>
    <div v-else-if="!withdrawals.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">درخواستی وجود ندارد.</div>

    <div v-else class="space-y-3">
      <div v-for="tx in withdrawals" :key="tx.id" class="bg-dark-800 border border-dark-600 rounded-xl p-4">
        <div class="flex flex-wrap justify-between gap-2 mb-2">
          <div>
            <p class="font-bold text-white">{{ Math.abs(Number(tx.amount)).toLocaleString('fa-IR') }} تومان</p>
            <p class="text-xs text-gray-400">{{ (tx.user as { username?: string })?.username }} — {{ tx.status }}</p>
          </div>
          <p class="text-xs text-gray-500">{{ formatDateTime(tx.created_at_display || tx.created_at) }}</p>
        </div>
        <p v-if="tx.description" class="text-xs text-gray-500 mb-2">{{ tx.description }}</p>
        <div v-if="tx.status === 'pending'" class="flex flex-wrap gap-2 items-center">
          <button type="button" class="text-xs bg-success text-white px-2 py-1 rounded" @click="updateStatus(tx, 'completed')">تأیید</button>
          <input v-model="rejectionReasons[tx.id]" type="text" placeholder="دلیل رد..." class="bg-dark-700 border border-dark-600 rounded px-2 py-1 text-xs text-white flex-1 min-w-[120px]">
          <button type="button" class="text-xs bg-danger text-white px-2 py-1 rounded" @click="updateStatus(tx, 'rejected')">رد</button>
        </div>
      </div>
    </div>
  </div>
</template>
