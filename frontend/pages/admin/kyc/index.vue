<script setup lang="ts">
import type { KycSubmission } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت KYC | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('admin-kyc', () => api.admin.kyc(), {
  default: () => [] as KycSubmission[],
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">احراز هویت کاربران</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200">
      API `/api/v1/admin/kyc` در دسترس نیست.
    </div>
    <div v-else-if="!data?.length" class="bg-dark-800 border border-dark-600 rounded-xl p-8 text-center text-gray-500">
      درخواست KYC وجود ندارد.
    </div>
    <div v-else class="space-y-3">
      <div
        v-for="(item, i) in data"
        :key="item.id || i"
        class="bg-dark-800 border border-dark-600 rounded-xl p-4"
      >
        <p class="font-bold">کد ملی: {{ item.national_id || '—' }}</p>
        <p class="text-sm text-gray-400 mt-1">وضعیت: {{ item.status || 'pending' }}</p>
      </div>
    </div>
  </div>
</template>
