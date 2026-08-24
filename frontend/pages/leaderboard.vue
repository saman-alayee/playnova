<script setup lang="ts">
import type { LeaderboardEntry } from '~/types/api'

useHead({ title: 'رتبه‌بندی | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('leaderboard', () => api.leaderboard(), {
  default: () => [] as LeaderboardEntry[],
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">رتبه‌بندی بازیکنان</h1>

    <div v-if="pending" class="text-center py-10 text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-dark-600 rounded-xl p-6 text-center text-gray-400">
      بارگذاری رتبه‌بندی ممکن نشد.
    </div>
    <div v-else-if="!data?.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      هنوز داده‌ای ثبت نشده است.
    </div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-dark-900/80 text-gray-400">
          <tr>
            <th class="px-4 py-3 text-right">رتبه</th>
            <th class="px-4 py-3 text-right">بازیکن</th>
            <th class="px-4 py-3 text-right">کیل</th>
            <th class="px-4 py-3 text-right hidden sm:table-cell">برد</th>
            <th class="px-4 py-3 text-right hidden sm:table-cell">باخت</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in data"
            :key="entry.user_id"
            class="border-t border-dark-600 hover:bg-dark-900/40"
          >
            <td class="px-4 py-3 font-bold text-primary">#{{ entry.rank }}</td>
            <td class="px-4 py-3 font-semibold">{{ entry.username }}</td>
            <td class="px-4 py-3">{{ entry.kills?.toLocaleString('fa-IR') }}</td>
            <td class="px-4 py-3 hidden sm:table-cell text-success">{{ entry.wins ?? 0 }}</td>
            <td class="px-4 py-3 hidden sm:table-cell text-danger">{{ entry.losses ?? 0 }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
