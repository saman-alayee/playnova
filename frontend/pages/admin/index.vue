<script setup lang="ts">
import type { AdminDashboard } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'پنل مدیریت | PlayNova' })

const api = useApi()
const { formatToman } = useFormatToman()

const { data, pending } = await useAsyncData('admin-dashboard', () => api.admin.dashboard(), {
  default: () => ({}) as AdminDashboard,
})

function formatCount(value?: number) {
  if (value === undefined || value === null) return '—'
  return Number(value).toLocaleString('fa-IR')
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6 text-white">داشبورد مدیریت</h1>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <template v-else>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">تعداد کاربران</p>
          <p class="text-2xl font-black text-primary">{{ formatCount(data?.total_users) }}</p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">تعداد مسابقات</p>
          <p class="text-2xl font-black text-secondary">{{ formatCount(data?.total_tournaments) }}</p>
        </div>
        <div class="bg-dark-800 border border-primary/40 rounded-xl p-5">
          <p class="text-xs text-gray-400">مسابقات فعال</p>
          <p class="text-2xl font-black text-primary">{{ formatCount(data?.active_tournaments) }}</p>
        </div>
        <!-- بخش تیکت‌ها بسته شد.
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">تیکت‌های باز</p>
          <p class="text-2xl font-black text-white">{{ formatCount(data?.open_tickets) }}</p>
        </div>
        -->
      </div>

      <h2 class="font-bold mb-4 text-white">گزارش مالی</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <div class="bg-dark-800 border border-green-500/40 rounded-xl p-5">
          <p class="text-xs text-gray-400">مجموع شارژها</p>
          <p class="text-xl font-bold text-green-400">{{ formatToman(data?.total_deposits) }}</p>
        </div>
        <div class="bg-dark-800 border border-yellow-500/40 rounded-xl p-5">
          <p class="text-xs text-gray-400">برداشت‌های در انتظار</p>
          <p class="text-xl font-bold text-yellow-400">{{ formatToman(data?.pending_withdraws) }}</p>
          <p v-if="data?.pending_withdrawals_count" class="text-xs text-gray-500 mt-1">
            {{ formatCount(data.pending_withdrawals_count) }} درخواست
          </p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">برداشت‌های تأیید شده</p>
          <p class="text-xl font-bold text-white">{{ formatToman(data?.total_withdraws_completed) }}</p>
        </div>
        <div class="bg-dark-800 border border-secondary/40 rounded-xl p-5">
          <p class="text-xs text-gray-400">جمع مبالغ در کیف پول کاربران</p>
          <p class="text-xl font-bold text-secondary">{{ formatToman(data?.total_wallets) }}</p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">مجموع ورودی مسابقات</p>
          <p class="text-xl font-bold text-white">{{ formatToman(data?.total_entry_fees) }}</p>
        </div>
        <div class="bg-dark-800 border border-dark-600 rounded-xl p-5">
          <p class="text-xs text-gray-400">مجموع جوایز پرداخت شده</p>
          <p class="text-xl font-bold text-white">{{ formatToman(data?.total_prizes_paid) }}</p>
        </div>
        <div class="bg-dark-800 border border-secondary/40 rounded-xl p-5 sm:col-span-2 lg:col-span-1">
          <p class="text-xs text-gray-400">درآمد خالص (ورودی − جایزه)</p>
          <p class="text-xl font-bold text-secondary">{{ formatToman(data?.net_revenue) }}</p>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-dark-800 border border-amber-500/40 rounded-xl p-5">
          <p class="text-xs text-gray-400">KYC معلق</p>
          <p class="text-2xl font-black text-amber-300">{{ formatCount(data?.pending_kyc) }}</p>
        </div>
        <NuxtLink
          to="/admin/errors"
          class="bg-dark-800 border rounded-xl p-5 transition"
          :class="(data?.unresolved_api_errors ?? 0) > 0 ? 'border-red-500/40 hover:border-red-400/60' : 'border-dark-600 hover:border-dark-500'"
        >
          <p class="text-xs text-gray-400">خطاهای API بررسی‌نشده</p>
          <p class="text-2xl font-black" :class="(data?.unresolved_api_errors ?? 0) > 0 ? 'text-red-400' : 'text-white'">
            {{ formatCount(data?.unresolved_api_errors) }}
          </p>
        </NuxtLink>
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
        <NuxtLink to="/admin/errors" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-red-400/40 transition">
          خطاهای API
        </NuxtLink>
        <NuxtLink to="/admin/site-settings" class="bg-dark-800 border border-dark-600 rounded-xl p-4 hover:border-primary/40 transition">
          تنظیمات سایت
        </NuxtLink>
      </div>
    </template>
  </div>
</template>
