<script setup lang="ts">
import type { HistoryItem } from '~/types/api'

useHead({ title: 'تاریخچه مسابقات | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('history', () => api.history(), {
  default: () => [] as HistoryItem[],
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">تاریخچه مسابقات</h1>

    <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-center text-gray-400">
      بارگذاری تاریخچه ممکن نشد.
    </div>
    <div v-else-if="!data?.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      مسابقه‌ای در تاریخچه ثبت نشده است.
    </div>
    <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="item in data"
        :key="item.id"
        class="card-tournament rounded-2xl p-5"
      >
        <h3 class="font-bold text-white mb-2">{{ item.title }}</h3>
        <p class="text-xs text-gray-400 mb-2">{{ item.status_label || item.status }}</p>
        <p v-if="item.start_date" class="text-xs text-gray-500 mb-2">
          {{ new Date(item.start_date).toLocaleDateString('fa-IR') }}
        </p>
        <p v-if="item.prize_pool" class="text-sm text-secondary font-bold">
          جایزه: {{ Number(item.prize_pool).toLocaleString('fa-IR') }} تومان
        </p>
        <p v-if="item.result" class="text-xs text-gray-300 mt-2">{{ item.result }}</p>
      </div>
    </div>
  </div>
</template>
