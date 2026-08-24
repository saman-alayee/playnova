<script setup lang="ts">
import type { Tournament } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت مسابقات | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('admin-tournaments', () => api.admin.tournaments(), {
  default: () => [] as Tournament[],
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت مسابقات</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200">
      API `/api/v1/admin/tournaments` در دسترس نیست — پس از پیاده‌سازی Laravel فعال می‌شود.
    </div>
    <div v-else-if="!data?.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      مسابقه‌ای یافت نشد.
    </div>
    <div v-else class="space-y-3">
      <div
        v-for="t in data"
        :key="t.id"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4 flex items-center justify-between gap-3"
      >
        <div>
          <p class="font-bold text-white">{{ t.title }}</p>
          <p class="text-xs text-gray-400">{{ t.status_label || t.status }} · {{ Number(t.prize_pool).toLocaleString('fa-IR') }} تومان</p>
        </div>
        <NuxtLink :to="`/tournaments/${t.id}`" class="text-sm text-secondary">مشاهده</NuxtLink>
      </div>
    </div>
  </div>
</template>
