<script setup lang="ts">
import type { AdminDashboard } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'پنل مدیریت | PlayNova' })

const api = useApi()

const { data, pending, error } = await useAsyncData('admin-dashboard', () => api.admin.dashboard(), {
  default: () => ({}) as AdminDashboard,
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">داشبورد مدیریت</h1>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200">
      API مدیریت هنوز در Laravel پیاده‌سازی نشده — این صفحه پس از اتصال `/api/v1/admin/dashboard` فعال می‌شود.
    </div>
    <template v-else>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">کاربران</p>
          <p class="text-2xl font-black text-primary">{{ data?.users_count ?? '—' }}</p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">مسابقات</p>
          <p class="text-2xl font-black text-secondary">{{ data?.tournaments_count ?? '—' }}</p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">برداشت‌های معلق</p>
          <p class="text-2xl font-black text-amber-300">{{ data?.pending_withdrawals ?? '—' }}</p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">KYC معلق</p>
          <p class="text-2xl font-black text-success">{{ data?.pending_kyc ?? '—' }}</p>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <NuxtLink to="/admin/tournaments" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-primary/40 transition">
          مدیریت مسابقات
        </NuxtLink>
        <NuxtLink to="/admin/users" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-primary/40 transition">
          مدیریت کاربران
        </NuxtLink>
        <NuxtLink to="/admin/withdrawals" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-primary/40 transition">
          برداشت‌ها
        </NuxtLink>
        <NuxtLink to="/admin/kyc" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-primary/40 transition">
          احراز هویت
        </NuxtLink>
        <NuxtLink to="/admin/site-settings" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-primary/40 transition">
          تنظیمات سایت
        </NuxtLink>
      </div>
    </template>
  </div>
</template>
