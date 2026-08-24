<script setup lang="ts">
import type { Transaction } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت برداشت‌ها | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('admin-withdrawals', () => api.admin.withdrawals(), {
  default: () => [] as Transaction[],
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">درخواست‌های برداشت</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200">
      API `/api/v1/admin/withdrawals` در دسترس نیست.
    </div>
    <div v-else-if="!data?.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      درخواستی وجود ندارد.
    </div>
    <div v-else class="space-y-3">
      <div
        v-for="tx in data"
        :key="tx.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex items-center justify-between"
      >
        <div>
          <p class="font-bold">{{ Math.abs(Number(tx.amount)).toLocaleString('fa-IR') }} تومان</p>
          <p class="text-xs text-gray-400">{{ tx.status_label || tx.status }}</p>
        </div>
        <p class="text-xs text-gray-500">{{ new Date(tx.created_at).toLocaleString('fa-IR') }}</p>
      </div>
    </div>
  </div>
</template>
