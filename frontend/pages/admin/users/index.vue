<script setup lang="ts">
import type { User } from '~/types/api'

definePageMeta({ middleware: 'admin', layout: 'admin' })
useHead({ title: 'مدیریت کاربران | PlayNova' })

const api = useApi()
const { data, pending, error } = await useAsyncData('admin-users', () => api.admin.users(), {
  default: () => [] as User[],
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-white">مدیریت کاربران</h1>
      <NuxtLink to="/admin" class="text-sm text-secondary">← داشبورد</NuxtLink>
    </div>

    <div v-if="pending" class="text-gray-500">در حال بارگذاری...</div>
    <div v-else-if="error" class="bg-dark-800 border border-amber-600/40 rounded-xl p-6 text-amber-200">
      API `/api/v1/admin/users` در دسترس نیست.
    </div>
    <div v-else class="bg-dark-800 border border-dark-600 rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-dark-900/80 text-gray-400">
          <tr>
            <th class="px-4 py-3 text-right">کاربر</th>
            <th class="px-4 py-3 text-right">موبایل</th>
            <th class="px-4 py-3 text-right">کیف پول</th>
            <th class="px-4 py-3 text-right">کیل</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in data" :key="user.id" class="border-t border-dark-600">
            <td class="px-4 py-3 font-semibold">{{ user.username }}</td>
            <td class="px-4 py-3" dir="ltr">{{ user.mobile }}</td>
            <td class="px-4 py-3">{{ Number(user.wallet).toLocaleString('fa-IR') }}</td>
            <td class="px-4 py-3">{{ user.kills ?? 0 }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
